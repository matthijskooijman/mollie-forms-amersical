<?php
/*
Plugin Name: Mollie Forms
Description: Create registration forms with payment methods of Mollie. One-time and recurring payments are possible.
Version: 1.1.5
Author: Wobbie.nl
Author URI: http://wobbie.nl
Text Domain: mollie-forms
*/

if (!defined('ABSPATH')) {
    die('Please do not load this file directly!');
}

// Plugin Folder Path
if (!defined('RFMP_PLUGIN_PATH')) {
    define('RFMP_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

// Plugin Version
if (!defined('RFMP_VERSION')) {
    define('RFMP_VERSION', '1.1.5');
}

define('RFMP_PLUGIN_BASE', plugin_basename(__FILE__));

global $wpdb;

// Includes
if(!class_exists('Mollie_API_Client'))
    require_once RFMP_PLUGIN_PATH . 'libs/mollie-api-php/src/Mollie/API/Autoloader.php';

require_once RFMP_PLUGIN_PATH . 'includes/config.php';
require_once RFMP_PLUGIN_PATH . 'includes/class-webhook.php';
require_once RFMP_PLUGIN_PATH . 'includes/class-start.php';

$rfmp_webhook = new RFMP_Webhook;
$rfmp = new RFMP_Start;

if (is_admin())
{
    if(!class_exists('WP_List_Table'))
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

    require_once RFMP_PLUGIN_PATH . 'includes/class-registrations-table.php';
    require_once RFMP_PLUGIN_PATH . 'includes/class-admin.php';

    $admin = new RFMP_Admin;
}

function rfmp_install_plugin()
{
    global $wpdb;
    $charset_collate            = $wpdb->get_charset_collate();
    $table_registrations        = RFMP_TABLE_REGISTRATIONS;
    $table_registration_fields  = RFMP_TABLE_REGISTRATION_FIELDS;
    $table_payments             = RFMP_TABLE_PAYMENTS;
    $table_customers            = RFMP_TABLE_CUSTOMERS;
    $table_subscriptions        = RFMP_TABLE_SUBSCRIPTIONS;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sqlRegistrations = "CREATE TABLE $table_registrations (
            id                mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at        datetime NOT NULL,
            post_id           mediumint(9) NOT NULL,
            customer_id       varchar(45),
            subscription_id   varchar(45),
            total_price       decimal(8,2),
            recurring_price   decimal(8,2),
            price_frequency   varchar(45),
            number_of_times   mediumint(9),
            description       varchar(255),
            subs_fix          smallint(1) NOT NULL DEFAULT 0,
            UNIQUE KEY id (id)
        ) $charset_collate;";
    dbDelta($sqlRegistrations);

    $sqlRegistrationFields = "CREATE TABLE $table_registration_fields (
            registration_id   mediumint(9) NOT NULL,
            type              varchar(255),
            field             varchar(255),
            value             longtext
        ) $charset_collate;";
    dbDelta($sqlRegistrationFields);

    $sqlPayments = "CREATE TABLE $table_payments (
            id                mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at        datetime NOT NULL,
            registration_id   mediumint(9) NOT NULL,
            payment_id        varchar(45) NOT NULL,
            payment_method    varchar(255) NOT NULL,
            payment_mode      varchar(255) NOT NULL,
            payment_status    varchar(255) NOT NULL,
            amount            decimal(8,2) NOT NULL,
            rfmp_id           varchar(255) NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
    dbDelta($sqlPayments);

    $sqlCustomers = "CREATE TABLE $table_customers (
            id                mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at        datetime NOT NULL,
            post_id           mediumint(9) NOT NULL,
            customer_id       varchar(45) NOT NULL,
            name              varchar(255) NOT NULL,
            email             varchar(255) NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
    dbDelta($sqlCustomers);

    $sqlSubscriptions = "CREATE TABLE $table_subscriptions (
            id                mediumint(9) NOT NULL AUTO_INCREMENT,
            registration_id   mediumint(9) NOT NULL,
            subscription_id   varchar(45) NOT NULL,
            customer_id       varchar(45) NOT NULL,
            sub_mode          varchar(45) NOT NULL,
            sub_amount        float(15) NOT NULL,
            sub_times         mediumint(9) NOT NULL,
            sub_interval      varchar(45) NOT NULL,
            sub_description   varchar(255) NOT NULL,
            sub_method        varchar(45) NOT NULL,
            sub_status        varchar(25) NOT NULL,
            created_at        datetime NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
    dbDelta($sqlSubscriptions);

    update_option('rfmp_version', RFMP_VERSION);
}

function rfmp_uninstall_plugin()
{
    global $wpdb;

}

function start_buffer_output()
{
    ob_start();
}

// Update database when plugin is updated
if (get_option('rfmp_version') != RFMP_VERSION)
    rfmp_install_plugin();


// Register hooks
register_activation_hook(__FILE__, 'rfmp_install_plugin');
register_uninstall_hook(__FILE__, 'rfmp_uninstall_plugin');

add_action('init', 'start_buffer_output');

load_plugin_textdomain('mollie-forms');