<?php
class RFMP_Webhook {

    private $wpdb;

    /**
     * Hook WordPress
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        add_filter('query_vars', array($this, 'add_query_vars'), 0);
        add_action('parse_request', array($this, 'sniff_requests'), 0);
        add_action('init', array($this, 'add_endpoint'), 0);
    }

    /**
     * Add public query vars
     * @param array $vars List of current public query vars
     * @return array $vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = '__rfmpapi';
        $vars[] = 'mollie-forms';
        $vars[] = 'post_id';
        $vars[] = 'sub';
        $vars[] = 'first';
        return $vars;
    }

    /**
     * Add API Endpoint
     * @return void
     */
    public function add_endpoint()
    {
        add_rewrite_rule('^rfmp-webhook/([0-9]+)/first/([0-9]+)/?', 'index.php?__rfmpapi=1&post_id=$matches[1]&first=$matches[2]', 'top');
        add_rewrite_rule('^rfmp-webhook/([0-9]+)/sub/([0-9]+)/?', 'index.php?__rfmpapi=1&post_id=$matches[1]&sub=$matches[2]', 'top');
        add_rewrite_rule('^rfmp-webhook/([0-9]+)/?','index.php?__rfmpapi=1&post_id=$matches[1]','top');
        flush_rewrite_rules();
    }

    /**
     * Sniff Requests
     * @param $query
     * @return die if API request
     */
    public function sniff_requests($query)
    {
        if(
            ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($query->query_vars['__rfmpapi'])) ||
            ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($query->query_vars['mollie-forms']))
        )
        {
            echo $this->handle_request($query);
            exit;
        }
    }

    /**
     * Handle Webhook Request
     * @param $query
     * @return string
     */
    protected function handle_request($query)
    {
        try {
            $post       = $query->query_vars['post_id'];
            $api_key    = get_post_meta($post, '_rfmp_api_key', true);
            $payment_id = $_POST['id'];

            $webhook    = get_home_url(null, RFMP_WEBHOOK . $post);

            // Connect with Mollie
            $mollie = new Mollie_API_Client;
            $mollie->setApiKey($api_key);

            do_action('rfmp_webhook_called', $post, $payment_id);

            // Recurring payment of subscription
            if (isset($query->query_vars['sub']))
            {
                $sub = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_SUBSCRIPTIONS . " WHERE id = '" . esc_sql($query->query_vars['sub']) . "'");
                if ($sub == null)
                {
                    $sub = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_CUSTOMERS . " WHERE id = '" . esc_sql($query->query_vars['sub']) . "' AND registration_id != '0'");
                    if ($sub == null)
                    {
                        status_header(404);
                        return 'Subscription not found';
                    }
                }

                $registration = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id = '" . esc_sql($sub->registration_id) . "'");
                if ($registration == null)
                {
                    status_header(404);
                    return 'Registration not found';
                }

                $payment = $mollie->payments->get($payment_id);
                $regPayment = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_PAYMENTS . " WHERE payment_id = '" . esc_sql($payment->id) . "'");
                if ($regPayment == null)
                {
                    // Payment not found, add
                    $rfmp_id = uniqid('rfmp-' . $post . '-');
                    $this->wpdb->query($this->wpdb->prepare("INSERT INTO " . RFMP_TABLE_PAYMENTS . "
                    ( created_at, registration_id, payment_id, payment_method, payment_mode, payment_status, amount, rfmp_id )
                    VALUES ( NOW(), %d, %s, %s, %s, %s, %s, %s )",
                        $registration->id,
                        $payment->id,
                        $payment->method,
                        $payment->mode,
                        $payment->status,
                        $payment->amount,
                        $rfmp_id
                    ));
                }
                else
                {
                    // Payment found, update
                    $this->wpdb->query($this->wpdb->prepare("UPDATE " . RFMP_TABLE_PAYMENTS . " SET payment_status = %s, payment_method = %s, payment_mode = %s WHERE payment_id = %s",
                        $payment->status,
                        $payment->method,
                        $payment->mode,
                        $payment->id
                    ));
                }

                return 'OK, ' . $payment_id . ', Post ID: ' . $post . ', Subscription ID: ' . $sub->subscription_id;
            }


            $regPayment = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_PAYMENTS . " WHERE payment_id = '" . esc_sql($payment_id) . "'");
            if ($regPayment == null)
            {
                status_header(404);
                return 'Payment of registration not found';
            }

            $payment = $mollie->payments->get($payment_id);
            $this->wpdb->query($this->wpdb->prepare("UPDATE " . RFMP_TABLE_PAYMENTS . " SET payment_status = %s, payment_method = %s, payment_mode = %s WHERE payment_id = %s",
                $payment->status,
                $payment->method,
                $payment->mode,
                $payment_id
            ));

            // E-mail
            $this->sendEmail($post, $regPayment->registration_id, $payment, 'customer');
            $this->sendEmail($post, $regPayment->registration_id, $payment, 'merchant');

            // First payment
            if (isset($query->query_vars['first']) && ($payment->isPaid() && !$payment->isRefunded()))
            {
                $registration = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id = '" . esc_sql($query->query_vars['first']) . "'");
                if ($registration == null)
                {
                    status_header(404);
                    return 'Registration not found';
                }

                // subscriptions table fix
                if ($registration->subs_fix)
                    $subs_table = RFMP_TABLE_SUBSCRIPTIONS;
                else
                    $subs_table = RFMP_TABLE_CUSTOMERS;

                $this->wpdb->query($this->wpdb->prepare("INSERT INTO " . $subs_table . "
                    ( registration_id, customer_id, created_at )
                    VALUES ( %s, %s, NOW())",
                    $registration->id,
                    $registration->customer_id
                ));
                $sub_id = $this->wpdb->insert_id;

                $subscription = $mollie->customers_subscriptions->withParentId($registration->customer_id)->create(array(
                    "amount"      => $registration->total_price,
                    "interval"    => $registration->price_frequency,
                    "times"       => $registration->number_of_times > 0 ? ($registration->number_of_times - 1) : null,
                    "description" => $registration->description,
                    "webhookUrl"  => $webhook . '&sub=' . $sub_id,
                    "startDate"   => date('Y-m-d', strtotime("+" . $registration->price_frequency, strtotime(date('Y-m-d')))),
                ));

                if (isset($subscription->id) && $subscription->id)
                {
                    $this->wpdb->query($this->wpdb->prepare("UPDATE " . $subs_table . " SET subscription_id = %s, sub_mode = %s, sub_amount = %s, sub_times = %s, sub_interval = %s, sub_description = %s, sub_method = %s, sub_status = %s WHERE id = %d",
                        $subscription->id,
                        $subscription->mode,
                        $subscription->amount,
                        $subscription->times,
                        $subscription->interval,
                        $subscription->description,
                        $subscription->method,
                        $subscription->status,
                        $sub_id
                    ));
                }
                else
                {
                    $this->wpdb->query($this->wpdb->prepare("DELETE FROM " . $subs_table . " WHERE id = %s",
                        $sub_id
                    ));
                }

                return 'OK, ' . $payment_id . ', Post ID: ' . $post . ', Subscription ID: ' . $subscription->id;
            }

            return 'OK, ' . $payment_id . ', Post ID: ' . $post;

        } catch (Mollie_API_Exception $e) {
            status_header(500);
            return "API call failed: " . $e->getMessage();
        }
    }

    protected function sendEmail($post, $registration_id, $payment, $type)
    {
        if (!in_array($payment->status, array('paid', 'expired', 'cancelled')))
            return;

        $enabled = get_post_meta($post, '_rfmp_enabled_' . $payment->status . '_' . $type, true);
        if ($enabled != '1')
            return;

        $registration = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id=" . (int) $registration_id);
        $priceoption = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_REGISTRATION_FIELDS . " WHERE type='priceoptions' AND registration_id=" . (int) $registration_id);

        $data = array();
        $search     = array(
            '{rfmp="amount"}',
            '{rfmp="interval"}',
            '{rfmp="status"}',
            '{rfmp="payment_id"}',
            '{rfmp="form_title"}',
            '{rfmp="created_at"}',
            '{rfmp="priceoption"}',
            '{rfmp="url"}',
        );
        $replace    = array(
            $payment->amount,
            $this->frequency_label($registration->price_frequency),
            $payment->status,
            $payment->id,
            get_the_title($post),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->created_at)),
            $priceoption->value,
            get_permalink($post),
        );

        $fields = $this->wpdb->get_results("SELECT * FROM " . RFMP_TABLE_REGISTRATION_FIELDS . " WHERE registration_id=" . (int) $registration_id);
        foreach ($fields as $row)
        {
            if ($row->type == 'email')
                $data['to_email'] = $row->value;

            $data[$row->field]  = $row->value;
            $search[]           = '{rfmp="' . trim($row->field) . '"}';
            $replace[]          = $row->value;
        }

        $email = get_post_meta($post, '_rfmp_email_' . $payment->status . '_' . $type, true);
        $email = str_replace($search, $replace, $email);
        $email = str_replace(array('http://', 'https://'), '//', $email);
        $email = str_replace('//', (is_ssl() ? 'https://' : 'http://'), $email);
        $email = nl2br($email);

        $subject = get_post_meta($post, '_rfmp_subject_' . $payment->status . '_' . $type, true);
        $subject = str_replace($search, $replace, $subject);

        $fromname = get_post_meta($post, '_rfmp_fromname_' . $payment->status . '_' . $type, true);
        $fromemail = get_post_meta($post, '_rfmp_fromemail_' . $payment->status . '_' . $type, true);

        $to = $type == 'customer' ? $data['to_email'] : $fromemail;

        $fromemail = explode(',', trim($fromemail));
        $headers[] = 'From: ' . $fromname . ' <' . $fromemail[0] . '>';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        wp_mail($to, $subject, $email, $headers);
    }

    private function frequency_label($frequency)
    {
        $frequency  = trim($frequency);
        $search     = array(
            'months',
            'weeks',
            'days',
            'year',
        );
        $replace    = array(
            __('months', 'mollie-forms'),
            __('weeks', 'mollie-forms'),
            __('days', 'mollie-forms'),
            __('year', 'mollie-forms'),
        );

        switch ($frequency)
        {
            case 'once':
            case '':
                $return = '';
                break;
            case '1 months':
                $return = __('per month', 'mollie-forms');
                break;
            case '1 month':
                $return = __('per month', 'mollie-forms');
                break;
            case '3 months':
                $return = __('each quarter', 'mollie-forms');
                break;
            case '12 months':
                $return = __('per year', 'mollie-forms');
                break;
            case '1 weeks':
                $return = __('per week', 'mollie-forms');
                break;
            case '1 week':
                $return = __('per week', 'mollie-forms');
                break;
            case '1 days':
                $return = __('per day', 'mollie-forms');
                break;
            case '1 day':
                $return = __('per day', 'mollie-forms');
                break;
            default:
                $return = __('each', 'mollie-forms') . ' ' . str_replace($search, $replace, $frequency);
        }

        return $return;
    }
}