<?php defined('ABSPATH') || exit; ?>
<div class="wrap ocrm-wrap">
<div class="ocrm-page-header"><h1>⚙️ הגדרות CRM</h1></div>

<form method="post" style="max-width:560px;">
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

    <div class="ocrm-detail-card" style="margin-top:16px;">
        <h3>מידע טכני</h3>
        <div class="ocrm-field-row"><span class="ocrm-field-label">גרסה</span><span class="ocrm-field-value"><?php echo OFNOACOMPS_CRM_VERSION; ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">REST API</span><span class="ocrm-field-value"><code><?php echo rest_url('ofnoacomps-crm/v1'); ?></code></span></div>
        <div class="ocrm-field-row">
            <span class="ocrm-field-label">Endpoint ציבורי (capture)</span>
            <span class="ocrm-field-value"><code><?php echo rest_url('ocrm-crm/v1/capture'); ?></code></span>
        </div>
    </div>

    <div class="ocrm-detail-card" style="margin-top:16px;">
        <h3>אינטגרציות זמינות</h3>
        <p style="font-size:13px;color:#475569;">הפלאגין מחובר אוטומטית ל:</p>
        <ul style="font-size:13px;color:#475569;padding-right:20px;">
            <li><strong>Contact Form 7</strong> — לידים נקלטים אוטומטית מכל הטפסים</li>
            <li><strong>WPForms</strong> — תמיכה מובנית</li>
            <li><strong>API ציבורי</strong> — <code>POST /ofnoacomps-crm/v1/capture</code> לאינטגרציות חיצוניות</li>
            <li><strong>JavaScript</strong> — <code>OfnoacompsCRM.submitLead({...})</code> מכל טופס מותאם</li>
        </ul>
    </div>

    <button type="submit" class="ocrm-btn ocrm-btn-primary" style="margin-top:16px;">שמור הגדרות</button>
</form>
</div>
