<?php defined('ABSPATH') || exit;
$api_keys = Ofnoacomps_CRM_API_Keys::list_keys();
?>
<div class="wrap ocrm-wrap">
<div class="ocrm-page-header"><h1>⚙️ הגדרות CRM</h1></div>

<form method="post" style="max-width:600px;">
    <?php wp_nonce_field('ofnoacomps_settings', 'ofnoacomps_settings_nonce'); ?>

    <div class="ocrm-detail-card">
        <h3>כללי</h3>
        <div class="ocrm-form-row">
            <label>סמל מטבע</label>
            <input type="text" name="currency" value="<?php echo esc_attr($currency); ?>" style="max-width:80px;">
            <p style="color:#64748b;font-size:12px;">לדוגמה: ₪ / $ / €</p>
        </div>
        <div class="ocrm-form-row">
            <label>אימייל להתראות לידים חדשים</label>
            <input type="email" name="notify_email" value="<?php echo esc_attr($notify_email); ?>">
        </div>
    </div>

    <button type="submit" class="ocrm-btn ocrm-btn-primary" style="margin-top:16px;">שמור הגדרות</button>
</form>

<!-- ═══════════════════════════════════════════════════════════
     מפתחות API
     ════════════════════════════════════════════════════════ -->
<div class="ocrm-detail-card" style="max-width:700px;margin-top:28px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
        <h3 style="margin:0;">🔑 מפתחות API</h3>
        <button type="button" class="ocrm-btn ocrm-btn-primary" id="ocrm-new-key-btn">+ צור מפתח חדש</button>
    </div>

    <p style="font-size:13px;color:#475569;margin-bottom:12px;">
        מפתחות API מאפשרים לחבר מערכות חיצוניות (CRM, אוטומציה, אפליקציות) ישירות ל-Ofnoacomps CRM.<br>
        <strong>חשוב:</strong> המפתח המלא מוצג רק פעם אחת עם היצירה — שמור אותו במקום בטוח.
    </p>

    <!-- טופס יצירת מפתח חדש -->
    <div id="ocrm-new-key-form" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px;">
        <h4 style="margin:0 0 12px;">יצירת מפתח API חדש</h4>
        <div class="ocrm-form-row">
            <label>שם / תיאור</label>
            <input type="text" id="ocrm-key-name" placeholder="לדוגמה: חיבור ל-Make.com" style="max-width:300px;">
        </div>
        <div class="ocrm-form-row">
            <label>הרשאות</label>
            <label style="font-weight:normal;display:inline-flex;align-items:center;gap:6px;margin-left:12px;">
                <input type="checkbox" class="ocrm-key-cap" value="read" checked> קריאה
            </label>
            <label style="font-weight:normal;display:inline-flex;align-items:center;gap:6px;">
                <input type="checkbox" class="ocrm-key-cap" value="write"> כתיבה
            </label>
        </div>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="button" class="ocrm-btn ocrm-btn-primary" id="ocrm-generate-key-btn">צור מפתח</button>
            <button type="button" class="ocrm-btn" id="ocrm-cancel-key-btn">ביטול</button>
        </div>
    </div>

    <!-- הצגת מפתח חדש -->
    <div id="ocrm-key-reveal" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px;margin-bottom:16px;">
        <p style="margin:0 0 8px;font-weight:600;color:#166534;">✅ המפתח נוצר בהצלחה — העתק אותו עכשיו, הוא לא יוצג שוב!</p>
        <div style="display:flex;align-items:center;gap:8px;">
            <code id="ocrm-key-value" style="background:#dcfce7;padding:8px 12px;border-radius:6px;font-size:13px;flex:1;word-break:break-all;"></code>
            <button type="button" class="ocrm-btn" id="ocrm-copy-key-btn" style="white-space:nowrap;">📋 העתק</button>
        </div>
    </div>

    <!-- טבלת מפתחות קיימים -->
    <?php if ( empty($api_keys) ) : ?>
        <p style="color:#94a3b8;font-size:13px;text-align:center;padding:20px 0;">אין מפתחות API פעילים עדיין.</p>
    <?php else : ?>
    <table class="ocrm-table" style="width:100%;">
        <thead>
            <tr>
                <th>שם</th>
                <th>קידומת</th>
                <th>הרשאות</th>
                <th>שימוש אחרון</th>
                <th>נוצר</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="ocrm-keys-table-body">
        <?php foreach ($api_keys as $key) : ?>
            <tr id="ocrm-key-row-<?php echo (int)$key->id; ?>" style="<?php echo $key->is_active ? '' : 'opacity:.45;'; ?>">
                <td>
                    <strong><?php echo esc_html($key->name); ?></strong>
                    <?php if (!$key->is_active) echo ' <span style="color:#ef4444;font-size:11px;">(בוטל)</span>'; ?>
                </td>
                <td><code><?php echo esc_html($key->key_prefix); ?>…</code></td>
                <td><?php echo esc_html(implode(', ', $key->capabilities ?: ['read'])); ?></td>
                <td><?php echo $key->last_used_at ? esc_html(date_i18n('d/m/Y H:i', strtotime($key->last_used_at))) : '—'; ?></td>
                <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($key->created_at))); ?></td>
                <td style="text-align:left;">
                    <?php if ($key->is_active) : ?>
                    <button type="button"
                            class="ocrm-btn ocrm-btn-danger ocrm-revoke-key"
                            data-id="<?php echo (int)$key->id; ?>"
                            style="padding:4px 10px;font-size:12px;">
                        בטל
                    </button>
                    <?php endif; ?>
                    <button type="button"
                            class="ocrm-btn ocrm-delete-key"
                            data-id="<?php echo (int)$key->id; ?>"
                            style="padding:4px 10px;font-size:12px;margin-right:4px;">
                        מחק
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- מידע טכני -->
<!-- Auto-update section -->
<div class="ocrm-detail-card" style="max-width:700px;margin-top:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
        <h3 style="margin:0;">&#x1F504; &#x05E2;&#x05D3;&#x05DB;&#x05D5;&#x05E0;&#x05D9;&#x05DD; &#x05D0;&#x05D5;&#x05D8;&#x05D5;&#x05DE;&#x05D8;&#x05D9;&#x05D9;&#x05DD;</h3>
        <?php
        $ocrm_transient_key = 'ofnoacomps_ghupd_' . md5('ofnoacomps-crm');
        $ocrm_cached = get_transient($ocrm_transient_key);
        $ocrm_remote_ver = ($ocrm_cached && isset($ocrm_cached->version)) ? $ocrm_cached->version : null;
        $ocrm_has_update = $ocrm_remote_ver && version_compare($ocrm_remote_ver, OFNOACOMPS_CRM_VERSION, '>');
        $ocrm_force_url = wp_nonce_url(
            add_query_arg(['page'=>'ofnoacomps-crm-settings','ocrm_force_update_check'=>'1'], admin_url('admin.php')),
            'ocrm_force_update_check'
        );
        ?>
        <a href="<?php echo esc_url($ocrm_force_url); ?>" class="ocrm-btn ocrm-btn-primary" style="font-size:13px;">
            &#x1F50D; &#x05D1;&#x05D3;&#x05D5;&#x05E7; &#x05E2;&#x05D3;&#x05DB;&#x05D5;&#x05E0;&#x05D9;&#x05DD; &#x05E2;&#x05DB;&#x05E9;&#x05D9;&#x05D5;
        </a>
    </div>
    <?php if (isset($_GET['ocrm_update_checked'])) : ?>
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#166534;">
        &#x2705; Cache &#x05E0;&#x05D5;&#x05E7;&#x05D4; &mdash; WordPress &#x05D9;&#x05D1;&#x05D3;&#x05D5;&#x05E7; &#x05E2;&#x05D3;&#x05DB;&#x05D5;&#x05E0;&#x05D9;&#x05DD; &#x05D1;&#x05D8;&#x05E2;&#x05D9;&#x05E0;&#x05D4; &#x05D4;&#x05D1;&#x05D0;&#x05D4;.
    </div>
    <?php endif; ?>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">&#x05D2;&#x05E8;&#x05E1;&#x05D0; &#x05DE;&#x05D5;&#x05EA;&#x05E7;&#x05E0;&#x05EA;</span>
        <span class="ocrm-field-value"><strong><?php echo esc_html(OFNOACOMPS_CRM_VERSION); ?></strong></span>
    </div>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">&#x05D2;&#x05E8;&#x05E1;&#x05D0; &#x05D0;&#x05D7;&#x05E8;&#x05D5;&#x05E0;&#x05D4; &#x05D1;-GitHub</span>
        <span class="ocrm-field-value">
            <?php if ($ocrm_remote_ver) : ?>
                <?php if ($ocrm_has_update) : ?>
                    <strong style="color:#dc2626;"><?php echo esc_html($ocrm_remote_ver); ?></strong>
                    &nbsp;<a href="<?php echo esc_url(admin_url('update-core.php')); ?>" style="color:#2563eb;font-size:12px;">
                        &rarr; &#x05E2;&#x05D3;&#x05DB;&#x05DF; &#x05E2;&#x05DB;&#x05E9;&#x05D9;&#x05D5;
                    </a>
                <?php else : ?>
                    <span style="color:#16a34a;"><?php echo esc_html($ocrm_remote_ver); ?> &#x2713; &#x05E2;&#x05D3;&#x05DB;&#x05E0;&#x05D9;</span>
                <?php endif; ?>
            <?php else : ?>
                <span style="color:#94a3b8;">&#x05DC;&#x05D0; &#x05E0;&#x05D1;&#x05D3;&#x05E7; &mdash; &#x05DC;&#x05D7;&#x05E5; "&#x05D1;&#x05D3;&#x05D5;&#x05E7; &#x05E2;&#x05D3;&#x05DB;&#x05D5;&#x05E0;&#x05D9;&#x05DD; &#x05E2;&#x05DB;&#x05E9;&#x05D9;&#x05D5;"</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">&#x05D1;&#x05D3;&#x05D9;&#x05E7;&#x05D4; &#x05D0;&#x05D5;&#x05D8;&#x05D5;&#x05DE;&#x05D8;&#x05D9;&#x05EA;</span>
        <span class="ocrm-field-value" style="font-size:12px;color:#64748b;">&#x05DB;&#x05DC; &#x05E9;&#x05E2;&#x05D4; &#x05D3;&#x05E8;&#x05DA; GitHub manifest</span>
    </div>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">Flush Endpoint</span>
        <span class="ocrm-field-value">
            <code style="font-size:11px;"><?php echo esc_html(rest_url('ofnoacomps-crm/v1/flush-update-cache')); ?></code><br>
            <span style="font-size:11px;color:#94a3b8;">POST + Header: X-OCRM-Flush-Token: &lt;OCRM_UPDATE_SECRET&gt;</span>
        </span>
    </div>
</div>
<div class="ocrm-detail-card" style="max-width:700px;margin-top:16px;">
    <h3>מידע טכני</h3>
    <div class="ocrm-field-row"><span class="ocrm-field-label">גרסה</span><span class="ocrm-field-value"><?php echo OFNOACOMPS_CRM_VERSION; ?></span></div>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">REST API Base</span>
        <span class="ocrm-field-value"><code><?php echo rest_url('ofnoacomps-crm/v1'); ?></code></span>
    </div>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">אימות (Authorization header)</span>
        <span class="ocrm-field-value"><code>Authorization: Bearer ocrm_…</code></span>
    </div>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">אימות (X-API-Key header)</span>
        <span class="ocrm-field-value"><code>X-API-Key: ocrm_…</code></span>
    </div>
    <div class="ocrm-field-row">
        <span class="ocrm-field-label">Endpoint ציבורי (capture)</span>
        <span class="ocrm-field-value"><code><?php echo rest_url('ofnoacomps-crm/v1/capture'); ?></code></span>
    </div>
</div>

<script>
(function($){
    var nonce   = '<?php echo wp_create_nonce('wp_rest'); ?>';
    var apiBase = '<?php echo rest_url('ofnoacomps-crm/v1'); ?>';

    // Toggle form
    $('#ocrm-new-key-btn').on('click', function(){
        $('#ocrm-new-key-form').slideToggle(150);
        $('#ocrm-key-reveal').hide();
    });
    $('#ocrm-cancel-key-btn').on('click', function(){
        $('#ocrm-new-key-form').slideUp(150);
        $('#ocrm-key-name').val('');
    });

    // Generate key
    $('#ocrm-generate-key-btn').on('click', function(){
        var name = $('#ocrm-key-name').val().trim();
        if (!name){ alert('נא להזין שם למפתח'); return; }

        var caps = [];
        $('.ocrm-key-cap:checked').each(function(){ caps.push($(this).val()); });

        $.ajax({
            url: apiBase + '/api-keys',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ name: name, capabilities: caps }),
            success: function(res){
                var d = res.data;
                $('#ocrm-key-value').text(d.key);
                $('#ocrm-key-reveal').show();
                $('#ocrm-new-key-form').slideUp(150);
                $('#ocrm-key-name').val('');
                // הוסף שורה לטבלה
                var row = '<tr id="ocrm-key-row-'+d.id+'">' +
                    '<td><strong>'+$('<span>').text(d.name).html()+'</strong></td>' +
                    '<td><code>'+d.prefix+'…</code></td>' +
                    '<td>'+(caps.join(', ') || 'read')+'</td>' +
                    '<td>—</td><td>היום</td>' +
                    '<td style="text-align:left;">' +
                        '<button type="button" class="ocrm-btn ocrm-btn-danger ocrm-revoke-key" data-id="'+d.id+'" style="padding:4px 10px;font-size:12px;">בטל</button>' +
                        '<button type="button" class="ocrm-btn ocrm-delete-key" data-id="'+d.id+'" style="padding:4px 10px;font-size:12px;margin-right:4px;">מחק</button>' +
                    '</td></tr>';
                if (!$('#ocrm-keys-table-body').length) location.reload();
                else $('#ocrm-keys-table-body').prepend(row);
            },
            error: function(xhr){
                var msg = 'שגיאה ביצירת מפתח';
                try { var r = JSON.parse(xhr.responseText); if (r && r.error) msg = r.error; } catch(e){}
                console.error('OCRM key error:', xhr.status, xhr.responseText);
                alert(msg);
            }
        });
    });

    // Copy key
    $('#ocrm-copy-key-btn').on('click', function(){
        var key = $('#ocrm-key-value').text();
        navigator.clipboard.writeText(key).then(function(){
            $('#ocrm-copy-key-btn').text('✅ הועתק!');
            setTimeout(function(){ $('#ocrm-copy-key-btn').text('📋 העתק'); }, 2000);
        });
    });

    // Revoke key
    $(document).on('click', '.ocrm-revoke-key', function(){
        if (!confirm('לבטל את המפתח?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: apiBase + '/api-keys/' + id + '/revoke',
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            success: function(){
                $('#ocrm-key-row-'+id).css('opacity','0.45');
                $('#ocrm-key-row-'+id+' .ocrm-revoke-key').remove();
            },
            error: function(xhr){
                var msg = 'שגיאה ביצירת מפתח';
                try { var r = JSON.parse(xhr.responseText); if (r && r.error) msg = r.error; } catch(e){}
                console.error('OCRM key error:', xhr.status, xhr.responseText);
                alert(msg);
            }
        });
    });

    // Delete key
    $(document).on('click', '.ocrm-delete-key', function(){
        if (!confirm('למחוק את המפתח לצמיתות?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: apiBase + '/api-keys/' + id,
            method: 'DELETE',
            headers: { 'X-WP-Nonce': nonce },
            success: function(){ $('#ocrm-key-row-'+id).fadeOut(300, function(){ $(this).remove(); }); },
            error: function(xhr){
                var msg = 'שגיאה ביצירת מפתח';
                try { var r = JSON.parse(xhr.responseText); if (r && r.error) msg = r.error; } catch(e){}
                console.error('OCRM key error:', xhr.status, xhr.responseText);
                alert(msg);
            }
        });
    });
})(jQuery);
</script>
