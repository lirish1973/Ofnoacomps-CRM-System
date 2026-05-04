<?php
/**
 * OPB_Admin — Settings page under Settings → Post Badges.
 */
defined( 'ABSPATH' ) || exit;

class OPB_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public static function default_settings() {
        return opb_defaults(); // delegate to standalone function in main file
    }

    public function add_menu() {
        add_options_page(
            'Post Badges — Ofnoacomps',
            '🏷️ Post Badges',
            'manage_options',
            'opb-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'opb_settings_group', OPB_OPTION, [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );
    }

    public function sanitize( $input ) {
        $clean = opb_defaults();

        $clean['enabled']       = ! empty( $input['enabled'] ) ? 1 : 0;
        $clean['default_text']  = sanitize_text_field( $input['default_text'] ?? '' );
        $clean['type']          = in_array( $input['type'] ?? '', [ 'square', 'side', 'ribbon' ], true )
                                    ? $input['type'] : 'square';
        $clean['position']      = sanitize_text_field( $input['position'] ?? 'top-right' );
        $clean['bg_color']      = sanitize_hex_color( $input['bg_color']   ?? '#e74c3c' ) ?: '#e74c3c';
        $clean['text_color']    = sanitize_hex_color( $input['text_color'] ?? '#ffffff' ) ?: '#ffffff';
        $clean['font_size']     = max( 8, min( 72, intval( $input['font_size']     ?? 14 ) ) );
        $clean['text_shadow']   = ! empty( $input['text_shadow'] ) ? 1 : 0;
        $clean['shadow_color']  = sanitize_text_field( $input['shadow_color']  ?? 'rgba(0,0,0,0.35)' );
        $clean['border_radius'] = max( 0, min( 50, intval( $input['border_radius'] ?? 6 ) ) );

        // Filter
        $clean['filter_mode']  = in_array( $input['filter_mode'] ?? 'all', [ 'all', 'categories', 'tags' ], true )
                                    ? $input['filter_mode'] : 'all';
        $raw_terms = isset( $input['filter_terms'] ) ? (array) $input['filter_terms'] : [];
        $clean['filter_terms'] = array_values( array_map( 'intval', $raw_terms ) );

        return $clean;
    }

    public function enqueue( $hook ) {
        if ( $hook !== 'settings_page_opb-settings' ) return;
        wp_enqueue_style(  'opb-admin', OPB_URL . 'assets/admin.css', [], OPB_VERSION );
        wp_enqueue_script( 'opb-admin', OPB_URL . 'assets/admin.js', [ 'jquery' ], OPB_VERSION, true );
        // Load frontend CSS too (preview uses the same classes)
        wp_enqueue_style( 'opb-frontend', OPB_URL . 'assets/frontend.css', [], OPB_VERSION );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Force-flush cache
        if ( isset( $_GET['opb_flush'] ) && check_admin_referer( 'opb_flush' ) ) {
            delete_transient( 'opb_ghupd_' . md5( 'ofnoacomps-postBadges' ) );
            delete_site_transient( 'update_plugins' );
            add_settings_error( OPB_OPTION, 'opb_flushed', 'מטמון העדכונים נוקה בהצלחה ✓', 'updated' );
        }

        $s = opb_get_settings();
        require OPB_DIR . 'admin/views/settings.php';
    }

    /** Render position radio buttons — called directly from settings.php */
    public static function render_position_options( $type, $current ) {
        $positions = OPB_Badge_Renderer::positions( $type );
        foreach ( $positions as $slug => $label ) {
            $active = ( $current === $slug ) ? 'is-active' : '';
            printf(
                '<label class="opb-pos-option %s"><input type="radio" name="%s[position]" value="%s" class="opb-live" data-preview="position" %s> %s</label>',
                esc_attr( $active ),
                esc_attr( OPB_OPTION ),
                esc_attr( $slug ),
                checked( $current, $slug, false ),
                esc_html( $label )
            );
        }
    }

    /** Returns all categories for the filter UI */
    public static function get_categories_for_select() {
        return get_categories( [ 'hide_empty' => false, 'orderby' => 'name' ] );
    }

    /** Returns all tags for the filter UI */
    public static function get_tags_for_select() {
        return get_tags( [ 'hide_empty' => false, 'orderby' => 'name' ] );
    }
}
