<?php
/**
 * OPB_Meta_Box
 * Adds a per-post meta box to override badge text, type, position and colors.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'OPB_Meta_Box' ) ) :

class OPB_Meta_Box {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register' ] );
        add_action( 'save_post',      [ $this, 'save' ], 10, 2 );
    }

    /** Register on all public post types that support thumbnails */
    public function register() {
        $types = get_post_types( [ 'public' => true ] );
        foreach ( $types as $type ) {
            if ( post_type_supports( $type, 'thumbnail' ) ) {
                add_meta_box(
                    'opb-meta-box',
                    '🏷️ Post Badge',
                    [ $this, 'render' ],
                    $type,
                    'side',
                    'default'
                );
            }
        }
    }

    /** Render the meta box HTML */
    public function render( $post ) {
        wp_nonce_field( 'opb_save_meta', 'opb_nonce' );

        $disabled = get_post_meta( $post->ID, '_opb_disabled', true );
        $text     = get_post_meta( $post->ID, '_opb_text',    true );
        $type     = get_post_meta( $post->ID, '_opb_type',    true );
        $position = get_post_meta( $post->ID, '_opb_position', true );
        $bg       = get_post_meta( $post->ID, '_opb_bg',      true );
        $color    = get_post_meta( $post->ID, '_opb_color',   true );

        $settings = get_option( OPB_OPTION, [] );
        ?>
        <div class="opb-meta-wrap">
            <p>
                <label>
                    <input type="checkbox" name="opb_disabled" value="1" <?php checked( $disabled, '1' ); ?>>
                    <strong>הסתר Badge בפוסט זה</strong>
                </label>
            </p>
            <hr>
            <p>
                <label for="opb_text"><strong>טקסט הבאדג' (ריק = ברירת מחדל גלובלית)</strong></label><br>
                <input type="text" id="opb_text" name="opb_text"
                       value="<?php echo esc_attr( $text ); ?>"
                       placeholder="<?php echo esc_attr( isset( $settings['default_text'] ) ? $settings['default_text'] : '' ); ?>"
                       style="width:100%;">
            </p>
            <p>
                <label for="opb_type"><strong>סוג Badge (ריק = גלובלי)</strong></label><br>
                <select id="opb_type" name="opb_type" style="width:100%;">
                    <option value="">— ברירת מחדל —</option>
                    <?php foreach ( OPB_Badge_Renderer::types() as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $type, $slug ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="opb_position"><strong>מיקום (ריק = גלובלי)</strong></label><br>
                <input type="text" id="opb_position" name="opb_position"
                       value="<?php echo esc_attr( $position ); ?>"
                       placeholder="top-right / left / top..."
                       style="width:100%;">
            </p>
            <p style="display:flex;gap:12px;">
                <label style="flex:1;">
                    <strong>צבע רקע</strong><br>
                    <input type="color" name="opb_bg" value="<?php echo esc_attr( $bg ?: '#e74c3c' ); ?>" style="width:100%;height:32px;">
                </label>
                <label style="flex:1;">
                    <strong>צבע טקסט</strong><br>
                    <input type="color" name="opb_color" value="<?php echo esc_attr( $color ?: '#ffffff' ); ?>" style="width:100%;height:32px;">
                </label>
            </p>
            <p style="font-size:11px;color:#888;margin:0;">
                שדות ריקים ירשו את ההגדרות הגלובליות מ<a href="<?php echo esc_url( admin_url( 'options-general.php?page=opb-settings' ) ); ?>">דף ההגדרות</a>.
            </p>
        </div>
        <?php
    }

    /** Save meta box data */
    public function save( $post_id, $post ) {
        if ( ! isset( $_POST['opb_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['opb_nonce'], 'opb_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $fields = [
            '_opb_disabled' => 'opb_disabled',
            '_opb_text'     => 'opb_text',
            '_opb_type'     => 'opb_type',
            '_opb_position' => 'opb_position',
            '_opb_bg'       => 'opb_bg',
            '_opb_color'    => 'opb_color',
        ];

        foreach ( $fields as $meta_key => $post_key ) {
            $value = isset( $_POST[ $post_key ] ) ? sanitize_text_field( $_POST[ $post_key ] ) : '';
            update_post_meta( $post_id, $meta_key, $value );
        }
    }
}

endif; // class_exists OPB_Meta_Box
