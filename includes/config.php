<?php

define('RFMP_TXT_DOMAIN', 'mollie-forms');
define('RFMP_WEBHOOK', '?mollie-forms=true&post_id=');

define('RFMP_TABLE_REGISTRATIONS', $wpdb->prefix . 'rfmp_registrations');
define('RFMP_TABLE_REGISTRATION_FIELDS', $wpdb->prefix . 'rfmp_registration_fields');
define('RFMP_TABLE_PAYMENTS', $wpdb->prefix . 'rfmp_payments');
define('RFMP_TABLE_CUSTOMERS', $wpdb->prefix . 'rfmp_customers');
define('RFMP_TABLE_SUBSCRIPTIONS', $wpdb->prefix . 'rfmp_subscriptions');