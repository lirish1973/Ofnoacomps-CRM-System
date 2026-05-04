<?php
/**
 * OPB Settings Page View
 * $s = current settings (merged with defaults), available from render_page()
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap opb-settings-page" dir="rtl">

    <h1>🏷️ Ofnoacomps — Post Badges</h1>
    <p class="opb-sub">הגדרות ברירת מחדל גלובליות. ניתן לעקוף בכל פוסט בנפרד דרך תיבת המטא. <strong>עובד עם Elementor Loop, תמות קלאסיות ובלוקים.</strong></p>

    <?php settings_errors(); ?>

    <form method="post" action="options.php" id="opb-settings-form">
        <?php settings_fields( 'opb_settings_group' ); ?>

        <div class="opb-grid">

            <!-- ── LEFT: Controls ───────────────────────────────────── -->
            <div class="opb-col opb-col--controls">

                <!-- ENABLE -->
                <div class="opb-card">
                    <h2 class="opb-card__title">הפעלה</h2>
                    <label class="opb-toggle">
                        <input type="checkbox" name="<?php echo OPB_OPTION; ?>[enabled]" value="1"
                               id="opb_enabled" <?php checked( $s['enabled'], 1 ); ?>>
                        <span class="opb-toggle__track"></span>
                        <span class="opb-toggle__label">הצג Badge על פוסטים</span>
                    </label>
                </div>

                <!-- TEXT -->
                <div class="opb-card">
                    <h2 class="opb-card__title">טקסט ברירת מחדל</h2>
                    <input type="text" id="opb_text"
                           name="<?php echo OPB_OPTION; ?>[default_text]"
                           value="<?php echo esc_attr( $s['default_text'] ); ?>"
                           class="regular-text opb-live" data-preview="text"
                           placeholder="חדש! / מבצע / HOT">
                    <p class="description">פוסטים ללא טקסט מותאם אישית ישתמשו בטקסט זה.</p>
                </div>

                <!-- FILTER: Category / Tag -->
                <div class="opb-card">
                    <h2 class="opb-card__title">סינון — על אילו פוסטים להציג?</h2>
                    <div class="opb-filter-modes">
                        <?php
                        $modes = [ 'all' => 'כל הפוסטים', 'categories' => 'רק קטגוריות מסוימות', 'tags' => 'רק תגיות מסוימות' ];
                        foreach ( $modes as $mode_slug => $mode_label ) :
                        ?>
                            <label class="opb-filter-mode <?php echo $s['filter_mode'] === $mode_slug ? 'is-active' : ''; ?>">
                                <input type="radio"
                                       name="<?php echo OPB_OPTION; ?>[filter_mode]"
                                       value="<?php echo esc_attr( $mode_slug ); ?>"
                                       class="opb-filter-radio"
                                       <?php checked( $s['filter_mode'], $mode_slug ); ?>>
                                <?php echo esc_html( $mode_label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Category picker -->
                    <div id="opb-filter-categories" class="opb-term-picker"
                         style="<?php echo $s['filter_mode'] !== 'categories' ? 'display:none' : ''; ?>">
                        <p class="description">בחר קטגוריות שיוצג בהן ה-Badge:</p>
                        <div class="opb-term-list">
                        <?php foreach ( OPB_Admin::get_categories_for_select() as $cat ) : ?>
                            <label class="opb-term-item">
                                <input type="checkbox"
                                       name="<?php echo OPB_OPTION; ?>[filter_terms][]"
                                       value="<?php echo esc_attr( $cat->term_id ); ?>"
                                       <?php checked( in_array( $cat->term_id, (array) $s['filter_terms'], false ) ); ?>>
                                <?php echo esc_html( $cat->name ); ?>
                                <span class="opb-term-count">(<?php echo intval( $cat->count ); ?>)</span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tag picker -->
                    <div id="opb-filter-tags" class="opb-term-picker"
                         style="<?php echo $s['filter_mode'] !== 'tags' ? 'display:none' : ''; ?>">
                        <p class="description">בחר תגיות שיוצג בהן ה-Badge:</p>
                        <div class="opb-term-list">
                        <?php foreach ( OPB_Admin::get_tags_for_select() as $tag ) : ?>
                            <label class="opb-term-item">
                                <input type="checkbox"
                                       name="<?php echo OPB_OPTION; ?>[filter_terms][]"
                                       value="<?php echo esc_attr( $tag->term_id ); ?>"
                                       <?php checked( in_array( $tag->term_id, (array) $s['filter_terms'], false ) ); ?>>
                                <?php echo esc_html( $tag->name ); ?>
                                <span class="opb-term-count">(<?php echo intval( $tag->count ); ?>)</span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- BADGE TYPE -->
                <div class="opb-card">
                    <h2 class="opb-card__title">סוג Badge</h2>
                    <div class="opb-type-grid">
                        <?php
                        $types = OPB_Badge_Renderer::types();
                        $icons = [ 'square' => '⬛', 'side' => '▌', 'ribbon' => '▬' ];
                        foreach ( $types as $slug => $label ) :
                        ?>
                            <label class="opb-type-card <?php echo $s['type'] === $slug ? 'is-active' : ''; ?>">
                                <input type="radio"
                                       name="<?php echo OPB_OPTION; ?>[type]"
                                       value="<?php echo esc_attr( $slug ); ?>"
                                       class="opb-live" data-preview="type"
                                       <?php checked( $s['type'], $slug ); ?>>
                                <span class="opb-type-icon"><?php echo $icons[ $slug ]; ?></span>
                                <span class="opb-type-label"><?php echo esc_html( $label ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- POSITION -->
                <div class="opb-card">
                    <h2 class="opb-card__title">מיקום</h2>
                    <div id="opb-position-options">
                        <?php OPB_Admin::render_position_options( $s['type'], $s['position'] ); ?>
                    </div>
                </div>

                <!-- COLORS -->
                <div class="opb-card">
                    <h2 class="opb-card__title">צבעים</h2>
                    <div class="opb-row">
                        <div class="opb-field">
                            <label for="opb_bg">צבע רקע</label>
                            <input type="color" id="opb_bg"
                                   name="<?php echo OPB_OPTION; ?>[bg_color]"
                                   value="<?php echo esc_attr( $s['bg_color'] ); ?>"
                                   class="opb-live" data-preview="bg">
                        </div>
                        <div class="opb-field">
                            <label for="opb_color">צבע טקסט</label>
                            <input type="color" id="opb_color"
                                   name="<?php echo OPB_OPTION; ?>[text_color]"
                                   value="<?php echo esc_attr( $s['text_color'] ); ?>"
                                   class="opb-live" data-preview="color">
                        </div>
                    </div>
                </div>

                <!-- TYPOGRAPHY -->
                <div class="opb-card">
                    <h2 class="opb-card__title">טיפוגרפיה</h2>
                    <div class="opb-field">
                        <label>גודל טקסט: <strong id="opb_font_size_display"><?php echo intval( $s['font_size'] ); ?>px</strong></label>
                        <input type="range" id="opb_font_size"
                               name="<?php echo OPB_OPTION; ?>[font_size]"
                               value="<?php echo intval( $s['font_size'] ); ?>"
                               min="8" max="48" step="1"
                               class="opb-slider opb-live" data-preview="size"
                               data-display="opb_font_size_display">
                    </div>
                    <div class="opb-field opb-field--row" style="margin-top:12px;">
                        <label class="opb-toggle opb-toggle--small">
                            <input type="checkbox" id="opb_shadow"
                                   name="<?php echo OPB_OPTION; ?>[text_shadow]" value="1"
                                   class="opb-live" data-preview="shadow"
                                   <?php checked( $s['text_shadow'], 1 ); ?>>
                            <span class="opb-toggle__track"></span>
                            <span class="opb-toggle__label">הוסף צל לטקסט</span>
                        </label>
                        <div id="opb_shadow_color_wrap" style="<?php echo empty( $s['text_shadow'] ) ? 'display:none' : ''; ?>">
                            <label>צבע צל</label>
                            <input type="text" id="opb_shadow_color"
                                   name="<?php echo OPB_OPTION; ?>[shadow_color]"
                                   value="<?php echo esc_attr( $s['shadow_color'] ); ?>"
                                   class="opb-live" data-preview="shadow_color"
                                   placeholder="rgba(0,0,0,0.35)">
                        </div>
                    </div>
                </div>

                <!-- RADIUS -->
                <div class="opb-card" id="opb-radius-card" style="<?php echo $s['type'] !== 'square' ? 'display:none' : ''; ?>">
                    <h2 class="opb-card__title">עיגול פינות</h2>
                    <div class="opb-field">
                        <label>רדיוס: <strong id="opb_radius_display"><?php echo intval( $s['border_radius'] ); ?>px</strong></label>
                        <input type="range" id="opb_radius"
                               name="<?php echo OPB_OPTION; ?>[border_radius]"
                               value="<?php echo intval( $s['border_radius'] ); ?>"
                               min="0" max="50" step="1"
                               class="opb-slider opb-live" data-preview="radius"
                               data-display="opb_radius_display">
                    </div>
                </div>

                <?php submit_button( 'שמור הגדרות', 'primary opb-save-btn' ); ?>
            </div>

            <!-- ── RIGHT: Preview ───────────────────────────────────── -->
            <div class="opb-col opb-col--preview">
                <div class="opb-card opb-preview-card">
                    <h2 class="opb-card__title">תצוגה מקדימה</h2>
                    <div class="opb-preview-stage">
                        <div class="opb-wrap opb-preview-thumb">
                            <img src="<?php echo OPB_URL; ?>assets/preview-placeholder.svg"
                                 alt="Preview" class="opb-preview-img">
                            <span id="opb-preview-badge"
                                  class="opb-badge opb-type--<?php echo esc_attr( $s['type'] ); ?> opb-pos--<?php echo esc_attr( $s['position'] ); ?>"
                                  style="background-color:<?php echo esc_attr( $s['bg_color'] ); ?>;color:<?php echo esc_attr( $s['text_color'] ); ?>;font-size:<?php echo intval( $s['font_size'] ); ?>px;border-radius:<?php echo ( $s['type'] === 'square' ? intval( $s['border_radius'] ) : 0 ); ?>px;<?php echo ! empty( $s['text_shadow'] ) ? 'text-shadow:1px 1px 3px ' . esc_attr( $s['shadow_color'] ) . ';' : ''; ?>">
                                <?php echo esc_html( $s['default_text'] ?: 'Badge' ); ?>
                            </span>
                        </div>
                    </div>
                    <p class="opb-preview-note">התצוגה מתעדכנת בזמן אמת ✨</p>
                </div>

                <div class="opb-card opb-info-card">
                    <h2 class="opb-card__title">מידע</h2>
                    <ul class="opb-info-list">
                        <li>גרסה: <strong><?php echo OPB_VERSION; ?></strong></li>
                        <li>תואם: <strong>Elementor Loop ✓ · Classic ✓ · Blocks ✓</strong></li>
                        <li>עדכונים: <strong>אוטומטיים דרך GitHub ✓</strong></li>
                        <li>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=opb-settings&opb_flush=1' ), 'opb_flush' ) ); ?>">
                                🔄 בדוק עדכונים עכשיו
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        </div><!-- .opb-grid -->
    </form>
</div>

<script>
window.OPB_POSITIONS = {
    square: <?php echo wp_json_encode( OPB_Badge_Renderer::positions( 'square' ) ); ?>,
    side:   <?php echo wp_json_encode( OPB_Badge_Renderer::positions( 'side' ) ); ?>,
    ribbon: <?php echo wp_json_encode( OPB_Badge_Renderer::positions( 'ribbon' ) ); ?>
};
window.OPB_OPTION = '<?php echo OPB_OPTION; ?>';
</script>
