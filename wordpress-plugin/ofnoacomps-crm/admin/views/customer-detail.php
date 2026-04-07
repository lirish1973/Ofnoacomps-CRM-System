<?php defined('ABSPATH') || exit;
if (!$customer) { echo '<p>לקוח לא נמצא.</p>'; return; }
$name = trim($customer->first_name . ' ' . $customer->last_name) ?: '(ללא שם)';
$currency = get_option('ofnoacomps_crm_currency', '₪');
?>
<div class="wrap ocrm-wrap">
<div class="ocrm-page-header">
    <div style="display:flex;align-items:center;gap:12px;">
        <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-customers'); ?>" style="color:#64748b;text-decoration:none;">← לקוחות</a>
        <h1><?php echo esc_html($name); ?></h1>
    </div>
</div>

<div class="ocrm-detail-grid">
<div>
    <div class="ocrm-detail-card">
        <h3>פרטי לקוח</h3>
        <div class="ocrm-field-row"><span class="ocrm-field-label">שם</span><span class="ocrm-field-value"><?php echo esc_html($name); ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">חברה</span><span class="ocrm-field-value"><?php echo esc_html($customer->company ?: '—'); ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">אימייל</span><span class="ocrm-field-value"><?php echo $customer->email ? '<a href="mailto:'.esc_attr($customer->email).'">'.esc_html($customer->email).'</a>' : '—'; ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">טלפון</span><span class="ocrm-field-value"><?php echo $customer->phone ? '<a href="tel:'.esc_attr($customer->phone).'">'.esc_html($customer->phone).'</a>' : '—'; ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">עיר</span><span class="ocrm-field-value"><?php echo esc_html($customer->city ?: '—'); ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">כתובת</span><span class="ocrm-field-value"><?php echo esc_html($customer->address ?: '—'); ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">מקור</span><span class="ocrm-field-value"><?php echo esc_html($customer->source ?: '—'); ?></span></div>
        <div class="ocrm-field-row"><span class="ocrm-field-label">הערות</span><span class="ocrm-field-value"><?php echo esc_html($customer->notes ?: '—'); ?></span></div>
    </div>

    <!-- Deals -->
    <div class="ocrm-detail-card">
        <h3>עסקאות (<?php echo count($deals); ?>)</h3>
        <?php if ($deals): ?>
        <table class="ocrm-table">
            <thead><tr><th>שם עסקה</th><th>שלב</th><th>סכום</th><th>סטטוס</th></tr></thead>
            <tbody>
            <?php foreach ($deals as $d): ?>
            <tr>
                <td><?php echo esc_html($d->name); ?></td>
                <td><span class="ocrm-badge" style="background:<?php echo esc_attr($d->stage_color); ?>22;color:<?php echo esc_attr($d->stage_color); ?>;"><?php echo esc_html($d->stage_name); ?></span></td>
                <td><?php echo $currency . number_format((float)$d->amount, 0, '.', ','); ?></td>
                <td><span class="ocrm-badge badge-<?php echo esc_attr($d->status); ?>"><?php echo match($d->status){ 'open'=>'פתוח','won'=>'זכייה','lost'=>'הפסד', default=>$d->status }; ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p style="color:#94a3b8;font-size:13px;">אין עסקאות.</p><?php endif; ?>
    </div>

    <!-- Activities -->
    <div class="ocrm-detail-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h3 style="margin:0;border:none;padding:0;">פעילות</h3>
            <button class="ocrm-btn ocrm-btn-primary ocrm-btn-sm ocrm-open-modal" data-modal="activity-modal">+ הוסף</button>
        </div>
        <?php
        $icons = ['note'=>'📝','call'=>'📞','email'=>'✉️','meeting'=>'🤝','task'=>'✅','sms'=>'💬','whatsapp'=>'📱'];
        foreach ($activities as $act): ?>
        <div class="ocrm-activity-item">
            <div class="activity-icon"><?php echo $icons[$act->type] ?? '📌'; ?></div>
            <div class="activity-body">
                <div class="activity-subject"><?php echo esc_html($act->subject ?: $act->type); ?></div>
                <?php if ($act->body): ?><div style="font-size:13px;color:#475569;margin-top:4px;"><?php echo esc_html($act->body); ?></div><?php endif; ?>
                <div class="activity-meta"><?php echo date_i18n('d/m/Y H:i', strtotime($act->created_at)); ?></div>
            </div>
        </div>
        <?php endforeach;
        if (empty($activities)) echo '<p style="color:#94a3b8;font-size:13px;">אין פעילויות עדיין.</p>'; ?>
    </div>
</div>

<div>
    <div class="ocrm-detail-card">
        <h3>נוצר ב</h3>
        <p style="color:var(--crm-muted);font-size:13px;"><?php echo date_i18n('d/m/Y H:i', strtotime($customer->created_at)); ?></p>
    </div>
    <?php if ($customer->lead_id): ?>
    <div class="ocrm-detail-card">
        <h3>ליד מקורי</h3>
        <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-leads&action=view&id='.$customer->lead_id); ?>" class="ocrm-btn ocrm-btn-outline" style="width:100%;text-align:center;">פתח ליד →</a>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Activity Modal -->
<div class="ocrm-modal-overlay" id="activity-modal" style="display:none;">
<div class="ocrm-modal">
    <h2>הוסף פעילות</h2>
    <form class="ocrm-activity-form" data-entity-type="customer" data-entity-id="<?php echo $customer->id; ?>">
        <div class="ocrm-form-row"><label>סוג</label>
            <select name="activity_type">
                <?php foreach (Ofnoacomps_CRM_Activity::get_types() as $k => $v): ?>
                    <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ocrm-form-row"><label>נושא</label><input type="text" name="subject"></div>
        <div class="ocrm-form-row"><label>תיאור</label><textarea name="body" rows="4"></textarea></div>
        <div class="modal-footer">
            <button type="button" class="ocrm-btn ocrm-btn-outline ocrm-modal-close">ביטול</button>
            <button type="submit" class="ocrm-btn ocrm-btn-primary">שמור</button>
        </div>
    </form>
</div>
</div>
</div>
