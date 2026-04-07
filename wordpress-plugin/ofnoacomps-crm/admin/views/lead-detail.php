<?php defined('ABSPATH') || exit;
if (!$lead) { echo '<p>ליד לא נמצא.</p>'; return; }
$name = trim($lead->first_name . ' ' . $lead->last_name) ?: '(ללא שם)';
$sources_map = Ofnoacomps_CRM_Lead::get_sources();
?>
<div class="wrap ocrm-wrap">

<div class="ocrm-page-header">
    <div style="display:flex;align-items:center;gap:12px;">
        <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-leads'); ?>" style="color:#64748b;text-decoration:none;">← לידים</a>
        <h1><?php echo esc_html($name); ?></h1>
        <span class="ocrm-badge badge-<?php echo esc_attr($lead->status); ?>"><?php echo esc_html($statuses[$lead->status] ?? $lead->status); ?></span>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if ($lead->status !== 'converted'): ?>
            <button class="ocrm-btn ocrm-btn-success convert-lead-btn" data-id="<?php echo $lead->id; ?>">המר ללקוח</button>
        <?php endif; ?>
    </div>
</div>

<div class="ocrm-detail-grid">

    <!-- Left: details -->
    <div>
        <div class="ocrm-detail-card">
            <h3>פרטי קשר</h3>
            <div class="ocrm-field-row"><span class="ocrm-field-label">שם</span><span class="ocrm-field-value"><?php echo esc_html($name); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">אימייל</span><span class="ocrm-field-value"><?php echo $lead->email ? '<a href="mailto:'.esc_attr($lead->email).'">'.esc_html($lead->email).'</a>' : '—'; ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">טלפון</span><span class="ocrm-field-value"><?php echo $lead->phone ? '<a href="tel:'.esc_attr($lead->phone).'">'.esc_html($lead->phone).'</a>' : '—'; ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">הודעה</span><span class="ocrm-field-value" style="max-width:300px;"><?php echo esc_html($lead->message ?: '—'); ?></span></div>
        </div>

        <div class="ocrm-detail-card">
            <h3>מקור תנועה</h3>
            <div class="ocrm-field-row"><span class="ocrm-field-label">מקור</span><span class="ocrm-field-value"><span class="source-chip source-<?php echo esc_attr($lead->source); ?>"><?php echo esc_html($sources_map[$lead->source] ?? $lead->source); ?></span></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">מדיה</span><span class="ocrm-field-value"><?php echo esc_html($lead->medium ?: '—'); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">קמפיין</span><span class="ocrm-field-value"><?php echo esc_html($lead->campaign ?: '—'); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">מילת מפתח</span><span class="ocrm-field-value"><?php echo esc_html($lead->utm_term ?: '—'); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">Landing Page</span><span class="ocrm-field-value"><?php echo $lead->landing_page ? '<a href="'.esc_url($lead->landing_page).'" target="_blank">'.esc_html(parse_url($lead->landing_page, PHP_URL_PATH) ?: $lead->landing_page).'</a>' : '—'; ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">Referrer</span><span class="ocrm-field-value"><?php echo esc_html($lead->referrer ?: '—'); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">מכשיר</span><span class="ocrm-field-value"><?php echo esc_html($lead->device_type ?: '—'); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">IP</span><span class="ocrm-field-value"><?php echo esc_html($lead->ip_address ?: '—'); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">טופס</span><span class="ocrm-field-value"><?php echo esc_html($lead->form_name ?: '—'); ?></span></div>
        </div>

        <!-- Activity feed -->
        <div class="ocrm-detail-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="margin:0;border:none;padding:0;">פעילות</h3>
                <button class="ocrm-btn ocrm-btn-primary ocrm-btn-sm ocrm-open-modal" data-modal="activity-modal">+ הוסף</button>
            </div>
            <?php
            $icons = ['note'=>'📝','call'=>'📞','email'=>'✉️','meeting'=>'🤝','task'=>'✅','sms'=>'💬','whatsapp'=>'📱'];
            $types = Ofnoacomps_CRM_Activity::get_types();
            foreach ($activities as $act): ?>
            <div class="ocrm-activity-item">
                <div class="activity-icon"><?php echo $icons[$act->type] ?? '📌'; ?></div>
                <div class="activity-body">
                    <div class="activity-subject"><?php echo esc_html($act->subject ?: $types[$act->type] ?? $act->type); ?></div>
                    <?php if ($act->body): ?><div style="font-size:13px;color:#475569;margin-top:4px;"><?php echo esc_html($act->body); ?></div><?php endif; ?>
                    <div class="activity-meta"><?php
                        $u = get_user_by('id', $act->user_id);
                        echo date_i18n('d/m/Y H:i', strtotime($act->created_at));
                        if ($u) echo ' · ' . esc_html($u->display_name);
                    ?></div>
                </div>
            </div>
            <?php endforeach;
            if (empty($activities)): ?><p style="color:#94a3b8;font-size:13px;">אין פעילויות עדיין.</p><?php endif; ?>
        </div>
    </div>

    <!-- Right: sidebar -->
    <div>
        <div class="ocrm-detail-card">
            <h3>עדכון סטטוס</h3>
            <select class="lead-status-select" data-id="<?php echo $lead->id; ?>" style="width:100%;padding:9px;border:1px solid var(--crm-border);border-radius:6px;">
                <?php foreach ($statuses as $k => $v): ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($lead->status, $k); ?>><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="ocrm-detail-card">
            <h3>ציון ליד</h3>
            <div class="score-bar" style="margin-top:6px;">
                <div class="score-track" style="height:10px;">
                    <div class="score-fill" style="width:<?php echo $lead->score; ?>%"></div>
                </div>
                <span style="font-size:16px;font-weight:700;"><?php echo $lead->score; ?>/100</span>
            </div>
        </div>

        <div class="ocrm-detail-card">
            <h3>פרטים</h3>
            <div class="ocrm-field-row"><span class="ocrm-field-label">נוצר</span><span class="ocrm-field-value"><?php echo date_i18n('d/m/Y H:i', strtotime($lead->created_at)); ?></span></div>
            <div class="ocrm-field-row"><span class="ocrm-field-label">עודכן</span><span class="ocrm-field-value"><?php echo date_i18n('d/m/Y H:i', strtotime($lead->updated_at)); ?></span></div>
            <?php if ($lead->converted_at): ?>
                <div class="ocrm-field-row"><span class="ocrm-field-label">הומר</span><span class="ocrm-field-value"><?php echo date_i18n('d/m/Y', strtotime($lead->converted_at)); ?></span></div>
            <?php endif; ?>
        </div>

        <?php if ($lead->customer_id): ?>
        <div class="ocrm-detail-card">
            <h3>לקוח מקושר</h3>
            <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-customers&action=view&id='.$lead->customer_id); ?>" class="ocrm-btn ocrm-btn-outline" style="width:100%;text-align:center;">פתח לקוח →</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Activity Modal -->
<div class="ocrm-modal-overlay" id="activity-modal" style="display:none;">
<div class="ocrm-modal">
    <h2>הוסף פעילות</h2>
    <form class="ocrm-activity-form" data-entity-type="lead" data-entity-id="<?php echo $lead->id; ?>">
        <div class="ocrm-form-row">
            <label>סוג</label>
            <select name="activity_type">
                <?php foreach (Ofnoacomps_CRM_Activity::get_types() as $k => $v): ?>
                    <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ocrm-form-row"><label>נושא</label><input type="text" name="subject" placeholder="נושא הפעילות"></div>
        <div class="ocrm-form-row"><label>תיאור</label><textarea name="body" rows="4"></textarea></div>
        <div class="modal-footer">
            <button type="button" class="ocrm-btn ocrm-btn-outline ocrm-modal-close">ביטול</button>
            <button type="submit" class="ocrm-btn ocrm-btn-primary">שמור</button>
        </div>
    </form>
</div>
</div>

</div>
