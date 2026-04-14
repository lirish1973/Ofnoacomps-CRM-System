<?php
/**
 * Plugin Name: Ofnoacomps CRM
 * Plugin URI:  https://www.ofnoacomps.co.il
 * Description: מערכת CRM מלאה לניהול לידים, לקוחות, עסקאות ודוחות עם מעקב מקור תנועה.
 * Version:     1.3.7
 * Author:      Ofnoacomps
 * Text Domain: ofnoacomps-crm
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('OFNOACOMPS_CRM_VERSION',     '1.3.7');
define('OFNOACOMPS_CRM_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('OFNOACOMPS_CRM_PLUGIN_URL',  plugin_dir_url(__FILE__));
define('OFNOACOMPS_CRM_PLUGIN_FILE', __FILE__);

// Autoload classes
spl_autoload_register(function ($class) {
    $map = [
        'Ofnoacomps_CRM_Database'   => 'includes/class-database.php',
        'Ofnoacomps_CRM_Lead'       => 'includes/class-lead.php',
        'Ofnoacomps_CRM_Customer'   => 'includes/class-customer.php',
        'Ofnoacomps_CRM_Deal'       => 'includes/class-deal.php',
        'Ofnoacomps_CRM_Pipeline'   => 'includes/class-pipeline.php',
        'Ofnoacomps_CRM_Activity'   => 'includes/class-activity.php',
        'Ofnoacomps_CRM_Reports'    => 'includes/class-reports.php',
        'Ofnoacomps_CRM_Admin'      => 'admin/class-admin.php',
        'Ofnoacomps_CRM_REST_API'   => 'api/class-rest-api.php',
        'Ofnoacomps_CRM_API_Keys'   => 'includes/class-api-keys.php',
        'Ofnoacomps_CRM_Analytics'  => 'includes/class-analytics.php',
    ];

    if (isset($map[$class])) {
        require_once OFNOACOMPS_CRM_PLUGIN_DIR . $map[$class];
    }
});

// Auto-updater — must be loaded before plugins_loaded so filters are registered in time
require_once OFNOACOMPS_CRM_PLUGIN_DIR . 'includes/class-github-updater.php';
new Ofnoacomps_GitHub_Updater( __FILE__, 'ofnoacomps-crm', OFNOACOMPS_CRM_VERSION );

// Activation / Deactivation / Uninstall
register_activation_hook(__FILE__,   ['Ofnoacomps_CRM_Database', 'install']);
register_deactivation_hook(__FILE__, ['Ofnoacomps_CRM_Database', 'deactivate']);
register_uninstall_hook(__FILE__,    ['Ofnoacomps_CRM_Database', 'uninstall']);

/**
 * Bootstrap the plugin.
 */
function ofnoacomps_crm_init() {
    // Admin
    if (is_admin()) {
        new Ofnoacomps_CRM_Admin();
    }

    // REST API
    add_action('rest_api_init', function () {
        $api = new Ofnoacomps_CRM_REST_API();
        $api->register_routes();
    });

    // Frontend tracker (enqueue on all pages)
    add_action('wp_enqueue_scripts', 'ofnoacomps_crm_enqueue_tracker');

    // Hook into Contact Form 7
    add_action('wpcf7_mail_sent', 'ofnoacomps_crm_capture_cf7_lead');

    // Hook into WPForms
    add_action('wpforms_process_complete', 'ofnoacomps_crm_capture_wpforms_lead', 10, 4);


    // Analytics AJAX — works for logged-in AND logged-out visitors
    add_action('wp_ajax_nopriv_ofnoacomps_track_pageview', 'ofnoacomps_crm_ajax_track_pageview');
    add_action('wp_ajax_ofnoacomps_track_pageview',        'ofnoacomps_crm_ajax_track_pageview');
    add_action('wp_ajax_nopriv_ofnoacomps_track_event',    'ofnoacomps_crm_ajax_track_event');
    add_action('wp_ajax_ofnoacomps_track_event',           'ofnoacomps_crm_ajax_track_event');
    // Generic hook — other plugins can call this
    add_action('ofnoacomps_crm_capture_lead', ['Ofnoacomps_CRM_Lead', 'capture'], 10, 1);
}
add_action('plugins_loaded', 'ofnoacomps_crm_init');
add_action('plugins_loaded', ['Ofnoacomps_CRM_Database', 'maybe_upgrade'], 5);

/**
 * Enqueue the UTM tracker script on the frontend.
 */
function ofnoacomps_crm_enqueue_tracker() {
    wp_enqueue_script(
        'ofnoacomps-crm-tracker',
        OFNOACOMPS_CRM_PLUGIN_URL . 'assets/tracker.js',
        [],
        OFNOACOMPS_CRM_VERSION,
        true
    );
    wp_localize_script('ofnoacomps-crm-tracker', 'ofnoacompsCRM', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ofnoacomps_crm_nonce'),
        'siteUrl' => get_site_url(),
    ]);
}

/**
 * Capture a lead from Contact Form 7.
 *
 * @param object $contact_form
 */
function ofnoacomps_crm_capture_cf7_lead($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $posted  = $submission->get_posted_data();
    $tracker = isset($_COOKIE['ofnoacomps_crm_tracker'])
        ? json_decode(stripslashes($_COOKIE['ofnoacomps_crm_tracker']), true)
        : [];

    $name_parts = explode(' ', sanitize_text_field(isset($posted['your-name']) ? $posted['your-name'] : ''), 2);

    Ofnoacomps_CRM_Lead::create([
        'first_name'   => isset($name_parts[0]) ? $name_parts[0] : '',
        'last_name'    => isset($name_parts[1]) ? $name_parts[1] : '',
        'email'        => sanitize_email(isset($posted['your-email']) ? $posted['your-email'] : ''),
        'phone'        => sanitize_text_field(isset($posted['your-phone']) ? $posted['your-phone'] : (isset($posted['tel-788']) ? $posted['tel-788'] : '')),
        'message'      => sanitize_textarea_field(isset($posted['your-message']) ? $posted['your-message'] : ''),
        'form_id'      => $contact_form->id(),
        'form_name'    => $contact_form->title(),
        'page_url'     => sanitize_url(isset($tracker['page_url']) ? $tracker['page_url'] : wp_get_referer()),
        'source'       => sanitize_text_field(isset($tracker['source']) ? $tracker['source'] : 'direct'),
        'medium'       => sanitize_text_field(isset($tracker['medium']) ? $tracker['medium'] : ''),
        'campaign'     => sanitize_text_field(isset($tracker['campaign']) ? $tracker['campaign'] : ''),
        'utm_term'     => sanitize_text_field(isset($tracker['utm_term']) ? $tracker['utm_term'] : ''),
        'utm_content'  => sanitize_text_field(isset($tracker['utm_content']) ? $tracker['utm_content'] : ''),
        'referrer'     => sanitize_url(isset($tracker['referrer']) ? $tracker['referrer'] : ''),
        'landing_page' => sanitize_url(isset($tracker['landing_page']) ? $tracker['landing_page'] : ''),
        'device_type'  => sanitize_text_field(isset($tracker['device_type']) ? $tracker['device_type'] : ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
}

/**
 * Capture a lead from WPForms.
 *
 * @param array $fields
 * @param array $entry
 * @param array $form_data
 * @param int   $entry_id
 */
function ofnoacomps_crm_capture_wpforms_lead($fields, $entry, $form_data, $entry_id) {
    $tracker = isset($_COOKIE['ofnoacomps_crm_tracker'])
        ? json_decode(stripslashes($_COOKIE['ofnoacomps_crm_tracker']), true)
        : [];
    $email = $phone = $name = '';

    foreach ($fields as $field) {
        if (in_array($field['type'], ['email'])) $email = $field['value'];
        if (in_array($field['type'], ['phone'])) $phone = $field['value'];
        if (in_array($field['type'], ['name'])) $name = isset($field['value_raw']) ? $field['value_raw'] : $field['value'];
    }

    $name_parts = explode(' ', sanitize_text_field($name), 2);

    Ofnoacomps_CRM_Lead::create([
        'first_name'   => isset($name_parts[0]) ? $name_parts[0] : '',
        'last_name'    => isset($name_parts[1]) ? $name_parts[1] : '',
        'email'        => sanitize_email($email),
        'phone'        => sanitize_text_field($phone),
        'form_id'      => $form_data['id'],
        'form_name'    => isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : '',
        'source'       => sanitize_text_field(isset($tracker['source']) ? $tracker['source'] : 'direct'),
        'medium'       => sanitize_text_field(isset($tracker['medium']) ? $tracker['medium'] : ''),
        'campaign'     => sanitize_text_field(isset($tracker['campaign']) ? $tracker['campaign'] : ''),
        'utm_term'     => sanitize_text_field(isset($tracker['utm_term']) ? $tracker['utm_term'] : ''),
        'utm_content'  => sanitize_text_field(isset($tracker['utm_content']) ? $tracker['utm_content'] : ''),
        'referrer'     => sanitize_url(isset($tracker['referrer']) ? $tracker['referrer'] : ''),
        'landing_page' => sanitize_url(isset($tracker['landing_page']) ? $tracker['landing_page'] : ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
}

/**
 * Get visitor IP address.
 *
 * @return string
 */
function ofnoacomps_crm_get_ip() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return sanitize_text_field(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '';
}
// ── Analytics AJAX handlers ────────────────────────────────────────────────

/**
 * Record a pageview from the frontend tracker.
 */
function ofnoacomps_crm_ajax_track_pageview() {
    global $wpdb;
    $table = $wpdb->prefix . 'ofnoacomps_pageviews';
    $wpdb->insert($table, [
        'session_id'   => sanitize_text_field(isset($_POST['session_id'])   ? $_POST['session_id']   : ''),
        'page_url'     => esc_url_raw(isset($_POST['page_url'])              ? $_POST['page_url']     : ''),
        'page_title'   => sanitize_text_field(isset($_POST['page_title'])   ? $_POST['page_title']   : ''),
        'referrer'     => esc_url_raw(isset($_POST['referrer'])              ? $_POST['referrer']     : ''),
        'source'       => sanitize_text_field(isset($_POST['source'])       ? $_POST['source']       : 'direct'),
        'medium'       => sanitize_text_field(isset($_POST['medium'])       ? $_POST['medium']       : ''),
        'campaign'     => sanitize_text_field(isset($_POST['campaign'])     ? $_POST['campaign']     : ''),
        'utm_term'     => sanitize_text_field(isset($_POST['utm_term'])     ? $_POST['utm_term']     : ''),
        'utm_content'  => sanitize_text_field(isset($_POST['utm_content'])  ? $_POST['utm_content']  : ''),
        'landing_page' => esc_url_raw(isset($_POST['landing_page'])         ? $_POST['landing_page'] : ''),
        'device_type'  => sanitize_text_field(isset($_POST['device_type'])  ? $_POST['device_type']  : ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
    wp_send_json_success(['ok' => true]);
}

/**
 * Record a click / button event from the frontend tracker.
 */
function ofnoacomps_crm_ajax_track_event() {
    global $wpdb;
    $table = $wpdb->prefix . 'ofnoacomps_events';
    $wpdb->insert($table, [
        'session_id'  => sanitize_text_field(isset($_POST['session_id'])  ? $_POST['session_id']  : ''),
        'event_type'  => sanitize_text_field(isset($_POST['event_type'])  ? $_POST['event_type']  : ''),
        'event_label' => sanitize_text_field(isset($_POST['event_label']) ? $_POST['event_label'] : ''),
        'event_value' => sanitize_text_field(isset($_POST['event_value']) ? $_POST['event_value'] : ''),
        'page_url'    => esc_url_raw(isset($_POST['page_url'])             ? $_POST['page_url']    : ''),
        'source'      => sanitize_text_field(isset($_POST['source'])      ? $_POST['source']      : 'direct'),
        'medium'      => sanitize_text_field(isset($_POST['medium'])      ? $_POST['medium']      : ''),
        'campaign'    => sanitize_text_field(isset($_POST['campaign'])    ? $_POST['campaign']    : ''),
        'device_type' => sanitize_text_field(isset($_POST['device_type']) ? $_POST['device_type'] : ''),
        'ip_address'  => ofnoacomps_crm_get_ip(),
    ]);
    wp_send_json_success(['ok' => true]);
}