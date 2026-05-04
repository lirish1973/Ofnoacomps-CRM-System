<?php
/**
 * Plugin Name: Ofnoacomps Post Badges
 * Plugin URI:  https://www.ofnoacomps.co.il
 * Description: מוסיף Badge (תווית) על תמונת הפוסט עם שליטה מלאה על עיצוב, מיקום וצבעים. עובד עם Elementor Loop, תמות קלאסיות ובלוקים.
 * Version:     1.0.5
 * Author:      Ofnoacomps
 * Text Domain: ofnoacomps-postbadges
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// ── Constants (guarded — safe against double-load) ───────────────────────────
if ( ! defined( 'OPB_VERSION' ) ) {
    define( 'OPB_VERSION', '1.0.5' );
    define( 'OPB_DIR',    plugin_dir_path( __FILE__ ) );
    define( 'OPB_URL',    plugin_dir_url( __FILE__ ) );
    define( 'OPB_FILE',   __FILE__ );
    define( 'OPB_OPTION', 'opb_settings' );
}

// ── Default settings ─────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_defaults' ) ) {
    function opb_defaults() {
        return array(
            'enabled'       => 1,
            'default_text'  => "\xd7\x97\xd7\x93\xd7\xa9!",
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
        );
    }
}

if ( ! function_exists( 'opb_get_settings' ) ) {
    function opb_get_settings() {
        return wp_parse_args( (array) get_option( OPB_OPTION, array() ), opb_defaults() );
    }
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_boot' ) ) {
    function opb_boot() {
        // Auto-updater
        if ( ! class_exists( 'OPB_GitHub_Updater' ) ) {
            require_once OPB_DIR . 'includes/class-github-updater.php';
        }
        new OPB_GitHub_Updater( OPB_FILE, 'ofnoacomps-postBadges', OPB_VERSION );

        // Core classes
        if ( ! class_exists( 'OPB_Badge_Renderer' ) ) {
            require_once OPB_DIR . 'includes/class-badge-renderer.php';
        }
        if ( ! class_exists( 'OPB_Meta_Box' ) ) {
            require_once OPB_DIR . 'includes/class-meta-box.php';
        }
        new OPB_Meta_Box();

        // Admin
        if ( is_admin() ) {
            if ( ! class_exists( 'OPB_Admin' ) ) {
                require_once OPB_DIR . 'admin/class-admin.php';
            }
            new OPB_Admin();
        }
    }
    add_action( 'plugins_loaded', 'opb_boot', 1 );
}

// ── Frontend assets ──────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_enqueue_frontend' ) ) {
    function opb_enqueue_frontend() {
        $s = opb_get_settings();
        if ( empty( $s['enabled'] ) ) return;
        wp_enqueue_style( 'opb-frontend', OPB_URL . 'assets/frontend.css', array(), OPB_VERSION );
    }
    add_action( 'wp_enqueue_scripts', 'opb_enqueue_frontend' );
}

// ── Badge injection via post_thumbnail_html ──────────────────────────────────
// This filter receives $post_id directly — works perfectly with Elementor Loop,
// classic themes, Gutenberg and any other renderer that uses get_the_post_thumbnail().
if ( ! function_exists( 'opb_wrap_thumbnail_html' ) ) {
    function opb_wrap_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        // Skip admin, empty HTML, or already-wrapped images
        if ( is_admin() ) return $html;
        if ( empty( $html ) ) return $html;
        if ( strpos( $html, 'opb-wrap' ) !== false ) return $html;

        $s = opb_get_settings();
        if ( empty( $s['enabled'] ) ) return $html;

        $post_id = (int) $post_id;
        if ( ! $post_id ) return $html;

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

        // Needs OPB_Badge_Renderer — loaded in opb_boot() via plugins_loaded.
        // Guard in case this filter fires before boot (shouldn't happen, but safe).
        if ( ! class_exists( 'OPB_Badge_Renderer' ) ) {
            $dir = defined( 'OPB_DIR' ) ? OPB_DIR : plugin_dir_path( __FILE__ );
            require_once $dir . 'includes/class-badge-renderer.php';
        }

        return OPB_Badge_Renderer::wrap( $html, $post_id, $s );
    }
    add_filter( 'post_thumbnail_html', 'opb_wrap_thumbnail_html', 20, 5 );
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
