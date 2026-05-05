<?php
/**
 * Plugin Name: Ofnoacomps Post Badges
 * Plugin URI:  https://www.ofnoacomps.co.il
 * Description: מוסיף Badge (תווית) על תמונת הפוסט עם שליטה מלאה על עיצוב, מיקום וצבעים. עובד עם Elementor Loop, ACF ותמות קלאסיות.
 * Version:     1.0.7
 * Author:      Ofnoacomps
 * Text Domain: ofnoacomps-postbadges
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ─────────────────────────────────────────────────────────────────
if ( ! defined( 'OPB_VERSION' ) ) {
    define( 'OPB_VERSION', '1.0.7' );
    define( 'OPB_DIR',    plugin_dir_path( __FILE__ ) );
    define( 'OPB_URL',    plugin_dir_url( __FILE__ ) );
    define( 'OPB_FILE',   __FILE__ );
    define( 'OPB_OPTION', 'opb_settings' );
}

// ── Default settings ──────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_defaults' ) ) {
    function opb_defaults() {
        return array(
            'enabled'       => 1,
            'default_text'  => "\xd7\x97\xd7\x93\xd7\xa9!",   // חדש!
            'type'          => 'square',
            'position'      => 'top-right',
            'bg_color'      => '#e74c3c',
            'text_color'    => '#ffffff',
            'font_size'     => 14,
            'text_shadow'   => 1,
            'shadow_color'  => 'rgba(0,0,0,0.35)',
            'border_radius' => 6,
            'filter_mode'   => 'all',
            'filter_terms'  => array(),
            // ACF / custom image fields (comma-separated meta keys)
            // Leave empty to auto-detect any custom image field.
            'acf_fields'    => '',
        );
    }
}

if ( ! function_exists( 'opb_get_settings' ) ) {
    function opb_get_settings() {
        return wp_parse_args( (array) get_option( OPB_OPTION, array() ), opb_defaults() );
    }
}

// Helper: returns array of configured ACF field names (from settings)
if ( ! function_exists( 'opb_acf_field_names' ) ) {
    function opb_acf_field_names( $s = null ) {
        if ( is_null( $s ) ) $s = opb_get_settings();
        $raw = isset( $s['acf_fields'] ) ? trim( $s['acf_fields'] ) : '';
        if ( $raw === '' ) return array();
        return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
    }
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_boot' ) ) {
    function opb_boot() {
        if ( ! class_exists( 'OPB_GitHub_Updater' ) ) {
            require_once OPB_DIR . 'includes/class-github-updater.php';
        }
        new OPB_GitHub_Updater( OPB_FILE, 'ofnoacomps-postBadges', OPB_VERSION );

        if ( ! class_exists( 'OPB_Badge_Renderer' ) ) {
            require_once OPB_DIR . 'includes/class-badge-renderer.php';
        }
        if ( ! class_exists( 'OPB_Meta_Box' ) ) {
            require_once OPB_DIR . 'includes/class-meta-box.php';
        }
        new OPB_Meta_Box();

        if ( is_admin() ) {
            if ( ! class_exists( 'OPB_Admin' ) ) {
                require_once OPB_DIR . 'admin/class-admin.php';
            }
            new OPB_Admin();
        }
    }
    add_action( 'plugins_loaded', 'opb_boot', 1 );
}

// ── Frontend CSS ──────────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_enqueue_frontend' ) ) {
    function opb_enqueue_frontend() {
        $s = opb_get_settings();
        if ( empty( $s['enabled'] ) ) return;
        wp_enqueue_style( 'opb-frontend', OPB_URL . 'assets/frontend.css', array(), OPB_VERSION );
    }
    add_action( 'wp_enqueue_scripts', 'opb_enqueue_frontend' );
}

// ── Core badge filter: wp_get_attachment_image ────────────────────────────────
//
// Fires for every wp_get_attachment_image() call:
//   • Classic themes / get_the_post_thumbnail()
//   • Elementor Loop Grid (Post Thumbnail widget + Image widget + dynamic tag)
//   • ACF image fields rendered via Elementor
//   • Gutenberg featured image block
//
// Post-ID resolution order:
//   1. Fast path — current WP loop post whose _thumbnail_id == attachment_id
//   2. Featured image meta lookup  (_thumbnail_id)
//   3. ACF / custom field lookup:
//        a. Specific fields from settings  (precise, fast)
//        b. Auto-detect — any non-internal meta_value == attachment_id
//   All results cached per request.
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_wrap_attachment_image' ) ) {
    function opb_wrap_attachment_image( $html, $attachment_id, $size, $icon, $attr ) {
        if ( is_admin() )                                        return $html;
        if ( empty( $html ) )                                    return $html;
        if ( strpos( $html, 'opb-wrap' ) !== false )             return $html;

        $s = opb_get_settings();
        if ( empty( $s['enabled'] ) )                            return $html;

        $attachment_id = (int) $attachment_id;
        if ( ! $attachment_id )                                  return $html;

        static $cache = array();

        // ── 1. Fast path: current WP loop post ───────────────────────────────
        $post_id    = 0;
        $current_id = (int) get_the_ID();
        if ( $current_id && (int) get_post_thumbnail_id( $current_id ) === $attachment_id ) {
            $post_id = $current_id;
        }

        // ── 2 & 3. DB lookup (cached) ─────────────────────────────────────────
        if ( ! $post_id ) {
            if ( ! array_key_exists( $attachment_id, $cache ) ) {
                global $wpdb;
                $found = 0;

                // 2. Featured image
                $found = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key = '_thumbnail_id'
                       AND meta_value = %d
                     LIMIT 1",
                    $attachment_id
                ) );

                // 3a. Configured ACF field names (precise)
                if ( ! $found ) {
                    $acf_fields = opb_acf_field_names( $s );
                    if ( ! empty( $acf_fields ) ) {
                        $placeholders = implode( ', ', array_fill( 0, count( $acf_fields ), '%s' ) );
                        $args         = array_merge( $acf_fields, array( (string) $attachment_id ) );
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        $found = (int) $wpdb->get_var( $wpdb->prepare(
                            "SELECT post_id FROM {$wpdb->postmeta}
                             WHERE meta_key IN ($placeholders)
                               AND meta_value = %s
                             LIMIT 1",
                            $args
                        ) );
                    }
                }

                // 3b. Auto-detect: any non-internal meta field with this attachment ID
                if ( ! $found ) {
                    $found = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->postmeta}
                         WHERE meta_value = %s
                           AND meta_key NOT LIKE '\_%%'
                         LIMIT 1",
                        (string) $attachment_id
                    ) );
                }

                $cache[ $attachment_id ] = $found;
            }
            $post_id = $cache[ $attachment_id ];
        }

        if ( ! $post_id )                                        return $html;

        // Per-post disable flag
        if ( get_post_meta( $post_id, '_opb_disabled', true ) ) return $html;

        // Category / Tag filter
        $filter_mode  = isset( $s['filter_mode'] ) ? $s['filter_mode'] : 'all';
        $filter_terms = isset( $s['filter_terms'] ) ? (array) $s['filter_terms'] : array();
        if ( $filter_mode !== 'all' && ! empty( $filter_terms ) ) {
            $term_ids = array_map( 'intval', $filter_terms );
            if ( $filter_mode === 'categories' ) {
                $cats = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
                if ( empty( array_intersect( $term_ids, (array) $cats ) ) ) return $html;
            } elseif ( $filter_mode === 'tags' ) {
                $tags = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
                if ( empty( array_intersect( $term_ids, (array) $tags ) ) ) return $html;
            }
        }

        if ( ! class_exists( 'OPB_Badge_Renderer' ) ) {
            require_once ( defined( 'OPB_DIR' ) ? OPB_DIR : plugin_dir_path( __FILE__ ) )
                . 'includes/class-badge-renderer.php';
        }

        return OPB_Badge_Renderer::wrap( $html, $post_id, $s );
    }
    add_filter( 'wp_get_attachment_image', 'opb_wrap_attachment_image', 20, 5 );
}

// ── Activation ───────────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_activate' ) ) {
    function opb_activate() {
        if ( false === get_option( OPB_OPTION ) ) {
            add_option( OPB_OPTION, opb_defaults() );
        }
    }
    register_activation_hook( OPB_FILE, 'opb_activate' );
}
