<?php
/**
 * HOCO Israel Customer Club Popup — Full Lead Capture
 * הדבק ב-functions.php או בתוסף Code Snippets
 *
 * ═══════════════════════════════════════════
 *  הגדרות — ערוך רק כאן
 * ═══════════════════════════════════════════
 */
define( 'HOCO_POPUP_DELAY',  10000 );             // השהיה (ms)
define( 'HOCO_WHATSAPP_NUM', '972501234567' );    // מספר וואטסאפ עם קידומת מדינה
define( 'HOCO_NOTIFY_EMAIL', get_option('admin_email') ); // מייל לקבלת הודעות
// ═══════════════════════════════════════════

if ( ! defined( 'ABSPATH' ) ) exit;


/* ═════════════════════════════════════════════════════════════════
 * 1.  RENDER — HTML + CSS + JS
 * ═════════════════════════════════════════════════════════════════ */
add_action( 'wp_footer', function () {
    if ( is_admin() ) return;
    if ( isset( $_COOKIE['hoco_popup_shown'] ) ) return;

    $js_config = wp_json_encode( [
        'delay'    => (int) HOCO_POPUP_DELAY,
        'whatsapp' => HOCO_WHATSAPP_NUM,
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'hoco_popup_nonce' ),
    ] );
    ?>
    <!-- HOCO Popup -->
    <style id="hoco-popup-css">
        #hoco-overlay {
            display: none;
            position: fixed;
            top: 0; right: 0; bottom: 0; left: 0;
            background: rgba(41, 70, 142, .85);
            z-index: 999999;
            -webkit-box-pack: center; -ms-flex-pack: center; justify-content: center;
            -webkit-box-align: center; -ms-flex-align: center; align-items: center;
        }
        #hoco-box {
            background: #fff;
            border-radius: 12px;
            padding: 40px 36px 32px;
            width: 90%; max-width: 480px;
            position: relative;
            text-align: center;
            direction: rtl;
            -webkit-box-shadow: 0 8px 32px rgba(0,0,0,.28);
                    box-shadow: 0 8px 32px rgba(0,0,0,.28);
            -webkit-animation: hocoIn .35s ease; animation: hocoIn .35s ease;
        }
        @-webkit-keyframes hocoIn {
            from { opacity:0; -webkit-transform:translateY(-18px); transform:translateY(-18px); }
            to   { opacity:1; -webkit-transform:translateY(0);     transform:translateY(0); }
        }
        @keyframes hocoIn {
            from { opacity:0; transform:translateY(-18px); }
            to   { opacity:1; transform:translateY(0); }
        }
        #hoco-close {
            position:absolute; top:12px; left:16px;
            font-size:28px; cursor:pointer; color:#999;
            background:none; border:none; line-height:1; padding:0;
        }
        #hoco-close:hover { color:#333; }
        #hoco-box h2 { color:#29468e; font-size:24px; margin:0 0 12px; }
        #hoco-box > p { font-size:15px; color:#444; line-height:1.6; margin:0 0 20px; }
        #hoco-box > p strong { color:#83b735; }
        #hoco-form input[type="text"],
        #hoco-form input[type="email"],
        #hoco-form input[type="tel"],
        #hoco-form textarea {
            width:100%; -webkit-box-sizing:border-box; box-sizing:border-box;
            padding:11px 14px; margin-bottom:12px;
            border:1px solid #ddd; border-radius:6px; font-size:15px;
            text-align:right; font-family:inherit;
            -webkit-transition:border-color .2s; transition:border-color .2s;
            -webkit-appearance:none; -moz-appearance:none; appearance:none;
        }
        #hoco-form textarea { height:80px; resize:vertical; }
        #hoco-form input:focus,
        #hoco-form textarea:focus { border-color:#29468e; outline:none; }
        .hoco-consent {
            display:-webkit-box; display:-ms-flexbox; display:flex;
            -webkit-box-align:start; -ms-flex-align:start; align-items:flex-start;
            margin-bottom:18px; font-size:13px; color:#555; text-align:right;
        }
        .hoco-consent input[type="checkbox"] {
            width:16px; height:16px; margin-top:2px; margin-left:8px;
            -ms-flex-negative:0; flex-shrink:0;
        }
        .hoco-consent label { cursor:pointer; }
        #hoco-form button[type="submit"] {
            width:100%; padding:14px; background:#83b735; color:#fff;
            border:none; border-radius:6px; font-size:17px; font-weight:600;
            cursor:pointer; -webkit-transition:background .2s; transition:background .2s;
        }
        #hoco-form button:hover  { background:#6fa02a; }
        #hoco-form button:active { background:#5d8a22; }
        #hoco-form button:disabled { opacity:.6; cursor:not-allowed; }
        #hoco-msg { min-height:20px; margin-top:12px; font-size:14px; }
        #hoco-msg.ok  { color:#2e7d32; }
        #hoco-msg.err { color:#c62828; }
        @media (max-width:520px) {
            #hoco-box { padding:28px 20px 24px; }
            #hoco-box h2 { font-size:20px; }
        }
    </style>

    <div id="hoco-overlay" role="dialog" aria-modal="true" aria-labelledby="hoco-title">
        <div id="hoco-box">
            <button id="hoco-close" aria-label="סגור">&times;</button>
            <h2 id="hoco-title">הצטרפו למועדון HOCO ישראל</h2>
            <p>קבלו <strong>5% הנחה</strong> על הקנייה הראשונה<br>ומבצעים בלעדיים לחברי המועדון!</p>
            <form id="hoco-form" novalidate>
                <input type="text"  name="name"    placeholder="שם מלא *"        required>
                <input type="email" name="email"   placeholder="כתובת אימייל *"  required>
                <input type="tel"   name="phone"   placeholder="מספר טלפון *"    required>
                <textarea           name="message" placeholder="הודעה (אופציונלי)"></textarea>
                <div class="hoco-consent">
                    <input type="checkbox" id="hoco-consent" name="consent" required>
                    <label for="hoco-consent">אני מאשר/ת קבלת עדכונים ומבצעים מ-HOCO ישראל</label>
                </div>
                <button type="submit">אני מצטרף/ת!</button>
            </form>
            <div id="hoco-msg" role="status" aria-live="polite"></div>
        </div>
    </div>

    <script>
    (function () {
        var cfg       = <?php echo $js_config; ?>;
        var overlay   = document.getElementById('hoco-overlay');
        var form      = document.getElementById('hoco-form');
        var msgEl     = document.getElementById('hoco-msg');
        var submitBtn = form.querySelector('button[type="submit"]');

        function openPopup() {
            overlay.style.display        = 'flex';
            overlay.style.justifyContent = 'center';
            overlay.style.alignItems     = 'center';
        }
        function closePopup() { overlay.style.display = 'none'; setCookie(); }
        function setMsg(t, c) { msgEl.textContent = t; msgEl.className = c || ''; }
        function setCookie() {
            var exp = new Date();
            exp.setTime(exp.getTime() + 7 * 24 * 60 * 60 * 1000);
            document.cookie = 'hoco_popup_shown=1; expires=' + exp.toUTCString() + '; path=/';
        }

        setTimeout(openPopup, cfg.delay);
        document.getElementById('hoco-close').addEventListener('click', closePopup);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closePopup(); });
        document.addEventListener('keydown', function (e) {
            var k = e.key || e.keyCode;
            if (k === 'Escape' || k === 'Esc' || k === 27) closePopup();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name    = form.querySelector('[name="name"]').value.trim();
            var email   = form.querySelector('[name="email"]').value.trim();
            var phone   = form.querySelector('[name="phone"]').value.trim();
            var message = form.querySelector('[name="message"]').value.trim();
            var consent = form.querySelector('[name="consent"]').checked;

            if (!name || !email || !phone)
                return setMsg('יש למלא את כל השדות החובה.', 'err');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
                return setMsg('כתובת אימייל לא תקינה.', 'err');
            if (!consent)
                return setMsg('יש לאשר קבלת עדכונים.', 'err');

            setMsg('שולח...', '');
            submitBtn.disabled = true;

            /* WhatsApp */
            var waMsg = 'שלום HOCO ישראל! רוצה להצטרף למועדון הלקוחות.\n'
                      + 'שם: ' + name + '\nאימייל: ' + email + '\nטלפון: ' + phone
                      + (message ? '\nהודעה: ' + message : '');
            window.open('https://wa.me/' + cfg.whatsapp + '?text=' + encodeURIComponent(waMsg), '_blank');

            /* AJAX */
            var fd = new FormData();
            fd.append('action',  'hoco_popup_submit');
            fd.append('nonce',   cfg.nonce);
            fd.append('name',    name);
            fd.append('email',   email);
            fd.append('phone',   phone);
            fd.append('message', message);

            function onOK(res) {
                if (res && res.success) {
                    setMsg('נרשמת בהצלחה! קוד ההנחה בדרך אליך 🎉', 'ok');
                    setTimeout(closePopup, 3000);
                } else {
                    setMsg((res && res.data) ? res.data : 'אירעה שגיאה, נסה שוב.', 'err');
                    submitBtn.disabled = false;
                }
            }
            function onErr() {
                setMsg('שגיאת תקשורת. נסה שוב.', 'err');
                submitBtn.disabled = false;
            }

            if (typeof fetch !== 'undefined') {
                fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); }).then(onOK).catch(onErr);
            } else {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', cfg.ajaxUrl, true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    if (xhr.status === 200) { try { onOK(JSON.parse(xhr.responseText)); } catch(x) { onErr(); } }
                    else { onErr(); }
                };
                xhr.send(fd);
            }
        });
    })();
    </script>
    <?php
}, 99 );


/* ═════════════════════════════════════════════════════════════════
 * 2.  AJAX HANDLER
 * ═════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nopriv_hoco_popup_submit', 'hoco_popup_handle_submit' );
add_action( 'wp_ajax_hoco_popup_submit',        'hoco_popup_handle_submit' );

function hoco_popup_handle_submit() {
    if ( ! check_ajax_referer( 'hoco_popup_nonce', 'nonce', false ) )
        wp_send_json_error( 'בקשה לא תקינה.' );

    $name    = sanitize_text_field( wp_unslash( $_POST['name']    ?? '' ) );
    $email   = sanitize_email(      wp_unslash( $_POST['email']   ?? '' ) );
    $phone   = sanitize_text_field( wp_unslash( $_POST['phone']   ?? '' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

    if ( ! $name || ! $email || ! $phone ) wp_send_json_error( 'שדות חסרים.' );
    if ( ! is_email( $email ) )            wp_send_json_error( 'אימייל לא תקין.' );

    wp_insert_post( [
        'post_type'   => 'hoco_lead',
        'post_title'  => $name,
        'post_status' => 'private',
        'meta_input'  => [
            '_hoco_name'    => $name,
            '_hoco_email'   => $email,
            '_hoco_phone'   => $phone,
            '_hoco_message' => $message,
            '_hoco_date'    => current_time( 'mysql' ),
            '_hoco_url'     => sanitize_url( wp_get_referer() ?: '' ),
        ],
    ] );

    wp_mail(
        HOCO_NOTIFY_EMAIL,
        'ליד חדש ממועדון HOCO - ' . $name,
        implode( "\n", [
            "שם:     {$name}",
            "אימייל: {$email}",
            "טלפון:  {$phone}",
            "הודעה:  " . ( $message ?: '---' ),
            "תאריך:  " . current_time( 'mysql' ),
            "עמוד:   " . ( wp_get_referer() ?: '---' ),
        ] ),
        [ 'Content-Type: text/plain; charset=UTF-8' ]
    );

    wp_send_json_success( 'OK' );
}


/* ═════════════════════════════════════════════════════════════════
 * 3.  CUSTOM POST TYPE
 * ═════════════════════════════════════════════════════════════════ */
add_action( 'init', function () {
    register_post_type( 'hoco_lead', [
        'labels'          => [
            'name'          => 'HOCO Leads',
            'singular_name' => 'Lead',
            'edit_item'     => 'ערוך ליד',
            'view_item'     => 'צפה בליד',
            'search_items'  => 'חפש לידים',
            'not_found'     => 'לא נמצאו לידים',
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-groups',
        'supports'        => [ 'title' ],
        'capability_type' => 'post',
    ] );
} );


/* ═════════════════════════════════════════════════════════════════
 * 4.  ADMIN — META BOX (edit screen)
 * ═════════════════════════════════════════════════════════════════ */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'hoco_lead_details', 'פרטי הליד המלאים',
        'hoco_render_lead_meta_box', 'hoco_lead', 'normal', 'high'
    );
} );

function hoco_render_lead_meta_box( $post ) {
    $fields = [
        '_hoco_name'    => [ 'label' => 'שם',     'icon' => 'person',   'mailto' => false ],
        '_hoco_email'   => [ 'label' => 'אימייל', 'icon' => 'email',    'mailto' => true  ],
        '_hoco_phone'   => [ 'label' => 'טלפון',  'icon' => 'phone',    'tel'    => true  ],
        '_hoco_message' => [ 'label' => 'הודעה',  'icon' => 'comment',                    ],
        '_hoco_date'    => [ 'label' => 'תאריך',  'icon' => 'calendar',                   ],
        '_hoco_url'     => [ 'label' => 'עמוד',   'icon' => 'admin-links', 'url'  => true ],
    ];
    echo '<div style="direction:rtl;font-family:sans-serif">';
    echo '<table style="width:100%;border-collapse:collapse;font-size:14px">';
    foreach ( $fields as $key => $f ) {
        $val     = get_post_meta( $post->ID, $key, true );
        $display = '<span style="color:#aaa">—</span>';
        if ( $val ) {
            if ( ! empty( $f['mailto'] ) )
                $display = '<a href="mailto:' . esc_attr($val) . '">' . esc_html($val) . '</a>';
            elseif ( ! empty( $f['tel'] ) )
                $display = '<a href="tel:' . esc_attr($val) . '">' . esc_html($val) . '</a>';
            elseif ( ! empty( $f['url'] ) )
                $display = '<a href="' . esc_url($val) . '" target="_blank">' . esc_html($val) . '</a>';
            else
                $display = '<span>' . esc_html($val) . '</span>';
        }
        echo '<tr style="border-bottom:1px solid #eee">';
        echo '<th style="text-align:right;padding:10px 12px;background:#f9f9f9;width:110px;white-space:nowrap;font-weight:600">';
        echo '<span class="dashicons dashicons-' . esc_attr($f['icon']) . '" style="vertical-align:middle;margin-left:4px"></span>';
        echo esc_html( $f['label'] );
        echo '</th>';
        echo '<td style="padding:10px 12px">' . $display . '</td>';
        echo '</tr>';
    }
    echo '</table></div>';
}


/* ═════════════════════════════════════════════════════════════════
 * 5.  ADMIN — LIST COLUMNS
 * ═════════════════════════════════════════════════════════════════ */
add_filter( 'manage_hoco_lead_posts_columns', function ( $cols ) {
    return [
        'cb'         => $cols['cb'] ?? '',
        'title'      => 'שם',
        'hoco_email' => 'אימייל',
        'hoco_phone' => 'טלפון',
        'hoco_msg'   => 'הודעה',
        'hoco_page'  => 'עמוד',
        'hoco_date'  => 'תאריך',
    ];
} );

add_action( 'manage_hoco_lead_posts_custom_column', function ( $col, $post_id ) {
    switch ( $col ) {
        case 'hoco_email':
            $v = get_post_meta( $post_id, '_hoco_email', true );
            echo $v ? '<a href="mailto:' . esc_attr($v) . '">' . esc_html($v) . '</a>' : '—';
            break;
        case 'hoco_phone':
            $v = get_post_meta( $post_id, '_hoco_phone', true );
            echo $v ? '<a href="tel:' . esc_attr($v) . '">' . esc_html($v) . '</a>' : '—';
            break;
        case 'hoco_msg':
            $v = get_post_meta( $post_id, '_hoco_message', true );
            echo $v ? '<span title="' . esc_attr($v) . '">' . esc_html( mb_strimwidth($v, 0, 40, '...') ) . '</span>' : '—';
            break;
        case 'hoco_page':
            $v = get_post_meta( $post_id, '_hoco_url', true );
            if ( $v ) {
                $path = wp_parse_url( $v, PHP_URL_PATH ) ?: $v;
                echo '<a href="' . esc_url($v) . '" target="_blank">' . esc_html($path) . '</a>';
            } else { echo '—'; }
            break;
        case 'hoco_date':
            echo esc_html( get_post_meta( $post_id, '_hoco_date', true ) ?: '—' );
            break;
    }
}, 10, 2 );

add_filter( 'manage_edit-hoco_lead_sortable_columns', function ( $cols ) {
    $cols['hoco_email'] = '_hoco_email';
    $cols['hoco_date']  = '_hoco_date';
    return $cols;
} );

add_action( 'admin_head', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'hoco_lead' ) return;
    echo '<style>
        .column-hoco_email { width:22%; }
        .column-hoco_phone { width:13%; }
        .column-hoco_msg   { width:20%; }
        .column-hoco_page  { width:15%; }
        .column-hoco_date  { width:13%; }
        #the-list td, #the-list th { vertical-align:middle; }
    </style>';
} );
