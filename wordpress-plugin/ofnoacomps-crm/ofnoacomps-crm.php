<?php
/**
 * Plugin Name: Ofnoacomps CRM
 * Plugin URI:  https://www.ofnoacomps.co.il
 * Description: מערכת CRM מלאה לניהול לידים, לקוחות, עסקאות ודוחות עם מעקב מקור תנועה.
 * Version:     1.4.1
 * Author:      Ofnoacomps
 * Text Domain: ofnoacomps-crm
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('OFNOACOMPS_CRM_VERSION', '1.4.1');
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

// Auto-updater
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
    if (is_admin()) {
        new Ofnoacomps_CRM_Admin();
    }

    add_action('rest_api_init', function () {
        $api = new Ofnoacomps_CRM_REST_API();
        $api->register_routes();
    });

    add_action('wp_enqueue_scripts', 'ofnoacomps_crm_enqueue_tracker');

    // Contact Form 7
    add_action('wpcf7_mail_sent', 'ofnoacomps_crm_capture_cf7_lead');

    // WPForms
    add_action('wpforms_process_complete', 'ofnoacomps_crm_capture_wpforms_lead', 10, 4);

    // Elementor Pro Forms
    add_action('elementor_pro/forms/new_record', 'ofnoacomps_crm_capture_elementor_lead', 10, 2);

    // Gravity Forms
    add_action('gform_after_submission', 'ofnoacomps_crm_capture_gravity_lead', 10, 2);

    // Analytics AJAX
    add_action('wp_ajax_nopriv_ofnoacomps_track_pageview', 'ofnoacomps_crm_ajax_track_pageview');
    add_action('wp_ajax_ofnoacomps_track_pageview',        'ofnoacomps_crm_ajax_track_pageview');
    add_action('wp_ajax_nopriv_ofnoacomps_track_event',    'ofnoacomps_crm_ajax_track_event');
    add_action('wp_ajax_ofnoacomps_track_event',           'ofnoacomps_crm_ajax_track_event');

    // Generic hook
    add_action('ofnoacomps_crm_capture_lead', ['Ofnoacomps_CRM_Lead', 'capture'], 10, 1);
}
add_action('plugins_loaded', 'ofnoacomps_crm_init');
add_action('plugins_loaded', ['Ofnoacomps_CRM_Database', 'maybe_upgrade'], 5);

// ── Tracker ───────────────────────────────────────────────────────────────────

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

// ── Lead capture helpers ──────────────────────────────────────────────────────

function ofnoacomps_crm_get_tracker(): array {
    return isset($_COOKIE['ofnoacomps_crm_tracker'])
        ? (array) json_decode(stripslashes($_COOKIE['ofnoacomps_crm_tracker']), true)
        : [];
}

function ofnoacomps_crm_get_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return sanitize_text_field(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '';
}

// ── Contact Form 7 ────────────────────────────────────────────────────────────

function ofnoacomps_crm_capture_cf7_lead($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $posted  = $submission->get_posted_data();
    $tracker = ofnoacomps_crm_get_tracker();
    $parts   = explode(' ', sanitize_text_field($posted['your-name'] ?? ''), 2);

    Ofnoacomps_CRM_Lead::create([
        'first_name'   => $parts[0] ?? '',
        'last_name'    => $parts[1] ?? '',
        'email'        => sanitize_email($posted['your-email'] ?? ''),
        'phone'        => sanitize_text_field($posted['your-phone'] ?? $posted['tel-788'] ?? ''),
        'message'      => sanitize_textarea_field($posted['your-message'] ?? ''),
        'form_id'      => $contact_form->id(),
        'form_name'    => $contact_form->title(),
        'page_url'     => sanitize_url($tracker['page_url'] ?? wp_get_referer()),
        'source'       => sanitize_text_field($tracker['source']       ?? 'direct'),
        'medium'       => sanitize_text_field($tracker['medium']       ?? ''),
        'campaign'     => sanitize_text_field($tracker['campaign']     ?? ''),
        'utm_term'     => sanitize_text_field($tracker['utm_term']     ?? ''),
        'utm_content'  => sanitize_text_field($tracker['utm_content']  ?? ''),
        'referrer'     => sanitize_url($tracker['referrer']            ?? ''),
        'landing_page' => sanitize_url($tracker['landing_page']        ?? ''),
        'device_type'  => sanitize_text_field($tracker['device_type']  ?? ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
}

// ── WPForms ───────────────────────────────────────────────────────────────────

function ofnoacomps_crm_capture_wpforms_lead($fields, $entry, $form_data, $entry_id) {
    $tracker = ofnoacomps_crm_get_tracker();
    $email = $phone = $name = '';
    foreach ($fields as $field) {
        if ($field['type'] === 'email') $email = $field['value'];
        if ($field['type'] === 'phone') $phone = $field['value'];
        if ($field['type'] === 'name')  $name  = $field['value_raw'] ?? $field['value'];
    }
    $parts = explode(' ', sanitize_text_field($name), 2);

    Ofnoacomps_CRM_Lead::create([
        'first_name'   => $parts[0] ?? '',
        'last_name'    => $parts[1] ?? '',
        'email'        => sanitize_email($email),
        'phone'        => sanitize_text_field($phone),
        'form_id'      => $form_data['id'],
        'form_name'    => $form_data['settings']['form_title'] ?? '',
        'source'       => sanitize_text_field($tracker['source']      ?? 'direct'),
        'medium'       => sanitize_text_field($tracker['medium']      ?? ''),
        'campaign'     => sanitize_text_field($tracker['campaign']    ?? ''),
        'utm_term'     => sanitize_text_field($tracker['utm_term']    ?? ''),
        'utm_content'  => sanitize_text_field($tracker['utm_content'] ?? ''),
        'referrer'     => sanitize_url($tracker['referrer']           ?? ''),
        'landing_page' => sanitize_url($tracker['landing_page']       ?? ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
}

// ── Elementor Pro Forms ───────────────────────────────────────────────────────

function ofnoacomps_crm_capture_elementor_lead($record, $ajax_handler) {
    $tracker    = ofnoacomps_crm_get_tracker();
    $raw_fields = $record->get('fields');

    // Flatten fields to key => value
    $f = [];
    foreach ($raw_fields as $id => $field) {
        $f[$id]               = $field['value'];
        // Also index by common label slugs
        $slug = sanitize_title($field['title'] ?? $id);
        $f[$slug] = $field['value'];
    }

    // Extract common field names (supports Hebrew & English field IDs)
    $name    = $f['name']      ?? $f['your-name']  ?? $f['full-name'] ?? $f['full_name'] ?? $f['שם'] ?? '';
    $email   = $f['email']     ?? $f['your-email'] ?? $f['mail']      ?? $f['אימייל']    ?? '';
    $phone   = $f['phone']     ?? $f['tel']        ?? $f['your-phone']?? $f['mobile']    ?? $f['טלפון'] ?? '';
    $message = $f['message']   ?? $f['your-message']?? $f['msg']      ?? $f['הודעה']    ?? '';

    // If a "name" field wasn't found, try first_name + last_name fields
    if (empty($name)) {
        $name = trim(($f['first_name'] ?? $f['first-name'] ?? '') . ' ' . ($f['last_name'] ?? $f['last-name'] ?? ''));
    }

    $parts = explode(' ', sanitize_text_field($name), 2);

    $form_settings = $record->get('form_settings');

    Ofnoacomps_CRM_Lead::create([
        'first_name'   => $parts[0] ?? '',
        'last_name'    => $parts[1] ?? '',
        'email'        => sanitize_email($email),
        'phone'        => sanitize_text_field($phone),
        'message'      => sanitize_textarea_field($message),
        'form_id'      => $form_settings['id']        ?? '',
        'form_name'    => $form_settings['form_name'] ?? 'Elementor Form',
        'source'       => sanitize_text_field($tracker['source']      ?? 'direct'),
        'medium'       => sanitize_text_field($tracker['medium']      ?? ''),
        'campaign'     => sanitize_text_field($tracker['campaign']    ?? ''),
        'utm_term'     => sanitize_text_field($tracker['utm_term']    ?? ''),
        'utm_content'  => sanitize_text_field($tracker['utm_content'] ?? ''),
        'referrer'     => sanitize_url($tracker['referrer']           ?? ''),
        'landing_page' => sanitize_url($tracker['landing_page']       ?? ''),
        'device_type'  => sanitize_text_field($tracker['device_type'] ?? ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
}

// ── Gravity Forms ─────────────────────────────────────────────────────────────

function ofnoacomps_crm_capture_gravity_lead($entry, $form) {
    $tracker = ofnoacomps_crm_get_tracker();
    $email = $phone = $name = '';

    foreach ($form['fields'] as $field) {
        $val = rgar($entry, (string)$field->id);
        if ($field->type === 'email')  $email = $val;
        if ($field->type === 'phone')  $phone = $val;
        if ($field->type === 'name')   $name  = $val;
        if ($field->type === 'text' && empty($name)) $name = $val;
    }

    $parts = explode(' ', sanitize_text_field($name), 2);

    Ofnoacomps_CRM_Lead::create([
        'first_name'   => $parts[0] ?? '',
        'last_name'    => $parts[1] ?? '',
        'email'        => sanitize_email($email),
        'phone'        => sanitize_text_field($phone),
        'form_id'      => $form['id'],
        'form_name'    => $form['title'] ?? 'Gravity Form',
        'source'       => sanitize_text_field($tracker['source']      ?? 'direct'),
        'medium'       => sanitize_text_field($tracker['medium']      ?? ''),
        'campaign'     => sanitize_text_field($tracker['campaign']    ?? ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
}

// ── Analytics AJAX ────────────────────────────────────────────────────────────

function ofnoacomps_crm_ajax_track_pageview() {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ofnoacomps_pageviews', [
        'session_id'   => sanitize_text_field($_POST['session_id']   ?? ''),
        'page_url'     => esc_url_raw($_POST['page_url']             ?? ''),
        'page_title'   => sanitize_text_field($_POST['page_title']   ?? ''),
        'referrer'     => esc_url_raw($_POST['referrer']             ?? ''),
        'source'       => sanitize_text_field($_POST['source']       ?? 'direct'),
        'medium'       => sanitize_text_field($_POST['medium']       ?? ''),
        'campaign'     => sanitize_text_field($_POST['campaign']     ?? ''),
        'utm_term'     => sanitize_text_field($_POST['utm_term']     ?? ''),
        'utm_content'  => sanitize_text_field($_POST['utm_content']  ?? ''),
        'landing_page' => esc_url_raw($_POST['landing_page']         ?? ''),
        'device_type'  => sanitize_text_field($_POST['device_type']  ?? ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ]);
    wp_send_json_success(['ok' => true]);
}

function ofnoacomps_crm_ajax_track_event() {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ofnoacomps_events', [
        'session_id'  => sanitize_text_field($_POST['session_id']  ?? ''),
        'event_type'  => sanitize_text_field($_POST['event_type']  ?? ''),
        'event_label' => sanitize_text_field($_POST['event_label'] ?? ''),
        'event_value' => sanitize_text_field($_POST['event_value'] ?? ''),
        'page_url'    => esc_url_raw($_POST['page_url']            ?? ''),
        'source'      => sanitize_text_field($_POST['source']      ?? 'direct'),
        'medium'      => sanitize_text_field($_POST['medium']      ?? ''),
        'campaign'    => sanitize_text_field($_POST['campaign']    ?? ''),
        'device_type' => sanitize_text_field($_POST['device_type'] ?? ''),
        'ip_address'  => ofnoacomps_crm_get_ip(),
    ]);
    wp_send_json_success(['ok' => true]);
}
