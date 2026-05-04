<?php
/**
 * Plugin Name: Ofnoacomps Post Badges
 * Plugin URI:  https://www.ofnoacomps.co.il
 * Description: מוסיף Badge (תווית) על תמונת הפוסט עם שליטה מלאה על עיצוב, מיקום וצבעים. עובד עם Elementor Loop, תמות קלאסיות ובלוקים.
 * Version:     1.0.3
 * Author:      Ofnoacomps
 * Text Domain: ofnoacomps-postbadges
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// ── Constants (guarded — safe against double-load) ───────────────────────────
if ( ! defined( 'OPB_VERSION' ) ) {
    define( 'OPB_VERSION', '1.0.3' );
    define( 'OPB_DIR',    plugin_dir_path( __FILE__ ) );
    define( 'OPB_URL',    plugin_dir_url( __FILE__ ) );
    define( 'OPB_FILE',   __FILE__ );
    define( 'OPB_OPTION', 'opb_settings' );
}

// ── Default settings ─────────────────────────────────────────────────────────
if ( ! function_exists( 'opb_defaults' ) ) {
    function opb_defaults() {
        return [
            'enabled'       => 1,
            'default_text'  => 'חדש!',
            'type'          => 'square',
            'position'      => 'top-right',
            'bg_color'      => '#e74c3c',
            'text_color'    => '#ffffff',
            'font_size'     => 14,
            'text_shadow'   => 1,
            'shadow_color'  => 'rgba(0,0,0,0.35)',
            'border_radius' => 6,
            'filter_mode'   => 'all',
            'filter_terms'  => [],
        ];
    }
}

if ( ! function_exists( 'opb_get_settings' ) ) {
    function opb_get_settings() {
        return wp_parse_args( (array) get_option( OPB_OPTION, [] ), opb_defaults() );
    }
}

// ── Bootstrap (deferred to plugins_loaded so all WP functions are available) ─
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
        wp_enqueue_style(  'opb-frontend', OPB_URL . 'assets/frontend.css', [], OPB_VERSION );
        wp_enqueue_script( 'opb-frontend', OPB_URL . 'assets/frontend.js',  [], OPB_VERSION, true );
    }
    add_action( 'wp_enqueue_scripts', 'opb_enqueue_frontend' );
}

// ── Badge data-attributes filter ─────────────────────────────────────────────
if ( ! function_exists( 'opb_add_badge_attrs' ) ) {
    function opb_add_badge_attrs( $attr, $attachment, $size ) {
        if ( ! is_array( $attr ) )                                    return $attr;
        if ( ! is_object( $attachment ) || empty( $attachment->ID ) ) return $attr;
        if ( is_admin() )                                             return $attr;

        $s = opb_get_settings();
        if ( empty( $s['enabled'] ) ) return $attr;

        $post_id = (int) get_the_ID();
        if ( ! $post_id ) {
            global $post;
            $post_id = ( isset( $post ) && is_object( $post ) && ! empty( $post->ID ) )
                ? (int) $post->ID : 0;
        }
        if ( ! $post_id ) return $attr;

        $thumb_id = (int) get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id || $thumb_id !== (int) $attachment->ID ) return $attr;

        if ( get_post_meta( $post_id, '_opb_disabled', true ) ) return $attr;

        // Category / Tag filter
        $filter_mode  = isset( $s['filter_mode'] ) ? $s['filter_mode'] : 'all';
        $filter_terms = isset( $s['filter_terms'] ) ? (array) $s['filter_terms'] : [];
        if ( $filter_mode !== 'all' && ! empty( $filter_terms ) ) {
            $term_ids = array_map( 'intval', $filter_terms );
            if ( $filter_mode === 'categories' ) {
                $cats = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
                if ( empty( array_intersect( $term_ids, (array) $cats ) ) ) return $attr;
            } elseif ( $filter_mode === 'tags' ) {
                $tags = wp_get_post_tags( $post_id, [ 'fields' => 'ids' ] );
                if ( empty( array_intersect( $term_ids, (array) $tags ) ) ) return $attr;
            }
        }

        $text = (string) get_post_meta( $post_id, '_opb_text', true );
        if ( $text === '' ) $text = isset( $s['default_text'] ) ? (string) $s['default_text'] : '';
        if ( $text === '' ) return $attr;

        $type   = get_post_meta( $post_id, '_opb_type',     true ) ?: ( $s['type']       ?? 'square' );
        $pos    = get_post_meta( $post_id, '_opb_position', true ) ?: ( $s['position']   ?? 'top-right' );
        $bg     = get_post_meta( $post_id, '_opb_bg',       true ) ?: ( $s['bg_color']   ?? '#e74c3c' );
        $color  = get_post_meta( $post_id, '_opb_color',    true ) ?: ( $s['text_color'] ?? '#ffffff' );

        $fsize  = (int) ( $s['font_size']     ?? 14 );
        $radius = ( $type === 'square' ) ? (int) ( $s['border_radius'] ?? 6 ) : 0;
        $shadow = ! empty( $s['text_shadow'] )
            ? 'text-shadow:1px 1px 3px ' . esc_attr( $s['shadow_color'] ?? 'rgba(0,0,0,0.35)' ) . ';'
            : '';

        $inline = sprintf(
            'background-color:%s;color:%s;font-size:%dpx;border-radius:%dpx;%s',
            esc_attr( $bg ), esc_attr( $color ), $fsize, $radius, $shadow
        );

        $attr['data-opb-badge'] = esc_attr( $text );
        $attr['data-opb-type']  = esc_attr( $type );
        $attr['data-opb-pos']   = esc_attr( $pos );
        $attr['data-opb-style'] = esc_attr( $inline );

        return $attr;
    }
    add_filter( 'wp_get_attachment_image_attributes', 'opb_add_badge_attrs', 20, 3 );
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
