<?php
/**
 * Plugin Name: Ofnoacomps CRM
 * Plugin URI:  https://www.ofnoacomps.co.il
 * Description: מערכת CRM מלאה לניהול לידים, לקוחות, עסקאות ודוחות עם מעקב מקור תנועה.
 * Version:     1.4.2
 * Author:      Ofnoacomps
 * Text Domain: ofnoacomps-crm
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('OFNOACOMPS_CRM_VERSION', '1.4.2');
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

// ── Shared helpers ────────────────────────────────────────────────────────────

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

/**
 * Build the tracker-cookie portion of a lead row.
 */
function ofnoacomps_crm_tracker_data(array $tracker = []): array {
    if (empty($tracker)) {
        $tracker = ofnoacomps_crm_get_tracker();
    }
    return [
        'source'       => sanitize_text_field($tracker['source']       ?? 'direct'),
        'medium'       => sanitize_text_field($tracker['medium']       ?? ''),
        'campaign'     => sanitize_text_field($tracker['campaign']     ?? ''),
        'utm_term'     => sanitize_text_field($tracker['utm_term']     ?? ''),
        'utm_content'  => sanitize_text_field($tracker['utm_content']  ?? ''),
        'referrer'     => sanitize_url($tracker['referrer']            ?? ''),
        'landing_page' => sanitize_url($tracker['landing_page']        ?? ''),
        'device_type'  => sanitize_text_field($tracker['device_type']  ?? ''),
        'ip_address'   => ofnoacomps_crm_get_ip(),
    ];
}

/**
 * Split a full name string into [first_name, last_name].
 */
function ofnoacomps_crm_split_name(string $name): array {
    $parts = explode(' ', sanitize_text_field(trim($name)), 2);
    return [
        'first_name' => $parts[0] ?? '',
        'last_name'  => $parts[1] ?? '',
    ];
}

// ── Contact Form 7 ────────────────────────────────────────────────────────────
// Uses type-based detection + common field-name fallbacks.
// Works with any CF7 form regardless of field naming.

function ofnoacomps_crm_capture_cf7_lead($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $posted  = $submission->get_posted_data();
    $tracker = ofnoacomps_crm_get_tracker();

    // ── Name ─────────────────────────────────────────────────────────────────
    // Try combined name field first, then separate first/last
    $first_name = '';
    $last_name  = '';
    $combined   = $posted['your-name'] ?? $posted['name'] ?? $posted['full-name'] ?? $posted['full_name'] ?? '';
    if ($combined) {
        $name_parts = ofnoacomps_crm_split_name($combined);
        $first_name = $name_parts['first_name'];
        $last_name  = $name_parts['last_name'];
    } else {
        $first_name = sanitize_text_field($posted['first-name'] ?? $posted['first_name'] ?? $posted['fname'] ?? '');
        $last_name  = sanitize_text_field($posted['last-name']  ?? $posted['last_name']  ?? $posted['lname'] ?? '');
    }

    // ── Email ─────────────────────────────────────────────────────────────────
    // Try common field names, then auto-detect any field that is a valid email
    $email = '';
    foreach (['your-email', 'email', 'mail', 'e-mail', 'email-address'] as $key) {
        if (!empty($posted[$key]) && is_email($posted[$key])) {
            $email = sanitize_email($posted[$key]);
            break;
        }
    }
    if (empty($email)) {
        foreach ($posted as $val) {
            if (is_string($val) && is_email($val)) {
                $email = sanitize_email($val);
                break;
            }
        }
    }

    // ── Phone ─────────────────────────────────────────────────────────────────
    // Try common field names, then auto-detect any field that looks like a phone
    $phone = '';
    $phone_keys = ['your-phone', 'phone', 'tel', 'mobile', 'phone-number', 'telephone',
                   'cell', 'cellphone', 'tel-788', 'phone-791', 'mobile-number'];
    foreach ($phone_keys as $key) {
        if (!empty($posted[$key])) {
            $phone = sanitize_text_field($posted[$key]);
            break;
        }
    }
    if (empty($phone)) {
        foreach ($posted as $val) {
            if (is_string($val) && preg_match('/^[\d\+\-\(\)\s]{7,20}$/', trim($val))) {
                $phone = sanitize_text_field(trim($val));
                break;
            }
        }
    }

    // ── Message ───────────────────────────────────────────────────────────────
    $message = sanitize_textarea_field(
        $posted['your-message'] ?? $posted['message'] ?? $posted['msg'] ??
        $posted['textarea-your-message'] ?? $posted['text-your-message'] ?? ''
    );

    Ofnoacomps_CRM_Lead::create(array_merge(ofnoacomps_crm_tracker_data($tracker), [
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'email'        => $email,
        'phone'        => $phone,
        'message'      => $message,
        'form_id'      => $contact_form->id(),
        'form_name'    => $contact_form->title(),
        'page_url'     => sanitize_url($tracker['page_url'] ?? wp_get_referer()),
    ]));
}

// ── WPForms ───────────────────────────────────────────────────────────────────
// Matches by field type (email, phone, name, textarea/text-multiline).
// Falls back to text fields for name when no dedicated name field exists.

function ofnoacomps_crm_capture_wpforms_lead($fields, $entry, $form_data, $entry_id) {
    $tracker    = ofnoacomps_crm_get_tracker();
    $first_name = '';
    $last_name  = '';
    $email      = '';
    $phone      = '';
    $message    = '';

    foreach ($fields as $field) {
        $type  = strtolower($field['type'] ?? '');
        $value = $field['value'] ?? '';

        switch ($type) {
            case 'email':
                if (empty($email)) $email = sanitize_email($value);
                break;

            case 'phone':
                if (empty($phone)) $phone = sanitize_text_field($value);
                break;

            case 'name':
                // WPForms Name field can be simple (value) or compound (value_raw sub-fields)
                if (!empty($field['first'])) {
                    $first_name = sanitize_text_field($field['first']);
                    $last_name  = sanitize_text_field($field['last'] ?? '');
                } else {
                    $parts = ofnoacomps_crm_split_name($field['value_raw'] ?? $value);
                    $first_name = $parts['first_name'];
                    $last_name  = $parts['last_name'];
                }
                break;

            case 'textarea':
            case 'paragraph-text':
                if (empty($message)) $message = sanitize_textarea_field($value);
                break;

            case 'text':
                // Use first text field as name if no name field found yet
                if (empty($first_name)) {
                    $parts = ofnoacomps_crm_split_name($value);
                    $first_name = $parts['first_name'];
                    $last_name  = $parts['last_name'];
                }
                break;
        }
    }

    Ofnoacomps_CRM_Lead::create(array_merge(ofnoacomps_crm_tracker_data($tracker), [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
        'message'    => $message,
        'form_id'    => $form_data['id'] ?? '',
        'form_name'  => $form_data['settings']['form_title'] ?? '',
    ]));
}

// ── Elementor Pro Forms ───────────────────────────────────────────────────────
// PRIMARY: match by field type (email, tel, name, textarea).
// FALLBACK: match by field ID or sanitized title (handles Hebrew via mb_strtolower).
// This fixes the bug where sanitize_title() returned '' for Hebrew field titles.

function ofnoacomps_crm_capture_elementor_lead($record, $ajax_handler) {
    $tracker    = ofnoacomps_crm_get_tracker();
    $raw_fields = $record->get('fields');

    $first_name = '';
    $last_name  = '';
    $email      = '';
    $phone      = '';
    $message    = '';

    // Known English & Hebrew aliases for each intent
    $name_ids    = ['name', 'full-name', 'full_name', 'your-name', 'fullname',
                    'first-name', 'first_name', 'fname', 'שם', 'שם פרטי', 'שם מלא'];
    $email_ids   = ['email', 'your-email', 'mail', 'e-mail', 'email-address',
                    'אימייל', 'מייל', 'דואר אלקטרוני'];
    $phone_ids   = ['phone', 'tel', 'telephone', 'mobile', 'your-phone', 'phone-number',
                    'cellphone', 'cell', 'טלפון', 'נייד', 'מספר טלפון', 'טל'];
    $message_ids = ['message', 'msg', 'your-message', 'text', 'content', 'body',
                    'הודעה', 'תוכן', 'פרטים', 'הערות'];

    foreach ($raw_fields as $field_id => $field) {
        $type    = strtolower($field['field_type'] ?? $field['type'] ?? '');
        $value   = $field['value'] ?? '';
        $raw_val = $field['raw_value'] ?? $value;
        $title   = strtolower($field['title'] ?? '');
        $id_low  = strtolower($field_id);

        // ── Match by Elementor field type (most reliable) ─────────────────
        if ($type === 'email' && empty($email)) {
            $email = sanitize_email($value);
            continue;
        }
        if ($type === 'tel' && empty($phone)) {
            $phone = sanitize_text_field($value);
            continue;
        }
        if ($type === 'textarea' && empty($message)) {
            $message = sanitize_textarea_field($value);
            continue;
        }
        if ($type === 'name' && empty($first_name)) {
            // Elementor "Name" field may have sub-fields
            if (!empty($field['sub_fields'])) {
                $first_name = sanitize_text_field($field['sub_fields']['first'] ?? $value);
                $last_name  = sanitize_text_field($field['sub_fields']['last']  ?? '');
            } else {
                $parts = ofnoacomps_crm_split_name($raw_val ?: $value);
                $first_name = $parts['first_name'];
                $last_name  = $parts['last_name'];
            }
            continue;
        }

        // ── Match by field ID or title (Hebrew-safe) ──────────────────────
        // Compare against lower-cased title and ID so Hebrew titles work
        $match_keys = array_unique([$id_low, $title]);

        foreach ($match_keys as $mk) {
            if (empty($mk)) continue;

            if (empty($email) && in_array($mk, $email_ids, true) && is_email($value)) {
                $email = sanitize_email($value);
                break;
            }
            if (empty($phone) && in_array($mk, $phone_ids, true) && !empty($value)) {
                $phone = sanitize_text_field($value);
                break;
            }
            if (empty($message) && in_array($mk, $message_ids, true) && !empty($value)) {
                $message = sanitize_textarea_field($value);
                break;
            }
            if (empty($first_name) && in_array($mk, $name_ids, true) && !empty($value)) {
                $parts = ofnoacomps_crm_split_name($value);
                $first_name = $parts['first_name'];
                $last_name  = $parts['last_name'];
                break;
            }
        }

        // ── Auto-detect by value pattern as last resort ───────────────────
        if (empty($email) && is_email($value)) {
            $email = sanitize_email($value);
        } elseif (empty($phone) && preg_match('/^[\d\+\-\(\)\s]{7,20}$/', trim($value))) {
            $phone = sanitize_text_field(trim($value));
        }
    }

    // If still no name, try separate first_name / last_name fields explicitly
    if (empty($first_name)) {
        foreach ($raw_fields as $field_id => $field) {
            $id_low = strtolower($field_id);
            $title  = strtolower($field['title'] ?? '');
            if (in_array($id_low, ['first-name','first_name','fname','שם פרטי'], true) ||
                in_array($title,  ['first-name','first_name','fname','שם פרטי'], true)) {
                $first_name = sanitize_text_field($field['value'] ?? '');
            }
            if (in_array($id_low, ['last-name','last_name','lname','שם משפחה'], true) ||
                in_array($title,  ['last-name','last_name','lname','שם משפחה'], true)) {
                $last_name = sanitize_text_field($field['value'] ?? '');
            }
        }
    }

    $form_settings = $record->get('form_settings');

    Ofnoacomps_CRM_Lead::create(array_merge(ofnoacomps_crm_tracker_data($tracker), [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
        'message'    => $message,
        'form_id'    => $form_settings['id']        ?? '',
        'form_name'  => $form_settings['form_name'] ?? 'Elementor Form',
    ]));
}

// ── Gravity Forms ─────────────────────────────────────────────────────────────
// Matches by field type. Also captures textarea/text for message.

function ofnoacomps_crm_capture_gravity_lead($entry, $form) {
    $tracker    = ofnoacomps_crm_get_tracker();
    $first_name = '';
    $last_name  = '';
    $email      = '';
    $phone      = '';
    $message    = '';

    foreach ($form['fields'] as $field) {
        $val  = rgar($entry, (string)$field->id);
        $type = strtolower($field->type ?? '');

        switch ($type) {
            case 'email':
                if (empty($email)) $email = sanitize_email($val);
                break;

            case 'phone':
                if (empty($phone)) $phone = sanitize_text_field($val);
                break;

            case 'name':
                // Gravity Forms Name field can be single or compound
                $fn = rgar($entry, $field->id . '.3');  // First
                $ln = rgar($entry, $field->id . '.6');  // Last
                if (!empty($fn) || !empty($ln)) {
                    $first_name = sanitize_text_field($fn);
                    $last_name  = sanitize_text_field($ln);
                } elseif (!empty($val) && empty($first_name)) {
                    $parts = ofnoacomps_crm_split_name($val);
                    $first_name = $parts['first_name'];
                    $last_name  = $parts['last_name'];
                }
                break;

            case 'textarea':
                if (empty($message)) $message = sanitize_textarea_field($val);
                break;

            case 'text':
                // Use first text field as name only if no name field found
                if (empty($first_name) && !empty($val)) {
                    $parts = ofnoacomps_crm_split_name($val);
                    $first_name = $parts['first_name'];
                    $last_name  = $parts['last_name'];
                }
                break;
        }
    }

    Ofnoacomps_CRM_Lead::create(array_merge(ofnoacomps_crm_tracker_data($tracker), [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
        'message'    => $message,
        'form_id'    => $form['id']    ?? '',
        'form_name'  => $form['title'] ?? 'Gravity Form',
    ]));
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
