<?php

Class RFMP_Admin {

    private $mollie, $wpdb;

    function __construct()
    {
        global $wpdb;

        add_action('init', array($this, 'init_plugin'));
        add_action('add_meta_boxes_rfmp', array($this, 'add_meta_boxes'));
        add_action('save_post_rfmp', array($this, 'save_meta_boxes'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'load_scripts'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_rfmp_export', array($this, 'export_registrations'));

        add_filter('post_row_actions', array($this, 'post_actions'), 10, 2);
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);

        $this->mollie   = new Mollie_API_Client;
        $this->wpdb     = $wpdb;
    }

    public function init_plugin()
    {
        remove_post_type_support('rfmp', 'editor');
    }


    function plugin_row_meta($links, $file)
    {
        if (RFMP_PLUGIN_BASE == $file)
        {
            $row_meta = array(
                'support'    => '<a href="http://support.wobbie.nl" target="_blank">' . esc_html__('Support', 'mollie-forms') . '</a>',
                'add-ons'    => '<a href="edit.php?post_type=rfmp&page=add-ons">' . esc_html__('Add-ons', 'mollie-forms') . '</a>',
            );

            return array_merge($links, $row_meta);
        }
        return (array) $links;
    }

    public function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=rfmp',
            __('Registrations', 'mollie-forms'),
            __('Registrations', 'mollie-forms'),
            'edit_posts',
            'registrations',
            array(
                $this,
                'page_registrations'
            )
        );
        add_submenu_page(
            null,
            __('Registration', 'mollie-forms'),
            __('Registration', 'mollie-forms'),
            'edit_posts',
            'registration',
            array(
                $this,
                'page_registration'
            )
        );


        // Add-ons
        add_submenu_page(
            'edit.php?post_type=rfmp',
            __('Add-ons', 'mollie-forms'),
            __('Add-ons', 'mollie-forms'),
            'administrator',
            'add-ons',
            array(
                $this,
                'page_addons'
            )
        );

        global $submenu;
        $submenu['edit.php?post_type=rfmp'][] = array('Support', 'manage_options', 'http://support.wobbie.nl');
    }

    public function page_addons()
    {
        ?>
        <style>
            .products li {
                float: left;
                margin: 0 1em 1em 0!important;
                padding: 0;
                vertical-align: top;
                width: 300px;
            }
            .products li a {
                text-decoration: none;
                color: inherit;
                border: 1px solid #ddd;
                display: block;
                min-height: 220px;
                overflow: hidden;
                background: #f5f5f5;
                -webkit-box-shadow: inset 0 1px 0 rgba(255,255,255,.2), inset 0 -1px 0 rgba(0,0,0,.1);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.2), inset 0 -1px 0 rgba(0,0,0,.1);
            }
            .products li a h2, .products li a h3 {
                margin: 0!important;
                padding: 20px!important;
                background: #fff;
            }
            .products li a p {
                padding: 20px!important;
                margin: 0!important;
                border-top: 1px solid #f1f1f1;
            }
        </style>

        <div class="wrap">
            <h2><?php _e('Add-ons', 'mollie-forms');?></h2>

            <ul class="products">
                <li class="product">
                    <a href="https://wobbie.nl/downloads/mailchimp-for-mollie-forms/" target="_blank">
                        <h2><?php _e('Mailchimp', 'mollie-forms');?></h2>
                        <p><?php _e('Add people to your Mailchimp mailing list.', 'mollie-forms');?></p>
                    </a>
                </li>
            </ul>
        </div>

        <?php
    }

    public function add_meta_boxes($post)
    {
        add_meta_box('rfmp_meta_box_fields', __('Fields', 'mollie-forms'), array($this, 'build_meta_boxes_fields'), 'rfmp', 'normal', 'high');
        add_meta_box('rfmp_meta_box_settings', __('Settings', 'mollie-forms'), array($this, 'build_meta_boxes_settings'), 'rfmp', 'normal', 'default');
        add_meta_box('rfmp_meta_box_priceoptions', __('Price options', 'mollie-forms'), array($this, 'build_meta_boxes_priceoptions'), 'rfmp', 'normal', 'default');
        add_meta_box('rfmp_meta_box_emails', __('Email settings', 'mollie-forms'), array($this, 'build_meta_boxes_emails'), 'rfmp', 'normal', 'default');
        add_meta_box('rfmp_meta_box_paymentmethods', __('Payment methods', 'mollie-forms'), array($this, 'build_meta_boxes_paymentmethods'), 'rfmp', 'side', 'default');
    }

    public function build_meta_boxes_fields($post)
    {
        wp_nonce_field(basename(__FILE__), 'rfmp_meta_box_fields_nonce');
        $field_type     = get_post_meta($post->ID, '_rfmp_fields_type', true);
        $field_label    = get_post_meta($post->ID, '_rfmp_fields_label', true);
        $field_value    = get_post_meta($post->ID, '_rfmp_fields_value', true);
        $field_class    = get_post_meta($post->ID, '_rfmp_fields_class', true);
        $field_required = get_post_meta($post->ID, '_rfmp_fields_required', true);

        if (empty($field_type))
        {
            $field_type = array(0 => 'name', 1 => 'email', 2 => 'priceoptions', 3 => 'payment_methods', 4 => 'submit');
            $field_label = array(0 => __('Name', 'mollie-forms'), 1 => __('Email', 'mollie-forms'), 2 => '', 3 => __('Payment method', 'mollie-forms'), 4 => __('Submit', 'mollie-forms'));
        }
        ?>
        <script id="rfmp_template_field" type="text/template">
            <tr>
                <td class="sort"></td>
                <td>
                    <select name="rfmp_fields_type[]" class="rfmp_type">
                        <option value="text"><?php esc_html_e('Text field', 'mollie-forms');?></option>
                        <option value="textarea"><?php esc_html_e('Text area', 'mollie-forms');?></option>
                        <option value="dropdown"><?php esc_html_e('Dropdown', 'mollie-forms');?></option>
                        <option value="checkbox"><?php esc_html_e('Checkbox', 'mollie-forms');?></option>
                        <option value="radio"><?php esc_html_e('Radio buttons', 'mollie-forms');?></option>
                        <option value="date"><?php esc_html_e('Date', 'mollie-forms');?></option>
                    </select>
                </td>
                <td><input type="text" name="rfmp_fields_label[]" style="width:100%"></td>
                <td><input style="display:none;width:100%" class="rfmp_value" type="text" name="rfmp_fields_value[]" placeholder="value1|value2|value3"></td>
                <td><input type="text" name="rfmp_fields_class[]" style="width:100%"></td>
                <td><input type="hidden" name="rfmp_fields_required[]" value="0"><input type="checkbox" name="rfmp_fields_required[]" value="1"></td>
                <td width="1%"><a href="#" class="delete"><?php esc_html_e('Delete', 'mollie-forms');?></a></td>
            </tr>
        </script>

        <div class='inside'>
            <table class="widefat rfmp_table" id="rfmp_fields">
                <thead>
                    <tr>
                        <th class="sort"></th>
                        <th><?php esc_html_e('Type', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Label', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Values', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Class', 'mollie-forms');?></th>
                        <th width="50"><?php esc_html_e('Required', 'mollie-forms');?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($field_type as $key => $type) { ?>
                        <?php if ($type == 'priceoptions') { ?>
                            <tr>
                                <td class="sort"></td>
                                <td><?php esc_html_e('Price options', 'mollie-forms');?><input type="hidden" name="rfmp_fields_type[]" value="priceoptions"></td>
                                <td><input type="text" name="rfmp_fields_label[]" value="<?php echo esc_attr($field_label[$key]);?>" style="width:100%"></td>
                                <td><input type="hidden" name="rfmp_fields_value[]" value=""></td>
                                <td><input type="text" name="rfmp_fields_class[]" value="<?php echo esc_attr($field_class[$key]);?>" style="width:100%"></td>
                                <td><input type="checkbox" name="rfmp_fields_required[]" value="1" disabled checked><input type="hidden" name="rfmp_fields_required[]" value="1"></td>
                                <td width="1%"></td>
                            </tr>
                        <?php } elseif ($type == 'submit') { ?>
                            <tr>
                                <td class="sort"></td>
                                <td><?php esc_html_e('Submit button', 'mollie-forms');?><input type="hidden" name="rfmp_fields_type[]" value="submit"></td>
                                <td><input type="text" name="rfmp_fields_label[]" value="<?php echo esc_attr($field_label[$key]);?>" style="width:100%"></td>
                                <td><input type="hidden" name="rfmp_fields_value[]" value=""></td>
                                <td><input type="text" name="rfmp_fields_class[]" value="<?php echo esc_attr($field_class[$key]);?>" style="width:100%"></td>
                                <td><input type="checkbox" name="rfmp_fields_required[]" value="1" disabled checked><input type="hidden" name="rfmp_fields_required[]" value="1"></td>
                                <td width="1%"></td>
                            </tr>
                        <?php } elseif ($type == 'payment_methods') { ?>
                            <tr>
                                <td class="sort"></td>
                                <td><?php esc_html_e('Payment methods', 'mollie-forms');?><input type="hidden" name="rfmp_fields_type[]" value="payment_methods"></td>
                                <td><input type="text" name="rfmp_fields_label[]" value="<?php echo esc_attr($field_label[$key]);?>" style="width:100%"></td>
                                <td><input type="hidden" name="rfmp_fields_value[]" value=""></td>
                                <td><input type="text" name="rfmp_fields_class[]" value="<?php echo esc_attr($field_class[$key]);?>" style="width:100%"></td>
                                <td><input type="checkbox" name="rfmp_fields_required[]" value="1" disabled checked><input type="hidden" name="rfmp_fields_required[]" value="1"></td>
                                <td width="1%"></td>
                            </tr>
                        <?php } elseif ($type == 'name') { ?>
                            <tr>
                                <td class="sort"></td>
                                <td><?php esc_html_e('Name', 'mollie-forms');?><input type="hidden" name="rfmp_fields_type[]" value="name"></td>
                                <td><input type="text" name="rfmp_fields_label[]" value="<?php echo esc_attr($field_label[$key]);?>" style="width:100%"></td>
                                <td><input type="hidden" name="rfmp_fields_value[]" value=""></td>
                                <td><input type="text" name="rfmp_fields_class[]" value="<?php echo esc_attr($field_class[$key]);?>" style="width:100%"></td>
                                <td><input type="checkbox" name="rfmp_fields_required[]" value="1" disabled checked><input type="hidden" name="rfmp_fields_required[]" value="1"></td>
                                <td width="1%"></td>
                            </tr>
                        <?php } elseif ($type == 'email') { ?>
                            <tr>
                                <td class="sort"></td>
                                <td><?php esc_html_e('Email address', 'mollie-forms');?><input type="hidden" name="rfmp_fields_type[]" value="email"></td>
                                <td><input type="text" name="rfmp_fields_label[]" value="<?php echo esc_attr($field_label[$key]);?>" style="width:100%"></td>
                                <td><input type="hidden" name="rfmp_fields_value[]" value=""></td>
                                <td><input type="text" name="rfmp_fields_class[]" value="<?php echo esc_attr($field_class[$key]);?>" style="width:100%"></td>
                                <td><input type="checkbox" name="rfmp_fields_required[]" value="1" disabled checked><input type="hidden" name="rfmp_fields_required[]" value="1"></td>
                                <td width="1%"></td>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <td class="sort"></td>
                                <td>
                                    <select name="rfmp_fields_type[]" class="rfmp_type">
                                        <option value="text"><?php esc_html_e('Text field', 'mollie-forms');?></option>
                                        <option value="textarea"<?php echo ($type == 'textarea' ? ' selected' : '');?>><?php esc_html_e('Text area', 'mollie-forms');?></option>
                                        <option value="checkbox"<?php echo ($type == 'checkbox' ? ' selected' : '');?>><?php esc_html_e('Checkbox', 'mollie-forms');?></option>
                                        <option value="dropdown"<?php echo ($type == 'dropdown' ? ' selected' : '');?>><?php esc_html_e('Dropdown', 'mollie-forms');?></option>
                                        <option value="radio"<?php echo ($type == 'radio' ? ' selected' : '');?>><?php esc_html_e('Radio buttons', 'mollie-forms');?></option>
                                        <option value="date"<?php echo ($type == 'date' ? ' selected' : '');?>><?php esc_html_e('Date', 'mollie-forms');?></option>
                                    </select>
                                </td>
                                <td><input type="text" name="rfmp_fields_label[]" value="<?php echo esc_attr($field_label[$key]);?>" style="width:100%"></td>
                                <td><input style="<?php echo ($type != 'dropdown' && $type != 'radio' ? 'display:none;' : '');?>width:100%;" class="rfmp_value" type="text" name="rfmp_fields_value[]" value="<?php echo esc_attr($field_value[$key]);?>" placeholder="value1|value2|value3"></td>
                                <td><input type="text" name="rfmp_fields_class[]" value="<?php echo esc_attr($field_class[$key]);?>" style="width:100%"></td>
                                <td><input type="hidden" name="rfmp_fields_required[]" value="0"><input type="checkbox" value="1" name="rfmp_fields_required[<?php echo $key;?>]"<?php echo (isset($field_required[$key]) && $field_required[$key] ? ' checked' : '');?>></td>
                                <td width="1%"><a href="#" class="delete"><?php esc_html_e('Delete', 'mollie-forms');?></a></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="7"><input type="button" id="rfmp_add_field" class="button" value="<?php esc_html_e('Add new field', 'mollie-forms');?>"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    public function build_meta_boxes_priceoptions($post)
    {
        wp_nonce_field(basename(__FILE__), 'rfmp_meta_box_priceoptions_nonce');
        $option_desc        = get_post_meta($post->ID, '_rfmp_priceoption_desc', true);
        $option_price       = get_post_meta($post->ID, '_rfmp_priceoption_price', true);
        $option_pricetype   = get_post_meta($post->ID, '_rfmp_priceoption_pricetype', true);
        $option_shipping    = get_post_meta($post->ID, '_rfmp_priceoption_shipping', true);
        $option_frequency   = get_post_meta($post->ID, '_rfmp_priceoption_frequency', true);
        $option_frequencyval= get_post_meta($post->ID, '_rfmp_priceoption_frequencyval', true);
        $option_times       = get_post_meta($post->ID, '_rfmp_priceoption_times', true);
        ?>
        <script id="rfmp_template_priceoption" type="text/template">
            <tr>
                <td class="sort"></td>
                <td><input type="text" name="rfmp_priceoptions_desc[]" style="width:100%;"></td>
                <td>
                    <select name="rfmp_priceoptions_pricetype[]" class="rfmp_pricetype">
                        <option value="fixed"><?php esc_html_e('Fixed', 'mollie-forms');?></option>
                        <option value="open"><?php esc_html_e('Open', 'mollie-forms');?></option>
                    </select>
                    <input type="number" min="0.50" step="any" placeholder="<?php _e('Amount', 'mollie-forms');?>" data-ph-fixed="<?php _e('Amount', 'mollie-forms');?>" data-ph-open="<?php _e('Minimum amount', 'mollie-forms');?>" name="rfmp_priceoptions_price[]">
                </td>
                <td>
                    <input type="number" min="0.50" step="any" name="rfmp_priceoptions_shipping[]">
                </td>
                <td>
                    <input type="number" name="rfmp_priceoptions_frequencyval[]" style="width:50px;display:none;">
                    <select name="rfmp_priceoptions_frequency[]" class="rfmp_frequency">
                        <option value="once"><?php esc_html_e('Once', 'mollie-forms');?></option>
                        <option value="months"><?php esc_html_e('Months', 'mollie-forms');?></option>
                        <option value="weeks"><?php esc_html_e('Weeks', 'mollie-forms');?></option>
                        <option value="days"><?php esc_html_e('Days', 'mollie-forms');?></option>
                    </select>
                </td>
                <td>
                    <input type="number" name="rfmp_priceoptions_times[]" style="width: 50px;display:none;">
                </td>
                <td width="1%"><a href="javascript: void(0);" class="delete"><?php esc_html_e('Delete', 'mollie-forms');?></a></td>
            </tr>
        </script>

        <div class='inside'>
            <table class="widefat rfmp_table" id="rfmp_priceoptions">
                <thead>
                    <tr>
                        <th class="sort"></th>
                        <th><?php esc_html_e('Description', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Price', 'mollie-forms');?> &euro;</th>
                        <th><?php esc_html_e('Shipping costs', 'mollie-forms');?> &euro;</th>
                        <th><?php esc_html_e('Frequency', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Number of times', 'mollie-forms');?> <a href="#" style="cursor: help;" title="<?php esc_html_e('The number of times including the first payment. Leave empty or set to 0 for an on-going subscription', 'mollie-forms');?>">?</a></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($option_desc as $key => $desc) { ?>
                        <tr>
                            <td class="sort"></td>
                            <td><input type="text" required style="width:100%;" name="rfmp_priceoptions_desc[]" value="<?php echo esc_attr($desc);?>"></td>
                            <td>
                                <select name="rfmp_priceoptions_pricetype[]" class="rfmp_pricetype">
                                    <option value="fixed"><?php esc_html_e('Fixed', 'mollie-forms');?></option>
                                    <option value="open"<?php echo ($option_pricetype[$key] == 'open' ? ' selected' : '');?>><?php esc_html_e('Open', 'mollie-forms');?></option>
                                </select>
                                <input type="number" min="0.50" step="any" name="rfmp_priceoptions_price[]" value="<?php echo esc_attr($option_price[$key]);?>" placeholder="<?php echo ($option_pricetype[$key] == 'open' ? _e('Minimum amount', 'mollie-forms') : _e('Amount', 'mollie-forms'));?>">
                            </td>
                            <td>
                                <input type="number" min="0.50" step="any" name="rfmp_priceoptions_shipping[]" value="<?php echo esc_attr($option_shipping[$key]);?>">
                            </td>
                            <td>
                                <input type="number" name="rfmp_priceoptions_frequencyval[]" value="<?php echo esc_attr($option_frequencyval[$key]);?>" style="width:50px;<?php echo ($option_frequency[$key] == 'once' ? 'display:none;' : '');?>">
                                <select name="rfmp_priceoptions_frequency[]" class="rfmp_frequency">
                                    <option value="once"><?php esc_html_e('Once', 'mollie-forms');?></option>
                                    <option value="months"<?php echo ($option_frequency[$key] == 'months' ? ' selected' : '');?>><?php esc_html_e('Months', 'mollie-forms');?></option>
                                    <option value="weeks"<?php echo ($option_frequency[$key] == 'weeks' ? ' selected' : '');?>><?php esc_html_e('Weeks', 'mollie-forms');?></option>
                                    <option value="days"<?php echo ($option_frequency[$key] == 'days' ? ' selected' : '');?>><?php esc_html_e('Days', 'mollie-forms');?></option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="rfmp_priceoptions_times[]" value="<?php echo esc_attr($option_times[$key]);?>" style="width: 50px;<?php echo ($option_frequency[$key] == 'once' ? 'display:none;' : '');?>">
                            </td>
                            <td width="1%"><a href="javascript: void(0);" class="delete"><?php esc_html_e('Delete', 'mollie-forms');?></a></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="7"><input type="button" id="rfmp_add_priceoption" class="button" value="<?php esc_html_e('Add new price option', 'mollie-forms');?>"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    public function build_meta_boxes_settings($post)
    {
        wp_nonce_field(basename(__FILE__), 'rfmp_meta_box_settings_nonce');
        $api_key            = get_post_meta($post->ID, '_rfmp_api_key', true);
        $display_pm         = get_post_meta($post->ID, '_rfmp_payment_methods_display', true);
        $display_po         = get_post_meta($post->ID, '_rfmp_priceoptions_display', true);
        $class_success      = get_post_meta($post->ID, '_rfmp_class_success', true);
        $class_error        = get_post_meta($post->ID, '_rfmp_class_error', true);
        $payment_description= get_post_meta($post->ID, '_rfmp_payment_description', true);
        $after_payment      = get_post_meta($post->ID, '_rfmp_after_payment', true);
        $message_success    = get_post_meta($post->ID, '_rfmp_msg_success', true);
        $message_error      = get_post_meta($post->ID, '_rfmp_msg_error', true);
        $redirect_success   = get_post_meta($post->ID, '_rfmp_redirect_success', true);
        $redirect_error     = get_post_meta($post->ID, '_rfmp_redirect_error', true);
        ?>
        <div class='inside'>
            <table class="form-table">
                <tbody>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="rfmp_shortcode"><?php esc_html_e('Shortcode', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input id="rfmp_shortcode" value='[rfmp id="<?php echo esc_attr($post->ID);?>"]' readonly type="text" style="width: 350px" onfocus="this.select();"><br>
                        <small><?php echo esc_html_e('Place this shortcode on a page or in a post', 'mollie-forms');?></small>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="rfmp_shortcode_total"><?php esc_html_e('Shortcode amount total raised', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input id="rfmp_shortcode_total" value='[rfmp-total id="<?php echo esc_attr($post->ID);?>"]' readonly type="text" style="width: 350px" onfocus="this.select();"><br>
                        <small><?php echo esc_html_e('Place this shortcode on a page or in a post', 'mollie-forms');?></small>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="rfmp_api_key"><?php esc_html_e('Mollie API-key', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_api_key" id="rfmp_api_key" value="<?php echo esc_attr($api_key);?>" required type="text" style="width: 350px">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="rfmp_priceoptions_display"><?php esc_html_e('Price options display', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <select name="rfmp_priceoptions_display" style="width: 350px;">
                            <option value="dropdown"><?php esc_html_e('Dropdown', 'mollie-forms');?></option>
                            <option value="list"<?php echo ($display_po == 'list' ? ' selected' : '');?>><?php esc_html_e('List', 'mollie-forms');?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="rfmp_payment_methods_display"><?php esc_html_e('Payment methods display', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <select name="rfmp_payment_methods_display" style="width: 350px;">
                            <option value="dropdown"><?php esc_html_e('Dropdown', 'mollie-forms');?></option>
                            <option value="list"<?php echo ($display_pm == 'list' ? ' selected' : '');?>><?php esc_html_e('List with icons and text', 'mollie-forms');?></option>
                            <option value="text"<?php echo ($display_pm == 'text' ? ' selected' : '');?>><?php esc_html_e('List with text', 'mollie-forms');?></option>
                            <option value="icons"<?php echo ($display_pm == 'icons' ? ' selected' : '');?>><?php esc_html_e('List with icons', 'mollie-forms');?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="rfmp_payment_desc"><?php esc_html_e('Payment description', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_payment_description" id="rfmp_payment_desc" value="<?php echo esc_attr($payment_description);?>" required type="text" style="width: 350px"><br>
                        <small>
                            <?php esc_html_e('You can use variables in the payment description. Use {rfmp="label"} as variable and replace label with your filled in label of the field.', 'mollie-forms');?><br>
                            <?php esc_html_e('Examples: {rfmp="Name"} {rfmp="Email address"} {rfmp="group"}', 'mollie-forms');?><br>
                            <?php esc_html_e('You can also use fixed variables for the amount {rfmp="amount"} and ID {rfmp="id"} and price option {rfmp="priceoption"} and form title {rfmp="form_title"}', 'mollie-forms');?>
                        </small>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="rfmp_payment_methods_display"><?php esc_html_e('After payment', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <select name="rfmp_after_payment" style="width: 350px;">
                            <option value="message"><?php esc_html_e('Show a message', 'mollie-forms');?></option>
                            <option value="redirect"<?php echo ($after_payment == 'redirect' ? ' selected' : '');?>><?php esc_html_e('Redirect to a page', 'mollie-forms');?></option>
                        </select>
                    </td>
                </tr>

                <tr valign="top" class="rfmp_after_payment_message" <?php echo $after_payment != 'redirect' ? '' : 'style="display: none;"';?>>
                    <th scope="row" class="titledesc">
                        <label for="rfmp_msg_success"><?php esc_html_e('Success message', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_msg_success" id="rfmp_msg_success" value="<?php echo esc_attr($message_success);?>" required type="text" style="width: 350px">
                    </td>
                </tr>
                <tr valign="top" class="rfmp_after_payment_message" <?php echo $after_payment != 'redirect' ? '' : 'style="display: none;"';?>>
                    <th scope="row" class="titledesc">
                        <label for="rfmp_msg_error"><?php esc_html_e('Error message', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_msg_error" id="rfmp_msg_error" value="<?php echo esc_attr($message_error);?>" required type="text" style="width: 350px">
                    </td>
                </tr>
                <tr valign="top" class="rfmp_after_payment_message" <?php echo $after_payment != 'redirect' ? '' : 'style="display: none;"';?>>
                    <th scope="row" class="titledesc">
                        <label for="rfmp_class_success"><?php esc_html_e('Class success message', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_class_success" id="rfmp_class_success" value="<?php echo esc_attr($class_success);?>" type="text" style="width: 350px">
                    </td>
                </tr>
                <tr valign="top" class="rfmp_after_payment_message" <?php echo $after_payment != 'redirect' ? '' : 'style="display: none;"';?>>
                    <th scope="row" class="titledesc">
                        <label for="rfmp_class_error"><?php esc_html_e('Class error message', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_class_error" id="rfmp_class_error" value="<?php echo esc_attr($class_error);?>" type="text" style="width: 350px">
                    </td>
                </tr>

                <tr valign="top" class="rfmp_after_payment_redirect" <?php echo $after_payment == 'redirect' ? '' : 'style="display: none;"';?>>
                    <th scope="row" class="titledesc">
                        <label for="rfmp_redirect_success"><?php esc_html_e('Redirect URL successful payment', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_redirect_success" id="rfmp_redirect_success" value="<?php echo esc_attr($redirect_success);?>" type="text" style="width: 350px">
                    </td>
                </tr>
                <tr valign="top" class="rfmp_after_payment_redirect" <?php echo $after_payment == 'redirect' ? '' : 'style="display: none;"';?>>
                    <th scope="row" class="titledesc">
                        <label for="rfmp_redirect_error"><?php esc_html_e('Redirect URL failed payment', 'mollie-forms');?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="rfmp_redirect_error" id="rfmp_redirect_error" value="<?php echo esc_attr($redirect_error);?>" type="text" style="width: 350px">
                    </td>
                </tr>

                </tbody>
            </table>
        </div>
        <?php
    }

    public function build_meta_boxes_emails($post)
    {
        wp_nonce_field(basename(__FILE__), 'rfmp_meta_box_emails_nonce');

        $enabled_paid_customer      = get_post_meta($post->ID, '_rfmp_enabled_paid_customer', true);
        $email_paid_customer        = get_post_meta($post->ID, '_rfmp_email_paid_customer', true);
        $subject_paid_customer      = get_post_meta($post->ID, '_rfmp_subject_paid_customer', true);
        $fromemail_paid_customer    = get_post_meta($post->ID, '_rfmp_fromemail_paid_customer', true);
        $fromname_paid_customer     = get_post_meta($post->ID, '_rfmp_fromname_paid_customer', true);

        $enabled_expired_customer      = get_post_meta($post->ID, '_rfmp_enabled_expired_customer', true);
        $email_expired_customer        = get_post_meta($post->ID, '_rfmp_email_expired_customer', true);
        $subject_expired_customer      = get_post_meta($post->ID, '_rfmp_subject_expired_customer', true);
        $fromemail_expired_customer    = get_post_meta($post->ID, '_rfmp_fromemail_expired_customer', true);
        $fromname_expired_customer     = get_post_meta($post->ID, '_rfmp_fromname_expired_customer', true);

        $enabled_cancelled_customer      = get_post_meta($post->ID, '_rfmp_enabled_cancelled_customer', true);
        $email_cancelled_customer        = get_post_meta($post->ID, '_rfmp_email_cancelled_customer', true);
        $subject_cancelled_customer      = get_post_meta($post->ID, '_rfmp_subject_cancelled_customer', true);
        $fromemail_cancelled_customer    = get_post_meta($post->ID, '_rfmp_fromemail_cancelled_customer', true);
        $fromname_cancelled_customer     = get_post_meta($post->ID, '_rfmp_fromname_cancelled_customer', true);

        $enabled_paid_merchant      = get_post_meta($post->ID, '_rfmp_enabled_paid_merchant', true);
        $email_paid_merchant        = get_post_meta($post->ID, '_rfmp_email_paid_merchant', true);
        $subject_paid_merchant      = get_post_meta($post->ID, '_rfmp_subject_paid_merchant', true);
        $fromemail_paid_merchant    = get_post_meta($post->ID, '_rfmp_fromemail_paid_merchant', true);
        $fromname_paid_merchant     = get_post_meta($post->ID, '_rfmp_fromname_paid_merchant', true);

        $enabled_expired_merchant      = get_post_meta($post->ID, '_rfmp_enabled_expired_merchant', true);
        $email_expired_merchant        = get_post_meta($post->ID, '_rfmp_email_expired_merchant', true);
        $subject_expired_merchant      = get_post_meta($post->ID, '_rfmp_subject_expired_merchant', true);
        $fromemail_expired_merchant    = get_post_meta($post->ID, '_rfmp_fromemail_expired_merchant', true);
        $fromname_expired_merchant     = get_post_meta($post->ID, '_rfmp_fromname_expired_merchant', true);

        $enabled_cancelled_merchant      = get_post_meta($post->ID, '_rfmp_enabled_cancelled_merchant', true);
        $email_cancelled_merchant        = get_post_meta($post->ID, '_rfmp_email_cancelled_merchant', true);
        $subject_cancelled_merchant      = get_post_meta($post->ID, '_rfmp_subject_cancelled_merchant', true);
        $fromemail_cancelled_merchant    = get_post_meta($post->ID, '_rfmp_fromemail_cancelled_merchant', true);
        $fromname_cancelled_merchant     = get_post_meta($post->ID, '_rfmp_fromname_cancelled_merchant', true);

        $rfmp_editor_settings   = array();
        ?>
        <div class='inside'>
            <div id="rfmp_tabs">
                <ul>
                    <li><a href="#rfmp_tab_paid_customer"><?php esc_html_e('Customer: Payment successful', 'mollie-forms');?></a></li>
                    <li><a href="#rfmp_tab_expired_customer"><?php esc_html_e('Customer: Payment expired', 'mollie-forms');?></a></li>
                    <li><a href="#rfmp_tab_cancelled_customer"><?php esc_html_e('Customer: Payment cancelled', 'mollie-forms');?></a></li>
                    <li><a href="#rfmp_tab_paid_merchant"><?php esc_html_e('Merchant: Payment successful', 'mollie-forms');?></a></li>
                    <li><a href="#rfmp_tab_expired_merchant"><?php esc_html_e('Merchant: Payment expired', 'mollie-forms');?></a></li>
                    <li><a href="#rfmp_tab_cancelled_merchant"><?php esc_html_e('Merchant: Payment cancelled', 'mollie-forms');?></a></li>
                </ul>

                <div id="rfmp_tab_paid_customer">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Enabled', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_enabled_paid_customer" value="1" type="checkbox" <?php echo $enabled_paid_customer == '1' ? 'checked' : '';?>>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Subject', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_subject_paid_customer" value="<?php echo esc_attr($subject_paid_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('From "email"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromemail_paid_customer" value="<?php echo esc_attr($fromemail_paid_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('From "name"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromname_paid_customer" value="<?php echo esc_attr($fromname_paid_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                    </table>

                    <?php wp_editor($email_paid_customer, 'rfmp_email_paid_customer', $rfmp_editor_settings); ?>
                </div>
                <div id="rfmp_tab_expired_customer">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Enabled', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_enabled_expired_customer" value="1" type="checkbox" <?php echo $enabled_expired_customer == '1' ? 'checked' : '';?>>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Subject', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_subject_expired_customer" value="<?php echo esc_attr($subject_expired_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('From "email"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromemail_expired_customer" value="<?php echo esc_attr($fromemail_expired_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('From "name"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromname_expired_customer" value="<?php echo esc_attr($fromname_expired_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                    </table>

                    <?php wp_editor($email_expired_customer, 'rfmp_email_expired_customer', $rfmp_editor_settings); ?>
                </div>
                <div id="rfmp_tab_cancelled_customer">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Enabled', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_enabled_cancelled_customer" value="1" type="checkbox" <?php echo $enabled_cancelled_customer == '1' ? 'checked' : '';?>>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Subject', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_subject_cancelled_customer" value="<?php echo esc_attr($subject_cancelled_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('From "email"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromemail_cancelled_customer" value="<?php echo esc_attr($fromemail_cancelled_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('From "name"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromname_cancelled_customer" value="<?php echo esc_attr($fromname_cancelled_customer);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                    </table>

                    <?php wp_editor($email_cancelled_customer, 'rfmp_email_cancelled_customer', $rfmp_editor_settings); ?>
                </div>
                <div id="rfmp_tab_paid_merchant">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Enabled', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_enabled_paid_merchant" value="1" type="checkbox" <?php echo $enabled_paid_merchant == '1' ? 'checked' : '';?>>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Subject', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_subject_paid_merchant" value="<?php echo esc_attr($subject_paid_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('To/from "email"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromemail_paid_merchant" value="<?php echo esc_attr($fromemail_paid_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('To/from "name"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromname_paid_merchant" value="<?php echo esc_attr($fromname_paid_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                    </table>

                    <?php wp_editor($email_paid_merchant, 'rfmp_email_paid_merchant', $rfmp_editor_settings); ?>
                </div>
                <div id="rfmp_tab_expired_merchant">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Enabled', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_enabled_expired_merchant" value="1" type="checkbox" <?php echo $enabled_expired_merchant == '1' ? 'checked' : '';?>>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Subject', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_subject_expired_merchant" value="<?php echo esc_attr($subject_expired_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('To/from "email"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromemail_expired_merchant" value="<?php echo esc_attr($fromemail_expired_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('To/from "name"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromname_expired_merchant" value="<?php echo esc_attr($fromname_expired_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                    </table>

                    <?php wp_editor($email_expired_merchant, 'rfmp_email_expired_merchant', $rfmp_editor_settings); ?>
                </div>
                <div id="rfmp_tab_cancelled_merchant">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Enabled', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_enabled_cancelled_merchant" value="1" type="checkbox" <?php echo $enabled_cancelled_merchant == '1' ? 'checked' : '';?>>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('Subject', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_subject_cancelled_merchant" value="<?php echo esc_attr($subject_cancelled_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('To/from "email"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromemail_cancelled_merchant" value="<?php echo esc_attr($fromemail_cancelled_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label><?php esc_html_e('To/from "name"', 'mollie-forms');?></label>
                            </th>
                            <td class="forminp forminp-text">
                                <input name="rfmp_fromname_cancelled_merchant" value="<?php echo esc_attr($fromname_cancelled_merchant);?>" type="text" style="width: 350px">
                            </td>
                        </tr>
                    </table>

                    <?php wp_editor($email_cancelled_merchant, 'rfmp_email_cancelled_merchant', $rfmp_editor_settings); ?>
                </div>
            </div>

            <br>
            <?php esc_html_e('You can use variables in the subjects and messages. Use {rfmp="label"} as variable and replace label with your filled in label of the field.', 'mollie-forms');?><br>
            <?php esc_html_e('Examples: {rfmp="Name"} {rfmp="Email address"} {rfmp="group"}', 'mollie-forms');?><br>
            <?php esc_html_e('You can also use fixed variables for the amount {rfmp="amount"} and payment status {rfmp="status"} and payment interval {rfmp="interval"} and payment ID {rfmp="payment_id"} and form title {rfmp="form_title"} and page url {rfmp="url"}', 'mollie-forms');?>
        </div>
        <?php
    }

    public function build_meta_boxes_paymentmethods($post)
    {
        wp_nonce_field(basename(__FILE__), 'rfmp_meta_box_paymentmethods_nonce');
        $api_key    = get_post_meta($post->ID, '_rfmp_api_key', true);
        $active     = get_post_meta($post->ID, '_rfmp_payment_method', true);
        $fixed      = get_post_meta($post->ID, '_rfmp_payment_method_fixed', true);
        $variable   = get_post_meta($post->ID, '_rfmp_payment_method_variable', true);

        try {

            if (!$api_key)
                echo '<p style="color: red">' . esc_html__('No API-key set', 'mollie-forms') . '</p>';
            else
            {
                $this->mollie->setApiKey($api_key);

                foreach ($this->mollie->methods->all(0, 0, array('locale' => get_locale())) as $method)
                {
                    echo '<input type="hidden" value="0" name="rfmp_payment_method[' . $method->id . ']">';
                    echo '<label><input type="checkbox" name="rfmp_payment_method[' . $method->id . ']" ' . ($active[$method->id] ? 'checked' : '') . ' value="1"> <img style="vertical-align:middle;display:inline-block;width:25px;" src="' . esc_url($method->image->normal) . '"> ' . esc_html($method->description) . '</label><br>';
                    echo esc_html_e('Surcharge:', 'mollie-forms') . ' &euro; <input type="number" step="any" min="0" name="rfmp_payment_method_fixed[' . $method->id . ']" value="' . esc_attr($fixed[$method->id]) . '" style="width: 50px;"> + <input type="number" step="any" min="0" name="rfmp_payment_method_variable[' . $method->id . ']" value="' . esc_attr($variable[$method->id]) . '" style="width: 50px;"> %<br><hr>';
                }
            }


        } catch (Mollie_API_Exception $e) {
            echo '<p style="color: red">' . $e->getMessage() . '</p>';
        }
    }

    public function save_meta_boxes($post_id)
    {
        // verify meta box nonce
        if (!isset($_POST['rfmp_meta_box_fields_nonce']) || !wp_verify_nonce($_POST['rfmp_meta_box_fields_nonce'], basename(__FILE__)))
            return;

        // verify meta box nonce
        if (!isset($_POST['rfmp_meta_box_priceoptions_nonce']) || !wp_verify_nonce($_POST['rfmp_meta_box_priceoptions_nonce'], basename(__FILE__)))
            return;

        // verify meta box nonce
        if (!isset($_POST['rfmp_meta_box_settings_nonce']) || !wp_verify_nonce($_POST['rfmp_meta_box_settings_nonce'], basename(__FILE__)))
            return;

        // verify meta box nonce
        if (!isset($_POST['rfmp_meta_box_paymentmethods_nonce']) || !wp_verify_nonce($_POST['rfmp_meta_box_paymentmethods_nonce'], basename(__FILE__)))
            return;

        // verify meta box nonce
        if (!isset($_POST['rfmp_meta_box_emails_nonce']) || !wp_verify_nonce($_POST['rfmp_meta_box_emails_nonce'], basename(__FILE__)))
            return;

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id))
            return;

        // Store custom fields
        update_post_meta($post_id, '_rfmp_api_key', $_POST['rfmp_api_key']);
        update_post_meta($post_id, '_rfmp_payment_methods_display', $_POST['rfmp_payment_methods_display']);
        update_post_meta($post_id, '_rfmp_priceoptions_display', $_POST['rfmp_priceoptions_display']);
        update_post_meta($post_id, '_rfmp_after_payment', $_POST['rfmp_after_payment']);
        update_post_meta($post_id, '_rfmp_redirect_success', $_POST['rfmp_redirect_success']);
        update_post_meta($post_id, '_rfmp_redirect_error', $_POST['rfmp_redirect_error']);
        update_post_meta($post_id, '_rfmp_class_success', $_POST['rfmp_class_success']);
        update_post_meta($post_id, '_rfmp_class_error', $_POST['rfmp_class_error']);
        update_post_meta($post_id, '_rfmp_payment_description', $_POST['rfmp_payment_description']);
        update_post_meta($post_id, '_rfmp_msg_success', $_POST['rfmp_msg_success']);
        update_post_meta($post_id, '_rfmp_msg_error', $_POST['rfmp_msg_error']);

        update_post_meta($post_id, '_rfmp_fields_type', $_POST['rfmp_fields_type']);
        update_post_meta($post_id, '_rfmp_fields_label', $_POST['rfmp_fields_label']);
        update_post_meta($post_id, '_rfmp_fields_value', $_POST['rfmp_fields_value']);
        update_post_meta($post_id, '_rfmp_fields_class', $_POST['rfmp_fields_class']);
        update_post_meta($post_id, '_rfmp_fields_required', $_POST['rfmp_fields_required']);

        update_post_meta($post_id, '_rfmp_priceoption_desc', $_POST['rfmp_priceoptions_desc']);
        update_post_meta($post_id, '_rfmp_priceoption_price', $_POST['rfmp_priceoptions_price']);
        update_post_meta($post_id, '_rfmp_priceoption_pricetype', $_POST['rfmp_priceoptions_pricetype']);
        update_post_meta($post_id, '_rfmp_priceoption_shipping', $_POST['rfmp_priceoptions_shipping']);
        update_post_meta($post_id, '_rfmp_priceoption_frequency', $_POST['rfmp_priceoptions_frequency']);
        update_post_meta($post_id, '_rfmp_priceoption_frequencyval', $_POST['rfmp_priceoptions_frequencyval']);
        update_post_meta($post_id, '_rfmp_priceoption_times', $_POST['rfmp_priceoptions_times']);

        update_post_meta($post_id, '_rfmp_payment_method', $_POST['rfmp_payment_method']);
        update_post_meta($post_id, '_rfmp_payment_method_fixed', $_POST['rfmp_payment_method_fixed']);
        update_post_meta($post_id, '_rfmp_payment_method_variable', $_POST['rfmp_payment_method_variable']);

        update_post_meta($post_id, '_rfmp_enabled_paid_customer', $_POST['rfmp_enabled_paid_customer']);
        update_post_meta($post_id, '_rfmp_email_paid_customer', $_POST['rfmp_email_paid_customer']);
        update_post_meta($post_id, '_rfmp_subject_paid_customer', $_POST['rfmp_subject_paid_customer']);
        update_post_meta($post_id, '_rfmp_fromname_paid_customer', $_POST['rfmp_fromname_paid_customer']);
        update_post_meta($post_id, '_rfmp_fromemail_paid_customer', $_POST['rfmp_fromemail_paid_customer']);
        update_post_meta($post_id, '_rfmp_enabled_expired_customer', $_POST['rfmp_enabled_expired_customer']);
        update_post_meta($post_id, '_rfmp_email_expired_customer', $_POST['rfmp_email_expired_customer']);
        update_post_meta($post_id, '_rfmp_subject_expired_customer', $_POST['rfmp_subject_expired_customer']);
        update_post_meta($post_id, '_rfmp_fromname_expired_customer', $_POST['rfmp_fromname_expired_customer']);
        update_post_meta($post_id, '_rfmp_fromemail_expired_customer', $_POST['rfmp_fromemail_expired_customer']);
        update_post_meta($post_id, '_rfmp_enabled_cancelled_customer', $_POST['rfmp_enabled_cancelled_customer']);
        update_post_meta($post_id, '_rfmp_email_cancelled_customer', $_POST['rfmp_email_cancelled_customer']);
        update_post_meta($post_id, '_rfmp_subject_cancelled_customer', $_POST['rfmp_subject_cancelled_customer']);
        update_post_meta($post_id, '_rfmp_fromname_cancelled_customer', $_POST['rfmp_fromname_cancelled_customer']);
        update_post_meta($post_id, '_rfmp_fromemail_cancelled_customer', $_POST['rfmp_fromemail_cancelled_customer']);

        update_post_meta($post_id, '_rfmp_enabled_paid_merchant', $_POST['rfmp_enabled_paid_merchant']);
        update_post_meta($post_id, '_rfmp_email_paid_merchant', $_POST['rfmp_email_paid_merchant']);
        update_post_meta($post_id, '_rfmp_subject_paid_merchant', $_POST['rfmp_subject_paid_merchant']);
        update_post_meta($post_id, '_rfmp_fromname_paid_merchant', $_POST['rfmp_fromname_paid_merchant']);
        update_post_meta($post_id, '_rfmp_fromemail_paid_merchant', $_POST['rfmp_fromemail_paid_merchant']);
        update_post_meta($post_id, '_rfmp_enabled_expired_merchant', $_POST['rfmp_enabled_expired_merchant']);
        update_post_meta($post_id, '_rfmp_email_expired_merchant', $_POST['rfmp_email_expired_merchant']);
        update_post_meta($post_id, '_rfmp_subject_expired_merchant', $_POST['rfmp_subject_expired_merchant']);
        update_post_meta($post_id, '_rfmp_fromname_expired_merchant', $_POST['rfmp_fromname_expired_merchant']);
        update_post_meta($post_id, '_rfmp_fromemail_expired_merchant', $_POST['rfmp_fromemail_expired_merchant']);
        update_post_meta($post_id, '_rfmp_enabled_cancelled_merchant', $_POST['rfmp_enabled_cancelled_merchant']);
        update_post_meta($post_id, '_rfmp_email_cancelled_merchant', $_POST['rfmp_email_cancelled_merchant']);
        update_post_meta($post_id, '_rfmp_subject_cancelled_merchant', $_POST['rfmp_subject_cancelled_merchant']);
        update_post_meta($post_id, '_rfmp_fromname_cancelled_merchant', $_POST['rfmp_fromname_cancelled_merchant']);
        update_post_meta($post_id, '_rfmp_fromemail_cancelled_merchant', $_POST['rfmp_fromemail_cancelled_merchant']);
    }

    public function load_scripts()
    {
        wp_enqueue_script('rfmp_admin_scripts', plugin_dir_url(__FILE__) . 'js/admin-scripts.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable', 'jquery-ui-tabs'), RFMP_VERSION);
        wp_enqueue_style('rfmp_admin_styles', plugin_dir_url(__FILE__) . 'css/admin-styles.css', array(), RFMP_VERSION);

        wp_register_style('jQueryUI', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_enqueue_style('jQueryUI');
    }

    public function post_actions($actions, $post)
    {
        if ($post->post_type=='rfmp')
        {
            unset($actions['inline hide-if-no-js']);
            unset($actions['view']);
            $actions['registrations'] = '<a href="edit.php?post_type=rfmp&page=registrations&post=' . $post->ID . '">' . __('Registrations', 'mollie-forms') . '</a>';
            $actions['export'] = '<a href="' . admin_url('admin-post.php?action=rfmp_export&post=' . $post->ID) . '">' . __('Export', 'mollie-forms') . '</a>';
        }
        return $actions;
    }

    public function page_registrations()
    {
        $table = new RFMP_Registrations_Table();
        $table->prepare_items();

        if (isset($_GET['post']))
            $post = get_post($_GET['post']);

        if (isset($_GET['msg']))
        {
            switch ($_GET['msg'])
            {
                case 'delete-ok':
                    $rfmp_msg = '<div class="updated notice"><p>' . esc_html__('The registration is successful deleted', 'mollie-forms') . '</p></div>';
                    break;
            }

            echo isset($rfmp_msg) ? $rfmp_msg : '';
        }
        ?>
        <div class="wrap">
            <h2><?php esc_html_e('Registrations', 'mollie-forms'); echo (isset($post) ? ' <small>(' . $post->post_title . ')</small>' : '');?></h2>

            <?php $table->display();?>
        </div>
        <?php
    }

    public function page_registration()
    {
        if (!isset($_GET['view']))
            return esc_html__('Registration not found', 'mollie-forms');

        $id = (int) $_GET['view'];

        $registration   = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id=" . $id);
        if ($registration == null)
            return esc_html__('Registration not found', 'mollie-forms');

        // Delete registration
        if (isset($_GET['delete']) && check_admin_referer('delete-reg_' . $_GET['view']))
        {
            $this->wpdb->query($this->wpdb->prepare("DELETE FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE id = %s",
                $id
            ));

            wp_redirect('?post_type=rfmp&page=registrations&msg=delete-ok');
            exit;
        }

        // subscriptions table fix
        if ($registration->subs_fix)
            $subs_table = RFMP_TABLE_SUBSCRIPTIONS;
        else
            $subs_table = RFMP_TABLE_CUSTOMERS;

        $api_key        = get_post_meta($registration->post_id, '_rfmp_api_key', true);

        // Connect with Mollie
        $mollie = new Mollie_API_Client;
        $mollie->setApiKey($api_key);

        // Get all subscriptions
        $allSubs   = $mollie->customers_subscriptions->withParentId($registration->customer_id)->all(0, 250);
        foreach ($allSubs as $sub)
        {
            $this->wpdb->query($this->wpdb->prepare("UPDATE " . $subs_table . " SET sub_status = %s WHERE subscription_id = %s",
                $sub->status,
                $sub->id
            ));
        }

        $fields         = $this->wpdb->get_results("SELECT * FROM " . RFMP_TABLE_REGISTRATION_FIELDS . " WHERE registration_id=" . $id);
        $subscriptions  = $this->wpdb->get_results("SELECT * FROM " . $subs_table . " WHERE registration_id=" . $id);
        $payments       = $this->wpdb->get_results("SELECT * FROM " . RFMP_TABLE_PAYMENTS . " WHERE registration_id=" . $id);

        // Cancel subscription
        if (isset($_GET['cancel']) && check_admin_referer('cancel-sub_' . $_GET['cancel']))
        {
            try {
                $cancelledSub   = $mollie->customers_subscriptions->withParentId($registration->customer_id)->cancel($_GET['cancel']);

                $this->wpdb->query($this->wpdb->prepare("UPDATE " . $subs_table . " SET sub_status = %s WHERE subscription_id = %s",
                    $cancelledSub->status,
                    $cancelledSub->id
                ));

                wp_redirect('?post_type=' . $_REQUEST['post_type'] . '&page=' . $_REQUEST['page'] . '&view=' . $_REQUEST['view'] . '&msg=cancel-ok');
            } catch(Mollie_API_Exception $e) {
                echo '<div class="error notice">' . $e->getMessage() . '</div>';
            }
        }

        // Refund payment
        if (isset($_GET['refund']) && check_admin_referer('refund-payment_' . $_GET['refund']))
        {
            try {
                $payment = $mollie->payments->get($_GET['refund']);
                if ($payment->canBeRefunded())
                {
                    $refund = $mollie->payments->refund($payment);

                    $this->wpdb->query($this->wpdb->prepare("UPDATE " . RFMP_TABLE_PAYMENTS . " SET payment_status = %s WHERE payment_id = %s",
                        $payment->status,
                        $payment->id
                    ));

                    wp_redirect('?post_type=' . $_REQUEST['post_type'] . '&page=' . $_REQUEST['page'] . '&view=' . $_REQUEST['view'] . '&msg=refund-ok');
                }
                else
                    wp_redirect('?post_type=' . $_REQUEST['post_type'] . '&page=' . $_REQUEST['page'] . '&view=' . $_REQUEST['view'] . '&msg=refund-nok');
            } catch(Mollie_API_Exception $e) {
                echo '<div class="error notice">' . $e->getMessage() . '</div>';
            }
        }

        if (isset($_GET['msg']))
        {
            switch ($_GET['msg'])
            {
                case 'refund-ok':
                    $rfmp_msg = '<div class="updated notice"><p>' . esc_html__('The payment is successful refunded', 'mollie-forms') . '</p></div>';
                    break;
                case 'refund-nok':
                    $rfmp_msg = '<div class="error notice"><p>' . esc_html__('The payment can not be refunded', 'mollie-forms') . '</p></div>';
                    break;
                case 'cancel-ok':
                    $rfmp_msg = '<div class="updated notice"><p>' . esc_html__('The subscription is successful cancelled', 'mollie-forms') . '</p></div>';
                    break;
            }

            echo isset($rfmp_msg) ? $rfmp_msg : '';
        }
        ?>
        <div class="wrap">
            <h2><?php esc_html_e('Registration', 'mollie-forms');?></h2>

            <table class="wp-list-table widefat fixed striped rfmp_page_registration">
                <tbody id="the-list">
                    <?php foreach ($fields as $row) { ?>
                        <tr>
                            <td class="field column-field column-primary"><strong><?php echo esc_html($row->field);?></strong></td>
                            <td class="value column-value"><?php echo nl2br(esc_html($row->value));?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td class="field column-field column-primary"><strong><?php echo esc_html_e('Total price', 'mollie-forms');?></strong></td>
                        <td class="value column-value"><?php echo '&euro; ' . number_format($registration->total_price, 2, ',', '');?></td>
                    </tr>
                    <tr>
                        <td class="field column-field column-primary"><strong><?php echo esc_html_e('Mollie Customer ID', 'mollie-forms');?></strong></td>
                        <td class="value column-value"><?php echo esc_html($registration->customer_id);?></td>
                    </tr>
                </tbody>
            </table><br>

            <?php if ($registration->price_frequency != 'once' && $subscriptions != null) { ?>
                <h3><?php esc_html_e('Subscriptions', 'mollie-forms');?></h3>
                <table class="wp-list-table widefat fixed striped rfmp_page_registration_subscriptions">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Subscription ID', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Created at', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Subscription mode', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Subscription amount', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Subscription number of times', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Subscription interval', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Subscription description', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Subscription status', 'mollie-forms');?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="the-list">
                    <?php
                    foreach ($subscriptions as $subscription) {
                        $url_cancel = wp_nonce_url('?post_type=rfmp&page=registration&view=' . $id . '&cancel=' . $subscription->subscription_id, 'cancel-sub_' . $subscription->subscription_id);
                        ?>
                        <tr>
                            <td class="column-subscription_id"><?php echo esc_html($subscription->subscription_id);?></td>
                            <td class="column-created_at"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription->created_at)));?></td>
                            <td class="column-sub_mode"><?php echo esc_html($subscription->sub_mode);?></td>
                            <td class="column-sub_amount">&euro;<?php echo esc_html(number_format($subscription->sub_amount, 2, ',', ''));?></td>
                            <td class="column-sub_times"><?php echo esc_html($subscription->sub_times);?></td>
                            <td class="column-sub_interval"><?php echo esc_html($this->frequency_label($subscription->sub_interval));?></td>
                            <td class="column-sub_description"><?php echo esc_html($subscription->sub_description);?></td>
                            <td class="column-sub_status"><?php echo esc_html($subscription->sub_status);?></td>
                            <td class="column-cancel"><?php if ($subscription->sub_status == 'active') { ?><a href="<?php echo $url_cancel;?>" style="color:#a00;"><?php echo esc_html_e('Cancel', 'mollie-forms');?></a><?php } ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table><br>
            <?php } ?>

            <h3><?php esc_html_e('Payments', 'mollie-forms');?></h3>
            <table class="wp-list-table widefat fixed striped rfmp_page_registration_payments">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Payment ID', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Created at', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Payment method', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Payment mode', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Payment status', 'mollie-forms');?></th>
                        <th><?php esc_html_e('Amount', 'mollie-forms');?></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="the-list">
                <?php
                foreach ($payments as $payment) {
                    $url_refund = wp_nonce_url('?post_type=rfmp&page=registration&view=' . $id . '&refund=' . $payment->payment_id, 'refund-payment_' . $payment->payment_id);
                    try {
                        $mollie_payment = $mollie->payments->get($payment->payment_id);
                    } catch(Mollie_API_Exception $e) {

                    }
                    ?>
                    <tr>
                        <td class="column-rfmp_id"><?php echo esc_html($payment->rfmp_id);?></td>
                        <td class="column-payment_id"><?php echo esc_html($payment->payment_id);?></td>
                        <td class="column-created_at"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->created_at)));?></td>
                        <td class="column-payment_method"><?php echo esc_html($payment->payment_method);?></td>
                        <td class="column-payment_mode"><?php echo esc_html($payment->payment_mode);?></td>
                        <td class="column-payment_status"><?php echo esc_html($payment->payment_status);?></td>
                        <td class="column-amount"><?php echo '&euro; ' . number_format($payment->amount, 2, ',', '');?></td>
                        <td><?php echo (isset($mollie_payment, $mollie_payment->details->consumerName) ? esc_html($mollie_payment->details->consumerName) . '<br>' . esc_html($mollie_payment->details->consumerAccount) : '');?></td>
                        <td class="column-cancel"><?php if ($payment->payment_status == 'paid') { ?><a href="<?php echo $url_refund;?>" style="color:#a00;"><?php echo esc_html_e('Refund', 'mollie-forms');?></a><?php } ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table><br>
        </div>
        <?php
    }

    public function export_registrations()
    {
        $post_id = $_GET['post'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registrations.csv');
        $output = fopen('php://output', 'w');

        $headers = array(
            esc_html__('Status', 'mollie-forms'),
            esc_html__('Date/time', 'mollie-forms'),
            esc_html__('Total', 'mollie-forms'),
            esc_html__('Frequency', 'mollie-forms'),
            esc_html__('Number of times', 'mollie-forms'),
            esc_html__('Description', 'mollie-forms'),
        );

        // get all fields for headers
        $registration = $this->wpdb->get_row("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE post_id=" . (int) $post_id . " ORDER BY id DESC LIMIT 1");
        $fields = $this->wpdb->get_results("SELECT * FROM " . RFMP_TABLE_REGISTRATION_FIELDS . " WHERE registration_id=" . (int) $registration->id);
        foreach ($fields as $field)
            $headers[] = esc_html($field->field);

        // put header in csv
        fputcsv($output, $headers);

        // make all registration rows
        $registrations = $this->wpdb->get_results("SELECT * FROM " . RFMP_TABLE_REGISTRATIONS . " WHERE post_id=" . (int) $post_id . " ORDER BY id DESC");
        foreach ($registrations as $registration)
        {
            $paymentsPaid = $this->wpdb->get_var("SELECT COUNT(*) FROM " . RFMP_TABLE_PAYMENTS . " WHERE payment_status='paid' AND registration_id=" . (int) $registration->id);

            $rows = array(
                $paymentsPaid ? __('Paid', 'mollie-forms') : __('Not paid', 'mollie-forms'),
                $registration->created_at,
                $registration->total_price,
                $this->frequency_label($registration->price_frequency),
                $registration->number_of_times,
                $registration->description,
            );

            $fields = $this->wpdb->get_results("SELECT * FROM " . RFMP_TABLE_REGISTRATION_FIELDS . " WHERE registration_id=" . (int) $registration->id);
            foreach ($fields as $field)
                $rows[] = esc_html($field->value);

            // put row in csv
            fputcsv($output, $rows);
        }
    }

    private function frequency_label($frequency)
    {
        $frequency = trim($frequency);
        switch ($frequency)
        {
            case 'once':
                $return = __('Once', 'mollie-forms');
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