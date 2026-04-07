<?php defined('ABSPATH') || exit; ?>
<div class="wrap ocrm-wrap">

<div class="ocrm-page-header">
    <h1>👥 לידים <span style="font-size:14px;color:#64748b;">(<?php echo number_format($total); ?>)</span></h1>
    <button class="ocrm-btn ocrm-btn-primary ocrm-open-modal" data-modal="add-lead-modal">+ ליד ידני</button>
</div>

<!-- Filters -->
<form method="get" class="ocrm-filters">
    <input type="hidden" name="page" value="ocrm-crm-leads">
    <input type="search" name="s" placeholder="חיפוש שם, אימייל, טלפון..." value="<?php echo esc_attr($search); ?>" style="min-width:200px;">
    <select name="status">
        <option value="">כל הסטטוסים</option>
        <?php foreach ($statuses as $k => $v): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($status, $k); ?>><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
    </select>
    <select name="source">
        <option value="">כל המקורות</option>
        <?php foreach ($sources as $k => $v): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($source, $k); ?>><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="ocrm-btn ocrm-btn-primary" type="submit">סנן</button>
    <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-leads'); ?>" class="ocrm-btn ocrm-btn-outline">נקה</a>
</form>

<!-- Table -->
<div class="ocrm-table-wrap">
    <table class="ocrm-table">
        <thead><tr>
            <th><input type="checkbox" id="cb-all"></th>
            <th>שם</th><th>אימייל / טלפון</th><th>מקור</th><th>קמפיין</th>
            <th>ציון</th><th>סטטוס</th><th>תאריך</th><th>פעולות</th>
        </tr></thead>
        <tbody>
        <?php foreach ($leads as $lead):
            $name = trim($lead->first_name . ' ' . $lead->last_name) ?: '(ללא שם)';
        ?>
        <tr>
            <td><input type="checkbox" name="lead_ids[]" value="<?php echo $lead->id; ?>"></td>
            <td>
                <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-leads&action=view&id='.$lead->id); ?>">
                    <?php echo esc_html($name); ?>
                </a>
            </td>
            <td>
                <?php if ($lead->email): ?><div><?php echo esc_html($lead->email); ?></div><?php endif; ?>
                <?php if ($lead->phone): ?><div style="color:#64748b;font-size:12px;"><?php echo esc_html($lead->phone); ?></div><?php endif; ?>
            </td>
            <td>
                <span class="source-chip source-<?php echo esc_attr($lead->source); ?>">
                    <?php echo esc_html($sources[$lead->source] ?? $lead->source); ?>
                </span>
                <?php if ($lead->medium): ?><div style="font-size:11px;color:#94a3b8;"><?php echo esc_html($lead->medium); ?></div><?php endif; ?>
            </td>
            <td style="font-size:12px;color:#64748b;"><?php echo esc_html($lead->campaign ?: '—'); ?></td>
            <td>
                <div class="score-bar">
                    <div class="score-track"><div class="score-fill" style="width:<?php echo $lead->score; ?>%"></div></div>
                    <span style="font-size:12px;color:#64748b;min-width:28px;"><?php echo $lead->score; ?></span>
                </div>
            </td>
            <td>
                <select class="lead-status-select" data-id="<?php echo $lead->id; ?>">
                    <?php foreach ($statuses as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($lead->status, $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td style="font-size:12px;white-space:nowrap;"><?php echo date_i18n('d/m/Y H:i', strtotime($lead->created_at)); ?></td>
            <td>
                <div style="display:flex;gap:6px;">
                    <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-leads&action=view&id='.$lead->id); ?>" class="ocrm-btn ocrm-btn-outline ocrm-btn-sm">פתח</a>
                    <?php if ($lead->status !== 'converted'): ?>
                        <button class="ocrm-btn ocrm-btn-success ocrm-btn-sm convert-lead-btn" data-id="<?php echo $lead->id; ?>">המר</button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="ocrm-pagination">
    <?php for ($i = 1; $i <= $pages; $i++):
        $url = add_query_arg(['paged' => $i]);
        if ($i === $paged): ?>
            <span class="current"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
        <?php endif;
    endfor; ?>
</div>
<?php endif; ?>

<!-- Add Lead Modal -->
<div class="ocrm-modal-overlay" id="add-lead-modal" style="display:none;">
<div class="ocrm-modal">
    <h2>הוסף ליד ידני</h2>
    <form id="add-lead-form">
        <div class="ocrm-form-grid">
            <div class="ocrm-form-row"><label>שם פרטי</label><input type="text" name="first_name" required></div>
            <div class="ocrm-form-row"><label>שם משפחה</label><input type="text" name="last_name"></div>
        </div>
        <div class="ocrm-form-grid">
            <div class="ocrm-form-row"><label>אימייל</label><input type="email" name="email"></div>
            <div class="ocrm-form-row"><label>טלפון</label><input type="text" name="phone"></div>
        </div>
        <div class="ocrm-form-row">
            <label>מקור</label>
            <select name="source">
                <?php foreach ($sources as $k => $v): ?>
                    <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ocrm-form-row">
            <label>אחראי</label>
            <select name="owner_id">
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ocrm-form-row"><label>הערה</label><textarea name="message" rows="3"></textarea></div>
        <div class="modal-footer">
            <button type="button" class="ocrm-btn ocrm-btn-outline ocrm-modal-close">ביטול</button>
            <button type="submit" class="ocrm-btn ocrm-btn-primary">שמור</button>
        </div>
    </form>
</div>
</div>

<script>
jQuery(function($){
    $('#add-lead-form').on('submit', function(e){
        e.preventDefault();
        var data = {};
        $(this).serializeArray().forEach(function(f){ data[f.name] = f.value; });
        fetch(ofnoacompsCRMAdmin.apiBase + '/leads', {
            method:'POST', headers:{'X-WP-Nonce':ofnoacompsCRMAdmin.nonce,'Content-Type':'application/json'},
            body: JSON.stringify(data)
        }).then(function(){ location.reload(); });
    });
});
</script>

</div>
