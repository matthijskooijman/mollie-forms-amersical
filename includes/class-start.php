<?php

Class RFMP_Start {

    private $wpdb, $mollie, $required_errors;

    function __construct()
    {
        global $wpdb;

        add_action('init', array($this, 'add_registration_form_type'), 0);
        add_shortcode('rfmp', array($this, 'add_rfmp_shortcode'));
        add_shortcode('rfmp-total', array($this, 'add_rfmp_total_shortcode'));
        add_shortcode('rfmp-goal', array($this, 'add_rfmp_goal_shortcode'));

        $this->wpdb     = $wpdb;
        $this->mollie   = new Mollie_API_Client;
    }

    public function add_registration_form_type()
    {
        $labels = array(
            'name'                  => _x('Mollie Forms', 'Registration Forms General Name', 'mollie-forms'),
            'singular_name'         => _x('Mollie Form', 'Registration Form Singular Name', 'mollie-forms'),
            'menu_name'             => __('Mollie Forms', 'mollie-forms'),
            'name_admin_bar'        => __('Registration Form', 'mollie-forms'),
            'archives'              => __('Item Archives', 'mollie-forms'),
            'parent_item_colon'     => __('Parent Item:', 'mollie-forms'),
            'all_items'             => __('All Forms', 'mollie-forms'),
            'add_new_item'          => __('Add New Form', 'mollie-forms'),
            'add_new'               => __('Add New', 'mollie-forms'),
            'new_item'              => __('New Form', 'mollie-forms'),
            'edit_item'             => __('Edit Form', 'mollie-forms'),
            'update_item'           => __('Update Form', 'mollie-forms'),
            'view_item'             => __('View Form', 'mollie-forms'),
            'search_items'          => __('Search Form', 'mollie-forms'),
            'not_found'             => __('Not found', 'mollie-forms'),
            'not_found_in_trash'    => __('Not found in Trash', 'mollie-forms'),
            'featured_image'        => __('Featured Image', 'mollie-forms'),
            'set_featured_image'    => __('Set featured image', 'mollie-forms'),
            'remove_featured_image' => __('Remove featured image', 'mollie-forms'),
            'use_featured_image'    => __('Use as featured image', 'mollie-forms'),
            'insert_into_item'      => __('Insert into form', 'mollie-forms'),
            'uploaded_to_this_item' => __('Uploaded to this form', 'mollie-forms'),
            'items_list'            => __('Forms list', 'mollie-forms'),
            'items_list_navigation' => __('Forms list navigation', 'mollie-forms'),
            'filter_items_list'     => __('Filter forms list', 'mollie-forms'),
        );
        $args = array(
            'label'                 => __('Registration Form', 'mollie-forms'),
            'description'           => __('Registration Form Description', 'mollie-forms'),
            'labels'                => $labels,
            'supports'              => array(),
            'taxonomies'            => array(),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => true,
            'rewrite'               => false,
            'menu_icon'             => 'dashicons-list-view',
        );
        register_post_type('rfmp', $args);
    }

    public function add_rfmp_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => ''
        ), $atts);
        $post = get_post($atts['id']);

        if (!$post->ID)
            return __('Form not found', 'mollie-forms');

        $output = '<form method="post" data-rfmp-version="' . RFMP_VERSION . '">';

        $output .= wp_nonce_field(basename(__FILE__), 'rfmp_form_' . $post->ID . '_nonce', true, false);
        $output .= '<input type="hidden" name="rfmp-post" value="' . $post->ID . '">';

        $fields_type = get_post_meta($post->ID, '_rfmp_fields_type', true);

        // POST request and check required fields
        if ($this->check_required($post->ID) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rfmp-post']) && $_POST['rfmp-post'] == $post->ID)
            $this->do_post($post->ID);

        // Message after payment
        if (isset($_GET['payment']))
        {
            $class_success      = get_post_meta($post->ID, '_rfmp_class_success', true);
            $class_error        = get_post_meta($post->ID, '_rfmp_class_error', true);
            $message_success    = get_post_meta($post->ID, '_rfmp_msg_success', true);
            $message_error      = get_post_meta($post->ID, '_rfmp_msg_error', true);
            $after_payment      = get_post_meta($post->ID, '_rfmp_after_payment', true);
            $redirect_success   = get_post_meta($post->ID, '_rfmp_redirect_success', true);
            $redirect_error     = get_post_meta($post->ID, '_rfmp_redirect_error', true);

            $payment        = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_PAYMENTS . " WHERE rfmp_id='" . esc_sql($_GET['payment']) . "'");
            $registration   = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id='" . esc_sql($payment->registration_id) . "'");
            if ($payment == null || $registration == null)
                return '<p class="' . esc_attr($class_error) . '">' . esc_html__('No payment found', 'mollie-forms') . '</p>';
            elseif ($registration->post_id == $post->ID)
            {
                if ($payment->payment_status == 'paid')
                {
                    if ($after_payment == 'redirect')
                        wp_redirect($redirect_success);
                    else
                        return '<p class="' . esc_attr($class_success) . '">' . esc_html($message_success) . '</p>';
                }
                elseif($payment->payment_status != 'open')
                {
                    if ($after_payment == 'redirect')
                        wp_redirect($redirect_error);
                    else
                        $output .= '<p class="' . esc_attr($class_error) . '">' . esc_html($message_error) . '</p>';
                }
            }
        }

        // Display form errors
        $output .= $this->required_errors;

        // Form fields
        foreach ($fields_type as $key => $type)
        {
            $output .= '<p>';
            $output .= $this->field_form($post->ID, $key, $type);
            $output .= '</p>';
        }

        $output .= '</form>';

        return $output;
    }

    public function add_rfmp_total_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => ''
        ), $atts);
        $post = get_post($atts['id']);

        if (!$post->ID)
            return __('Form not found', 'mollie-forms');

        $total = $this->wpdb->get_var("SELECT SUM(payments.amount) FROM " . RFMP_TABLE_PAYMENTS . " payments INNER JOIN " . RFMP_TABLE_REGISTRATIONS . " registrations ON payments.registration_id = registrations.id AND registrations.post_id='" . esc_sql($post->ID) . "' WHERE payments.payment_status='paid' AND payments.payment_mode='live'");

        return '€' . number_format($total, 2, ',', '');
    }

    public function add_rfmp_goal_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id'    => '',
            'goal'  => '',
            'text'  => __('Goal reached!', 'mollie-forms'),
        ), $atts);
        $post = get_post($atts['id']);
        $goal = $atts['goal'];

        if (!$post->ID)
            return __('Form not found', 'mollie-forms');

        if ($goal < 0)
            return __('Goal must be higher then 0', 'mollie-forms');

        $total = $this->wpdb->get_var("SELECT SUM(payments.amount) FROM " . RFMP_TABLE_PAYMENTS . " payments INNER JOIN " . RFMP_TABLE_REGISTRATIONS . " registrations ON payments.registration_id = registrations.id AND registrations.post_id='" . esc_sql($post->ID) . "' WHERE payments.payment_status='paid' AND payments.payment_mode='live'");

        $goal = (int) $goal - $total;

        if ($goal <= 0)
            return __($atts['text'], 'mollie-forms');

        return '€' . number_format($goal, 2, ',', '');
    }

    private function field_form($post, $key, $type)
    {
        $fields_label = get_post_meta($post, '_rfmp_fields_label', true);
        $fields_value = get_post_meta($post, '_rfmp_fields_value', true);
        $fields_class = get_post_meta($post, '_rfmp_fields_class', true);
        $fields_required = get_post_meta($post, '_rfmp_fields_required', true);

        $required = ($fields_required[$key] ? ' <span style="color:red;">*</span>' : '');

        $name = 'form_' . $post . '_field_' . $key;
        $form_value = isset($_POST[$name]) ? $_POST[$name] : (isset($_GET[$name]) ? $_GET[$name] : '');
        switch ($type)
        {
            case 'text':
                $return = '<label>' . strip_tags($fields_label[$key], '<a>') . $required . '<br><input type="text" name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '" ' . ($fields_required[$key] ? 'required' : '') . ' value="' . esc_attr($form_value) . '" style="width: 100%;"></label>';
                break;
            case 'textarea':
                $return = '<label>' . strip_tags($fields_label[$key], '<a>') . $required . '<br><textarea name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '" ' . ($fields_required[$key] ? 'required' : '') . ' style="width: 100%;">' . esc_html($form_value) . '</textarea></label>';
                break;
            case 'name':
                $return = '<label>' . strip_tags($fields_label[$key], '<a>') . $required . '<br><input type="text" name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '" ' . ($fields_required[$key] ? 'required' : '') . ' value="' . esc_attr($form_value) . '" style="width: 100%;"></label>';
                break;
            case 'email':
                $return = '<label>' . strip_tags($fields_label[$key], '<a>') . $required . '<br><input type="email" name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '" ' . ($fields_required[$key] ? 'required' : '') . ' value="' . esc_attr($form_value) . '" style="width: 100%;"></label>';
                break;
            case 'date':
                $return = '<label>' . strip_tags($fields_label[$key], '<a>') . $required . '<br><input type="date" name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '" ' . ($fields_required[$key] ? 'required' : '') . ' value="' . esc_attr($form_value) . '" style="width: 100%;"></label>';
                break;
            case 'checkbox':
                $return = '<label><input type="checkbox" name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '" value="1" ' . ($fields_required[$key] ? 'required' : '') . ($form_value == '1' ? ' checked' : '') . '> ' . strip_tags($fields_label[$key], '<a>') . $required . '</label>';
                break;
            case 'dropdown':
                $values = explode('|', $fields_value[$key]);
                $options = '';
                foreach ($values as $value)
                    $options .= '<option' . ($form_value == $value ? ' selected' : '') . '>' . esc_html($value) . '</option>';

                $return = '<label>' . esc_html($fields_label[$key]) . $required . '<br><select name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '">' . $options . '</select></label>';
                break;
            case 'radio':
                $values = explode('|', $fields_value[$key]);
                $options = '<label>' . strip_tags($fields_label[$key], '<a>') . $required . '</label><br>';
                foreach ($values as $value)
                    $options .= '<label><input type="radio" name="' . $name . '" class="' . esc_attr($fields_class[$key]) . '" value="' . esc_attr($value) . '" ' . ($fields_required[$key] ? 'required' : '') . ($form_value == esc_attr($value) ? ' checked' : '') . '> ' . esc_html($value) . '</label><br>';

                $return = $options;
                break;
            case 'submit':
                $return = '<input type="submit" name="' . $name . '" value="' . esc_attr($fields_label[$key]) . '" class="' . esc_attr($fields_class[$key]) . '">';
                break;
            case 'payment_methods':
                $return = '<label>' . strip_tags($fields_label[$key], '<a>') . '<br>' . $this->payment_methods($post, $fields_class[$key]) . '</label>';
                break;
            case 'priceoptions':
                $return = '<label>' . strip_tags($fields_label[$key], '<a>') . '<br>' . $this->price_options($post, $fields_class[$key]) . '</label>';
                break;
        }

        return $return;
    }

    public function payment_methods($post, $class)
    {
        $api_key    = get_post_meta($post, '_rfmp_api_key', true);
        $active     = get_post_meta($post, '_rfmp_payment_method', true);
        $fixed      = get_post_meta($post, '_rfmp_payment_method_fixed', true);
        $variable   = get_post_meta($post, '_rfmp_payment_method_variable', true);
        $display    = get_post_meta($post, '_rfmp_payment_methods_display', true);
        $form_value = isset($_POST['rfmp_payment_method']) ? $_POST['rfmp_payment_method'] : '';

        try {
            $this->mollie->setApiKey($api_key);

            $script = '';
            $rcur = array();
            foreach ($this->mollie->methods->all(0,0,array('recurringType' => 'first')) as $method)
            {
                if ($active[$method->id])
                {
                    $rcur[] = $method->id;
                    $script .= 'document.getElementById("rfmp_pm_' . $method->id . '_' . $post . '").style.display = "block";' . "\n";
                }
            }
            foreach ($this->mollie->methods->all(0,0) as $method)
            {
                if ($active[$method->id] && !in_array($method->id, $rcur))
                    $script .= 'document.getElementById("rfmp_pm_' . $method->id . '_' . $post . '").style.display = (frequency!="once" ? "none" : "block");' . "\n";
            }

            $methods = '
            <script>
            window.onload = setTimeout(rfmp_recurring_methods_' . $post . ', 100);
            function rfmp_recurring_methods_' . $post . '() {
                var priceoptions = document.getElementsByName("rfmp_priceoptions_' . $post . '");
                if (priceoptions[0].tagName == "INPUT")
                {
                    for (var i = 0, length = priceoptions.length; i < length; i++) {
                        if (priceoptions[i].checked) {
                            var frequency = priceoptions[i].dataset.frequency;
                            var pricetype = priceoptions[i].dataset.pricetype;
                            var freq = priceoptions[i].dataset.freq;
                            break;
                        }
                    }
                } else {
                    var frequency = priceoptions[0].options[priceoptions[0].selectedIndex].dataset.frequency;
                    var pricetype = priceoptions[0].options[priceoptions[0].selectedIndex].dataset.pricetype;
                    var freq = priceoptions[0].options[priceoptions[0].selectedIndex].dataset.freq;
                }
                                   
                document.getElementById("rfmp_checkbox_' . $post . '").style.display = (frequency=="once" ? "none" : "block");
                document.getElementById("rfmp_checkbox_hidden_' . $post . '").value = (frequency=="once" ? 0 : 1);
                document.getElementById("rfmp_open_amount_' . $post . '").style.display = (pricetype=="open" ? "block" : "none");
                document.getElementById("rfmp_open_amount_required_' . $post . '").value = (pricetype=="open" ? 1 : 0);
                document.getElementById("rfmp_amount_freq_' . $post . '").innerHTML = freq;
                ' . $script . '
            }
            </script>';

            if ($display != 'dropdown')
            {
                $first = true;
                $methods .= '<ul class="' . esc_attr($class) . '" style="list-style-type:none;margin:0;">';
                foreach ($this->mollie->methods->all(0,0, array('locale' => get_locale(), 'recurringType' => null)) as $method)
                {
                    if ($active[$method->id])
                    {
                        $subcharge = array();
                        if (isset($fixed[$method->id]) && $fixed[$method->id])
                            $subcharge[] = '&euro; ' . str_replace(',','.',$fixed[$method->id]);

                        if (isset($variable[$method->id]) && $variable[$method->id])
                            $subcharge[] = str_replace(',','.',$variable[$method->id]) . '%';

                        if ($display == 'list')
                        {
                            $methods .= '<li id="rfmp_pm_' . esc_attr($method->id) . '_' . $post . '"><label><input type="radio" name="rfmp_payment_method_' . $post . '" value="' . esc_attr($method->id) . '"' . ($form_value == $method->id || $first ? ' checked' : '') . '> <img style="vertical-align:middle;display:inline-block;" src="' . esc_url($method->image->normal) . '"> ' . esc_html($method->description) . (!empty($subcharge) ? ' (+ ' . implode(' & ', $subcharge) . ')' : '') . '</label></li>';
                        }
                        elseif ($display == 'text')
                        {
                            $methods .= '<li id="rfmp_pm_' . esc_attr($method->id) . '_' . $post . '"><input type="radio" name="rfmp_payment_method_' . $post . '" value="' . esc_attr($method->id) . '"' . ($form_value == $method->id || $first ? ' checked' : '') . '> ' . esc_html($method->description) . (!empty($subcharge) ? ' (+ ' . implode(' & ', $subcharge) . ')' : '') . '</li>';
                        }
                        elseif ($display == 'icons')
                        {
                            $methods .= '<li id="rfmp_pm_' . esc_attr($method->id) . '_' . $post . '"><input type="radio" name="rfmp_payment_method_' . $post . '" value="' . esc_attr($method->id) . '"' . ($form_value == $method->id || $first ? ' checked' : '') . '> <img style="vertical-align:middle;display:inline-block;" src="' . esc_url($method->image->normal) . '"> ' . (!empty($subcharge) ? ' (+ ' . implode(' & ', $subcharge) . ')' : '') . '</li>';
                        }
                        $first = false;
                    }
                }
                $methods .= '</ul>';
            }
            else
            {
                $methods .= '<select name="rfmp_payment_method_' . $post . '" class="' . esc_attr($class) . '">';
                foreach ($this->mollie->methods->all(0,0, array('locale' => get_locale())) as $method)
                {
                    if ($active[$method->id])
                    {
                        $subcharge = array();
                        if (isset($fixed[$method->id]) && $fixed[$method->id])
                            $subcharge[] = '&euro; ' . str_replace(',','.',$fixed[$method->id]);

                        if (isset($variable[$method->id]) && $variable[$method->id])
                            $subcharge[] = str_replace(',','.',$variable[$method->id]) . '%';

                        $methods .= '<option id="rfmp_pm_' . esc_attr($method->id) . '_' . $post . '" value="' . esc_attr($method->id) . '"' . ($form_value == $method->id ? ' selected' : '') . '>' . esc_html($method->description) . (!empty($subcharge) ? ' (+ ' . implode(' & ', $subcharge) . ')' : '') . '</option>';
                    }
                }
                $methods .= '</select>';
            }

            $methods .= '<input type="hidden" id="rfmp_checkbox_hidden_' . $post . '" name="rfmp_checkbox_hidden_' . $post . '" value="0">';
            $methods .= '<br><label id="rfmp_checkbox_' . $post . '" style="display:none;"><input type="checkbox" name="rfmp_checkbox_' . $post . '" value="1"> ' . __('I hereby give authorization to collect the amount shown above from my account periodically.', 'mollie-forms') . '</label>';

        } catch (Mollie_API_Exception $e) {
            $methods = '<p style="color: red">' . $e->getMessage() . '</p>';
        }

        return $methods;
    }

    private function price_options($post, $class)
    {
        $option_desc        = get_post_meta($post, '_rfmp_priceoption_desc', true);
        $option_price       = get_post_meta($post, '_rfmp_priceoption_price', true);
        $option_pricetype   = get_post_meta($post, '_rfmp_priceoption_pricetype', true);
        $option_shipping    = get_post_meta($post, '_rfmp_priceoption_shipping', true);
        $option_frequency   = get_post_meta($post, '_rfmp_priceoption_frequency', true);
        $option_frequencyval= get_post_meta($post, '_rfmp_priceoption_frequencyval', true);
        $option_times       = get_post_meta($post, '_rfmp_priceoption_times', true);
        $option_display     = get_post_meta($post, '_rfmp_priceoptions_display', true);
        $form_value         = isset($_POST['rfmp_priceoptions_' . $post ]) ? $_POST['rfmp_priceoptions_' . $post] : (isset($_GET['form_' . $post . '_priceoption']) ? $_GET['form_' . $post . '_priceoption'] : '');

        $priceoptions = '';
        $first = true;
        if ($option_display == 'list')
        {
            $priceoptions .= '<ul class="' . esc_attr($class) . '" style="list-style-type:none;margin:0;">';
            foreach ($option_desc as $key => $desc)
            {
                $frequency = $option_frequency[$key] != 'once' ? $option_frequencyval[$key] . ' ' . $option_frequency[$key] : 'once';
                if ($option_pricetype[$key] != 'open')
                    $price = '&euro;' . number_format($option_price[$key], 2, ',', '') . ' ' . $this->frequency_label($frequency);
                else
                    $price = $this->frequency_label($frequency);

                if (trim($option_shipping[$key]))
                {
                    $price .= ' + &euro; ' . number_format($option_shipping[$key], 2, ',', '') . ' ' . esc_html__('Shipping costs', 'mollie-forms');
                }

                $times = $option_times[$key] > 0 ? '; ' . sprintf(esc_html__('Stops after %s times', 'mollie-forms'), $option_times[$key]) : '';
                $priceoptions .= '<li><label><input type="radio" onchange="rfmp_recurring_methods_' . $post . '();" data-frequency="' . esc_attr($option_frequency[$key]) . '" data-freq="' . $this->frequency_label($frequency) . '" data-pricetype="' . $option_pricetype[$key] . '" name="rfmp_priceoptions_' . $post . '" value="' . esc_attr($key) . '"' . ($form_value == $key || $first ? ' checked' : '') . '> ' . esc_html($desc) . (!empty($price) || !empty($times) ? ' (' . trim($price . $times) . ')' : '') . '</label></li>';
                $first = false;
            }
            $priceoptions .= '</ul>';
        }
        else
        {
            $priceoptions .= '<select name="rfmp_priceoptions_' . $post . '" onchange="rfmp_recurring_methods_' . $post . '();" class="' . esc_attr($class) . '">';
            foreach ($option_desc as $key => $desc)
            {
                $frequency = $option_frequency[$key] != 'once' ? $option_frequencyval[$key] . ' ' . $option_frequency[$key] : 'once';
                if ($option_pricetype[$key] != 'open')
                    $price = '&euro;' . number_format($option_price[$key], 2, ',', '') . ' ' . $this->frequency_label($frequency);
                else
                    $price = $this->frequency_label($frequency);

                if (trim($option_shipping[$key]))
                {
                    $price .= ' + &euro; ' . number_format($option_shipping[$key], 2, ',', '') . ' ' . esc_html__('Shipping costs', 'mollie-forms');
                }

                $times = $option_times[$key] > 0 ? '; ' . sprintf(esc_html__('Stops after %s times', 'mollie-forms'), $option_times[$key]) : '';
                $priceoptions .= '<option data-frequency="' . esc_attr($option_frequency[$key]) . '" data-freq="' . $this->frequency_label($frequency) . '" data-pricetype="' . $option_pricetype[$key] . '" value="' . esc_attr($key) . '"' . ($form_value == $key ? ' selected' : '') . '>' . esc_html($desc) . (!empty($price) || !empty($times) ? ' (' . trim($price . $times) . ')' : '') . '</option>';
            }
            $priceoptions .= '</select>';
        }

        $form_value_amount = isset($_POST['rfmp_amount_' . $post]) ? $_POST['rfmp_amount_' . $post] : (isset($_GET['form_' . $post . '_amount']) ? $_GET['form_' . $post . '_amount'] : '');

        $priceoptions .= '<p id="rfmp_open_amount_' . $post . '" style="display:none;"><label>' . esc_html__('Amount', 'mollie-forms') . ' <span style="color:red;">*</span><br><input type="text" value="' . esc_attr($form_value_amount) . '" name="rfmp_amount_' . $post . '"> <span id="rfmp_amount_freq_' . $post . '"></span></label><input type="hidden" name="rfmp_amount_required_' . $post . '" id="rfmp_open_amount_required_' . $post . '" value="0"></p>';


        return $priceoptions;
    }

    private function check_required($post)
    {
        $fields_label       = get_post_meta($post, '_rfmp_fields_label', true);
        $fields_value       = get_post_meta($post, '_rfmp_fields_value', true);
        $fields_required    = get_post_meta($post, '_rfmp_fields_required', true);

        $option             = isset($_POST['rfmp_priceoptions_' . $post]) ? $_POST['rfmp_priceoptions_' . $post] : false;
        $option_price       = get_post_meta($post, '_rfmp_priceoption_price', true);
        $option_pricetype   = get_post_meta($post, '_rfmp_priceoption_pricetype', true);

        $return = true;
        $this->required_errors = '';

        foreach ($fields_required as $key => $required)
        {
            $name = 'form_' . $post . '_field_' . $key;
            if (isset($_POST[$name]) && empty($_POST[$name]) && $required)
            {
                $return = false;
                $this->required_errors .= '<p class="rfmp_error" style="color:red;">- ' . sprintf(esc_html__('%s is a required field', 'mollie-forms'), $fields_label[$key]) . '</p>';
            }
        }

        if (isset($_POST['rfmp_checkbox_hidden_' . $post]) && $_POST['rfmp_checkbox_hidden_' . $post] == '1' && !isset($_POST['rfmp_checkbox_' . $post]))
        {
            $return = false;
            $this->required_errors .= '<p class="rfmp_error" style="color:red;">- ' . esc_html__('Please give us authorization to collect the amount from your account periodically.', 'mollie-forms') . '</p>';
        }

        $price  = isset($option_price[$option]) ? $option_price[$option] : 0.00;
        $type   = isset($option_pricetype[$option]) ? $option_pricetype[$option] : false;
        $minimum = (float) str_replace(',','.', $price);
        if (!$minimum)
            $minimum = 1;

        if ($option && $type == 'open' && $_POST['rfmp_amount_' . $post] < $minimum)
        {
            $return = false;
            $this->required_errors .= '<p class="rfmp_error" style="color:red;">- ' . esc_html__('Please fill in a higher amount.', 'mollie-forms') . ' ' . esc_html__('The minimum amount is:', 'mollie-forms') . ' &euro;' . number_format($minimum, 2, ',', '') . ' </p>';
        }

        return $return;
    }

    private function do_post($post)
    {
        if (!isset($_POST['rfmp_form_' . $post . '_nonce']) || !wp_verify_nonce($_POST['rfmp_form_' . $post . '_nonce'], basename(__FILE__)))
            return '';

        $api_key    = get_post_meta($post, '_rfmp_api_key', true);
        $webhook    = get_home_url(null, RFMP_WEBHOOK . $post);
        $redirect   = get_home_url(null, $_SERVER['REQUEST_URI']);
        $redirect  .= strstr($redirect, '?') ? '&' : '?';

        do_action('rfmp_form_submitted', $post, $_POST);

        try {

            if (!$api_key)
                echo '<p style="color: red">' . esc_html__('No API-key set', 'mollie-forms') . '</p>';
            else
            {
                $this->mollie->setApiKey($api_key);

                $rfmp_id = uniqid('rfmp-' . $post . '-');

                $option             = $_POST['rfmp_priceoptions_' . $post];
                $option_desc        = get_post_meta($post, '_rfmp_priceoption_desc', true);
                $option_price       = get_post_meta($post, '_rfmp_priceoption_price', true);
                $option_pricetype   = get_post_meta($post, '_rfmp_priceoption_pricetype', true);
                $option_shipping    = get_post_meta($post, '_rfmp_priceoption_shipping', true);
                $option_frequency   = get_post_meta($post, '_rfmp_priceoption_frequency', true);
                $option_frequencyval= get_post_meta($post, '_rfmp_priceoption_frequencyval', true);
                $option_times       = get_post_meta($post, '_rfmp_priceoption_times', true);

                $field_type         = get_post_meta($post, '_rfmp_fields_type', true);
                $field_label        = get_post_meta($post, '_rfmp_fields_label', true);

                $name_field         = array_search('name', $field_type);
                $email_field        = array_search('email', $field_type);
                $name_field_value   = trim($_POST['form_' . $post . '_field_' . $name_field]);
                $email_field_value  = trim($_POST['form_' . $post . '_field_' . $email_field]);

                $method             = $_POST['rfmp_payment_method_' . $post];
                $fixed              = get_post_meta($post, '_rfmp_payment_method_fixed', true);
                $variable           = get_post_meta($post, '_rfmp_payment_method_variable', true);

                if ($option_pricetype[$option] == 'open')
                    $price          = isset($_POST['rfmp_amount_' . $post]) ? (float) str_replace(',','.',$_POST['rfmp_amount_' . $post]) : 0;
                else
                    $price          = (float) str_replace(',','.',$option_price[$option]);

                if (trim($option_shipping[$option]))
                {
                    // Shipping costs
                    $price         += (float) str_replace(',','.',$option_shipping[$option]);
                }

                if ($option_frequency[$option] == 'once')
                    $option_frequencyval[$option] = '';

                $frequency          = trim($option_frequencyval[$option] . ' ' . $option_frequency[$option]);
                $times              = $option_times[$option] > 0 ? (int) $option_times[$option] : null; // number of times

                // Calculate total price
                if (isset($variable[$method]) && $variable[$method])
                {
                    // Add variable surcharge for payment method
                    $price *= (1 + str_replace(',','.',$variable[$method]) / 100);
                }
                if (isset($fixed[$method]) && $fixed[$method])
                {
                    // Add fixed surcharge for payment method
                    $price += str_replace(',','.',$fixed[$method]);
                }

                $total = number_format(str_replace(',','.',$price), 2, '.', '');

                // Create new customer at Mollie
                $customer = $this->mollie->customers->create(array(
                    'name'  => $name_field_value,
                    'email' => $email_field_value,
                ));

                // Add customer to database
                $this->wpdb->query($this->wpdb->prepare("INSERT INTO " . RFMP_TABLE_CUSTOMERS . "
                ( created_at, post_id, customer_id, name, email )
                VALUES ( NOW(), %d, %s, %s, %s )",
                    $post,
                    $customer->id,
                    $customer->name,
                    $customer->email
                ));
                $customer_id = $this->wpdb->insert_id;

                do_action('rfmp_customer_created', $post, $customer);

                // Payment description
                $search_desc    = array(
                    '{rfmp="id"}',
                    '{rfmp="amount"}',
                    '{rfmp="priceoption"}',
                    '{rfmp="form_title"}',
                );
                $replace_desc   = array(
                    $rfmp_id,
                    '€' . number_format($total, 2, ',', ''),
                    $option_desc[$option],
                    get_the_title($post),
                );

                // Add field values of registration
                foreach ($field_label as $key => $field)
                {
                    if ($field_type[$key] != 'submit')
                    {
                        $value = $_POST['form_' . $post . '_field_' . $key];
                        if ($field_type[$key] == 'payment_methods')
                            $value = $_POST['rfmp_payment_method_' . $post];
                        elseif ($field_type[$key] == 'priceoptions')
                            $value = $option_desc[$option];

                        $search_desc[]  = '{rfmp="' . trim($field) . '"}';
                        $replace_desc[] = $value;
                    }
                }

                $desc = get_post_meta($post, '_rfmp_payment_description', true);
                if (!$desc)
                    $desc = '{rfmp="priceoption"}';

                $desc = str_replace($search_desc, $replace_desc, $desc);

                // Create registration
                $this->wpdb->query($this->wpdb->prepare("INSERT INTO " . RFMP_TABLE_REGISTRATIONS . "
                    ( created_at, post_id, customer_id, subscription_id, total_price, price_frequency, number_of_times, description, subs_fix )
                    VALUES ( NOW(), %d, %s, NULL, %s, %s, %s, %s, 1 )",
                    $post,
                    $customer->id,
                    $total,
                    $frequency,
                    $times,
                    $desc
                ));
                $registration_id = $this->wpdb->insert_id;

                if (!$registration_id)
                {
                    $message_error = get_post_meta($post, '_rfmp_msg_error', true);
                    echo '<p style="color: red">' . esc_html($message_error) . '</p>';
                }
                else
                {
                    // Add field values of registration
                    foreach ($field_label as $key => $field)
                    {
                        if ($field_type[$key] != 'submit')
                        {
                            $value = $_POST['form_' . $post . '_field_' . $key];
                            if ($field_type[$key] == 'payment_methods')
                                $value = $_POST['rfmp_payment_method_' . $post];
                            elseif ($field_type[$key] == 'priceoptions')
                                $value = $option_desc[$option];

                            $this->wpdb->query($this->wpdb->prepare("INSERT INTO " . RFMP_TABLE_REGISTRATION_FIELDS . "
                    ( registration_id, field, `value`, `type` )
                    VALUES ( %d, %s, %s, %s )",
                                $registration_id,
                                $field,
                                $value,
                                $field_type[$key]
                            ));
                        }
                    }

                    // Check frequency
                    if ($option_frequency[$option] == 'once')
                    {
                        // Single payment
                        $payment = $this->mollie->payments->create(array(
                            'amount'            => $total,
                            'description'       => $desc,
                            'method'            => $method,
                            'redirectUrl'       => $redirect . 'payment=' . $rfmp_id,
                            'webhookUrl'        => $webhook,
                            'customerId'        => $customer->id,
                            'metadata'          => array(
                                'rfmp_id'   => $rfmp_id,
                                'name'      => $customer->name,
                                'email'     => $customer->email,
                                'priceoption'=> $option_desc[$option]
                            )
                        ));
                    }
                    else
                    {
                        // Recurring payment, subscription
                        $payment = $this->mollie->payments->create(array(
                            'amount'            => $total,
                            'description'       => $desc,
                            'method'            => $method,
                            'redirectUrl'       => $redirect . 'payment=' . $rfmp_id,
                            'webhookUrl'        => $webhook . '&first=' . $registration_id,
                            'customerId'        => $customer->id,
                            'recurringType'     => 'first',
                            'metadata'          => array(
                                'rfmp_id'   => $rfmp_id,
                                'name'      => $customer->name,
                                'email'     => $customer->email,
                                'priceoption'=> $option_desc[$option]
                            )
                        ));
                    }

                    do_action('rfmp_payment_created', $post, $payment);

                    // Create payment for registration
                    $this->wpdb->query($this->wpdb->prepare("INSERT INTO " . RFMP_TABLE_PAYMENTS . "
                    ( created_at, registration_id, payment_id, payment_method, payment_mode, payment_status, amount, rfmp_id )
                    VALUES ( NOW(), %d, %s, %s, %s, %s, %s, %s )",
                        $registration_id,
                        $payment->id,
                        $payment->method,
                        $payment->mode,
                        $payment->status,
                        $payment->amount,
                        $rfmp_id
                    ));

                    return wp_redirect($payment->getPaymentUrl());
                }
            }


        } catch (Mollie_API_Exception $e) {
            echo '<p style="color: red">' . $e->getMessage() . '</p>';

            if (isset($registration_id))
            {
                // an error occurred, delete registration
                $this->wpdb->query($this->wpdb->prepare("DELETE FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id = %d",
                    $registration_id
                ));
            }
            if (isset($customer_id))
            {
                // an error occurred, delete customer
                if (isset($customer) && isset($customer->id))
                    $this->mollie->customers->delete($customer->id);

                $this->wpdb->query($this->wpdb->prepare("DELETE FROM " . RFMP_TABLE_CUSTOMERS . " WHERE id = %d",
                    $customer_id
                ));
            }
        }
    }

    private function frequency_label($frequency)
    {
        $words = array(
            'days',
            'weeks',
            'months',
        );
        $translations = array(
            __('days', 'mollie-forms'),
            __('weeks', 'mollie-forms'),
            __('months', 'mollie-forms'),
        );

        $frequency = trim($frequency);
        switch ($frequency)
        {
            case 'once':
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
                $return = __('each', 'mollie-forms') . ' ' . str_replace($words, $translations, $frequency);
        }

        return $return;
    }

}