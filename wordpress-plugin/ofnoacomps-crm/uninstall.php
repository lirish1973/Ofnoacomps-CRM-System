<?php
/**
 * Ofnoacomps CRM - Uninstall
 *
 * Runs when the plugin is deleted from the WordPress admin (Plugins > Delete).
 * Drops all plugin database tables and removes all plugin options.
 *
 * NOTE: This file is executed by WordPress directly — ABSPATH and WP functions
 * are available, but the plugin itself is NOT loaded. We therefore duplicate
 * just the table-drop logic here rather than requiring class-database.php.
 */

// Security check — must be called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$tables = [
    'ofnoacomps_leads',
    'ofnoacomps_customers',
    'ofnoacomps_pipelines',
    'ofnoacomps_stages',
    'ofnoacomps_deals',
    'ofnoacomps_activities',
    'ofnoacomps_deal_stage_log',
    'ofnoacomps_lead_status_log',
    'ofnoacomps_api_keys',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore
}

// Remove plugin options
delete_option( 'ofnoacomps_crm_schema_version' );
delete_option( 'ofnoacomps_crm_currency' );
delete_option( 'ofnoacomps_crm_notify_email' );

// Remove cached update transients
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_ofnoacomps_ghupd_%'
        OR option_name LIKE '_transient_timeout_ofnoacomps_ghupd_%'"
); // phpcs:ignore
