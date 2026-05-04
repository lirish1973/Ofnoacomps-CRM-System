<?php
/**
 * OPB_Badge_Renderer
 * Handles all badge HTML generation and dynamic CSS.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OPB_Badge_Renderer' ) ) :

class OPB_Badge_Renderer {

    /**
     * Wraps the thumbnail HTML with a positioned badge overlay.
     *
     * @param string $html       Original <img> HTML from WordPress.
     * @param int    $post_id    Post ID.
     * @param array  $settings   Global plugin settings.
     * @return string
     */
    public static function wrap( $html, $post_id, $settings ) {
        // --- Determine badge text (per-post overrides global) ---
        $post_disabled = get_post_meta( $post_id, '_opb_disabled', true );
        if ( $post_disabled ) return $html;

        $text = get_post_meta( $post_id, '_opb_text', true );
        if ( $text === '' ) {
            $text = isset( $settings['default_text'] ) ? $settings['default_text'] : '';
        }
        if ( empty( $text ) ) return $html;

        // --- Per-post style overrides ---
        $per_post_type     = get_post_meta( $post_id, '_opb_type',    true );
        $per_post_position = get_post_meta( $post_id, '_opb_position', true );
        $per_post_bg       = get_post_meta( $post_id, '_opb_bg',      true );
        $per_post_color    = get_post_meta( $post_id, '_opb_color',   true );

        $type     = $per_post_type     ?: ( isset( $settings['type'] )     ? $settings['type']     : 'square' );
        $position = $per_post_position ?: ( isset( $settings['position'] ) ? $settings['position'] : 'top-right' );
        $bg       = $per_post_bg       ?: ( isset( $settings['bg_color'] ) ? $settings['bg_color'] : '#e74c3c' );
        $color    = $per_post_color    ?: ( isset( $settings['text_color'] ) ? $settings['text_color'] : '#ffffff' );
        $size     = isset( $settings['font_size'] )   ? intval( $settings['font_size'] )   : 14;
        $shadow   = ! empty( $settings['text_shadow'] );
        $shadow_color = isset( $settings['shadow_color'] ) ? $settings['shadow_color'] : 'rgba(0,0,0,0.4)';
        $radius   = isset( $settings['border_radius'] ) ? intval( $settings['border_radius'] ) : 4;

        // --- Build inline style ---
        $inline_style = sprintf(
            'background-color:%s;color:%s;font-size:%dpx;border-radius:%dpx;',
            esc_attr( $bg ),
            esc_attr( $color ),
            $size,
            $radius
        );
        if ( $shadow ) {
            $inline_style .= sprintf( 'text-shadow:1px 1px 3px %s;', esc_attr( $shadow_color ) );
        }

        // --- Build CSS classes ---
        $classes = implode( ' ', [
            'opb-badge',
            'opb-type--' . esc_attr( $type ),
            'opb-pos--'  . esc_attr( $position ),
        ] );

        $badge_html = sprintf(
            '<span class="%s" style="%s" aria-hidden="true">%s</span>',
            $classes,
            $inline_style,
            esc_html( $text )
        );

        return '<div class="opb-wrap">' . $html . $badge_html . '</div>';
    }

    /**
     * Builds the global inline CSS block (CSS custom properties / overrides).
     * Called once on wp_enqueue_scripts to inject dynamic values.
     *
     * @param array $settings
     * @return string CSS string
     */
    public static function build_inline_css( $settings ) {
        // Nothing critical here — all styling is done via inline style="" on the badge.
        // This method is reserved for future theme-level variable overrides.
        return '';
    }

    /**
     * Returns the available badge types.
     * @return array  slug => label
     */
    public static function types() {
        return [
            'square' => 'ריבוע (פינה)',
            'side'   => 'צד (רצועה אנכית)',
            'ribbon' => 'מצד לצד (סרט מלא)',
        ];
    }

    /**
     * Returns available positions per badge type.
     * @param string $type
     * @return array  slug => label
     */
    public static function positions( $type = 'square' ) {
        switch ( $type ) {
            case 'side':
                return [
                    'left'  => 'שמאל',
                    'right' => 'ימין',
                ];
            case 'ribbon':
                return [
                    'top'    => 'למעלה',
                    'bottom' => 'למטה',
                ];
            default: // square
                return [
                    'top-right'    => 'פינה ימין עליונה',
                    'top-left'     => 'פינה שמאל עליונה',
                    'bottom-right' => 'פינה ימין תחתונה',
                    'bottom-left'  => 'פינה שמאל תחתונה',
                ];
        }
    }
}

endif; // class_exists OPB_Badge_Renderer
