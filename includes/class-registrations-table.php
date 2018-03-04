<?php

class RFMP_Registrations_Table extends WP_List_Table {

    function get_columns()
    {
        $columns = array();
        $columns['created_at'] = __('Date/time', 'mollie-forms');
        $columns['post_id'] = __('Form', 'mollie-forms');
        $columns['customer'] = __('Customer', 'mollie-forms');
        $columns['total_price'] = __('Total price', 'mollie-forms');
        $columns['recurring_price'] = __('Recurring price', 'mollie-forms');
        $columns['payment_status'] = __('Payment status', 'mollie-forms');
        $columns['price_frequency'] = __('Frequency', 'mollie-forms');
        $columns['subscription_status'] = __('Subscription status', 'mollie-forms');
        $columns['description'] = __('Description', 'mollie-forms');
        $columns['actions'] = '';

        return $columns;
    }

    function column_actions($item)
    {
        $url_view   = 'edit.php?post_type=rfmp&page=registration&view=' . $item['id'];
        $url_delete = wp_nonce_url('edit.php?post_type=rfmp&page=registration&view=' . $item['id'] . '&delete=true', 'delete-reg_' . $item['id']);
        return sprintf('<a href="%s">' . esc_html__('View', 'mollie-forms') . '</a> <a href="%s" style="color:#a00;" onclick="return confirm(\'' . esc_html__('Are you sure?', 'mollie-forms') . '\');">' . esc_html__('Delete', 'mollie-forms') . '</a>', $url_view, $url_delete);
    }

    function prepare_items()
    {
        global $wpdb;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $where = '';
        if (isset($_GET['post']))
            $where .= ' WHERE post_id="' . esc_sql($_GET['post']) . '"';

        $registrations = $wpdb->get_results("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . $where . " ORDER BY id DESC", ARRAY_A);

        $per_page = 25;
        $current_page = $this->get_pagenum();
        $total_items = count($registrations);

        $d = array_slice($registrations,(($current_page-1)*$per_page),$per_page);

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );
        $this->items = $d;
    }


    function column_default($item, $column_name)
    {
        global $wpdb;
        switch($column_name) {
            case 'customer':
                $name = $wpdb->get_row("SELECT value FROM " . RFMP_TABLE_REGISTRATION_FIELDS . " WHERE type='name' AND registration_id=" . $item['id']);
                return $name->value;
                break;
            case 'total_price':
                return '&euro; ' . number_format($item[$column_name], 2, ',', '');
                break;
            case 'recurring_price':
                if ((float)$item[$column_name])
                    return '&euro; ' . number_format($item[$column_name], 2, ',', '');
                return '';
                break;
            case 'post_id':
                $post = get_post($item[$column_name]);
                return $post->post_title;
                break;
            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item[$column_name]));
                break;
            case 'price_frequency':
                return $this->frequency_label($item[$column_name]);
                break;
            case 'payment_status':
                $payments = $wpdb->get_var("SELECT COUNT(*) FROM " . RFMP_TABLE_PAYMENTS . " WHERE payment_status='paid' AND registration_id=" . (int) $item['id']);
                return $payments ? '<span style="color: green;">' . __('Paid', 'mollie-forms') . ' (' . $payments . 'x)</span>' : '<span style="color: red;">' . __('Not paid', 'mollie-forms') . '</span>';
                break;
            case 'subscription_status':
                $reg = $wpdb->get_row("SELECT subs_fix FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id=" . $item['id']);
                if ($reg->subs_fix)
                    $subs_table = RFMP_TABLE_SUBSCRIPTIONS;
                else
                    $subs_table = RFMP_TABLE_CUSTOMERS;

                $subscriptions = $wpdb->get_var("SELECT COUNT(*) FROM " . $subs_table . " WHERE sub_status='active' AND registration_id=" . (int) $item['id']);
                if ($item['price_frequency'] == 'once' || $item['price_frequency'] == 'manual')
                    return '';

                return $subscriptions ? '<span style="color: green;">' . __('Active', 'mollie-forms') . '</span>' : '<span style="color: red;">' . __('Not active', 'mollie-forms') . '</span>';
                break;
            default:
                return $item[$column_name];
        }
    }

    public function display_tablenav( $which ) {
        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
            <?php $this->pagination( $which );?>
            <br class="clear" />
        </div>
        <?php
    }

    private function frequency_label($frequency)
    {
        $frequency = trim($frequency);
        switch ($frequency)
        {
            case 'once':
                $return = '';
                break;
            case 'manual':
                $return = 'manual';
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
                $return = __('each', 'mollie-forms') . ' ' . $frequency;
        }

        return $return;
    }
}