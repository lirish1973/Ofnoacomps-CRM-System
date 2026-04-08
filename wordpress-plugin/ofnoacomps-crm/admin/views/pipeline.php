<?php defined('ABSPATH') || exit;
$currency = get_option('ofnoacomps_crm_currency', '₪');
?>
<div class="wrap ocrm-wrap">
<div class="ocrm-page-header">
    <h1>🔄 Pipeline מכירות</h1>
    <div style="display:flex;gap:8px;">
        <?php foreach ($pipelines as $p): ?>
            <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-pipeline&pipeline='.$p->id); ?>"
               class="ocrm-btn <?php echo $pipeline_id === $p->id ? 'ocrm-btn-primary' : 'ocrm-btn-outline'; ?>">
                <?php echo esc_html($p->name); ?>
            </a>
        <?php endforeach; ?>
        <button class="ocrm-btn ocrm-btn-outline ocrm-open-modal" data-modal="add-deal-modal-global">+ עסקה חדשה</button>
    </div>
</div>

<!-- Pipeline totals -->
<?php
$total_open = 0; $total_deals = 0;
foreach ($kanban as $col) { $total_open += $col['total']; $total_deals += count($col['deals']); }
?>
<div class="ocrm-stat-grid" style="grid-template-columns:repeat(3,1fr);max-width:520px;margin-bottom:20px;">
    <div class="ocrm-stat-card"><div class="stat-value"><?php echo $total_deals; ?></div><div class="stat-label">עסקאות פתוחות</div></div>
    <div class="ocrm-stat-card green"><div class="stat-value"><?php echo $currency . number_format($total_open, 0); ?></div><div class="stat-label">ערך Pipeline</div></div>
</div>

<!-- Kanban board -->
<div class="ocrm-kanban">
<?php foreach ($kanban as $col):
    $stage = $col['stage'];
    $stage_total = count($col['deals']);
?>
<div class="ocrm-kanban-col">
    <div class="kanban-col-header" style="--stage-color:<?php echo esc_attr($stage->color); ?>">
        <span class="stage-name"><?php echo esc_html($stage->name); ?></span>
        <span class="kanban-col-header" style="background:<?php echo esc_attr($stage->color); ?>22;color:<?php echo esc_attr($stage->color); ?>;border-radius:999px;padding:2px 8px;font-size:12px;"><?php echo $stage_total; ?></span>
    </div>

    <?php foreach ($col['deals'] as $deal):
        $cust = $deal->customer_id ? Ofnoacomps_CRM_Customer::get($deal->customer_id) : null;
        $cust_name = $cust ? trim($cust->first_name . ' ' . $cust->last_name) : '';
    ?>
    <div class="kanban-deal-card" data-id="<?php echo $deal->id; ?>">
        <div class="deal-name"><?php echo esc_html($deal->name); ?></div>
        <?php if ($cust_name): ?><div class="deal-meta">👤 <?php echo esc_html($cust_name); ?></div><?php endif; ?>
        <div class="deal-amount"><?php echo $currency . number_format((float)$deal->amount, 0, '.', ','); ?></div>
        <?php if ($deal->close_date): ?><div class="deal-meta">📅 <?php echo date_i18n('d/m/Y', strtotime($deal->close_date)); ?></div><?php endif; ?>
        <div style="display:flex;gap:6px;margin-top:8px;">
            <button class="ocrm-btn ocrm-btn-success ocrm-btn-sm deal-won-btn" data-id="<?php echo $deal->id; ?>">✓ זכייה</button>
            <button class="ocrm-btn ocrm-btn-danger ocrm-btn-sm deal-lost-btn"  data-id="<?php echo $deal->id; ?>">✗ הפסד</button>
        </div>
    </div>
    <?php endforeach; ?>

    <button class="ocrm-btn ocrm-btn-outline ocrm-btn-sm add-deal-btn" data-stage="<?php echo $stage->id; ?>"
            style="width:100%;margin-top:8px;">+ עסקה</button>
</div>
<?php endforeach; ?>
</div>

<!-- Add Deal Modal (from kanban column) -->
<div class="ocrm-modal-overlay" id="deal-modal" style="display:none;">
<div class="ocrm-modal">
    <h2>עסקה חדשה</h2>
    <form id="deal-form">
        <input type="hidden" id="deal-modal-stage" name="stage_id">
        <input type="hidden" id="deal-customer-id" name="customer_id" value="">
        <div class="ocrm-form-row"><label>שם עסקה *</label><input type="text" id="deal-name" required placeholder="לדוגמה: פרויקט X - לקוח Y"></div>
        <div class="ocrm-form-grid">
            <div class="ocrm-form-row"><label>סכום (<?php echo $currency; ?>)</label><input type="number" id="deal-amount" min="0" step="100"></div>
            <div class="ocrm-form-row"><label>תאריך סגירה</label><input type="date" id="deal-close-date"></div>
        </div>
        <div class="ocrm-form-row"><label>לקוח (חיפוש)</label>
            <select id="deal-customer-select" style="width:100%;padding:9px;border:1px solid var(--crm-border);border-radius:6px;">
                <option value="">ללא לקוח</option>
                <?php
                $custs = Ofnoacomps_CRM_Customer::list(['limit' => 200]);
                foreach ($custs as $c):
                    $cn = trim($c->first_name . ' ' . $c->last_name);
                ?>
                <option value="<?php echo $c->id; ?>"><?php echo esc_html($cn ?: $c->company ?: 'לקוח #'.$c->id); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ocrm-form-row"><label>הערות</label><textarea id="deal-notes" rows="3"></textarea></div>
        <div class="modal-footer">
            <button type="button" class="ocrm-btn ocrm-btn-outline ocrm-modal-close">ביטול</button>
            <button type="submit" class="ocrm-btn ocrm-btn-primary">צור עסקה</button>
        </div>
    </form>
</div>
</div>

<!-- Edit Deal Modal -->
<div class="ocrm-modal-overlay" id="deal-edit-modal" style="display:none;">
<div class="ocrm-modal">
    <h2>עריכת עסקה</h2>
    <form id="deal-edit-form">
        <input type="hidden" id="deal-edit-id">
        <div class="ocrm-form-row"><label>שם עסקה</label><input type="text" id="deal-edit-name"></div>
        <div class="ocrm-form-grid">
            <div class="ocrm-form-row"><label>סכום</label><input type="number" id="deal-edit-amount"></div>
            <div class="ocrm-form-row"><label>תאריך סגירה</label><input type="date" id="deal-edit-close-date"></div>
        </div>
        <div class="ocrm-form-row"><label>שלב</label>
            <select id="deal-edit-stage" style="width:100%;padding:9px;border:1px solid var(--crm-border);border-radius:6px;">
                <?php foreach ($kanban as $col): ?>
                    <option value="<?php echo $col['stage']->id; ?>"><?php echo esc_html($col['stage']->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ocrm-form-row"><label>הערות</label><textarea id="deal-edit-notes" rows="3"></textarea></div>
        <div class="modal-footer">
            <button type="button" class="ocrm-btn ocrm-btn-outline ocrm-modal-close">ביטול</button>
            <button type="submit" class="ocrm-btn ocrm-btn-primary">שמור שינויים</button>
        </div>
    </form>
</div>
</div>

<script>
// Sync customer select with hidden input
jQuery(function($){
    $('#deal-customer-select').on('change', function(){
        $('#deal-customer-id').val($(this).val());
    });
});
</script>

</div>
