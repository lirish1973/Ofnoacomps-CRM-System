<?php defined('ABSPATH') || exit; ?>
<div class="wrap ocrm-wrap">

<div class="ocrm-page-header">
    <h1>📊 דשבורד</h1>
    <form method="get" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="page" value="ocrm-crm">
        <input type="date" id="filter-from" name="from" value="<?php echo esc_attr($_GET['from'] ?? date('Y-m-01')); ?>" style="padding:7px 10px;border:1px solid #e2e8f0;border-radius:6px;">
        <span>עד</span>
        <input type="date" id="filter-to" name="to" value="<?php echo esc_attr($_GET['to'] ?? date('Y-m-d')); ?>" style="padding:7px 10px;border:1px solid #e2e8f0;border-radius:6px;">
        <button class="ocrm-btn ocrm-btn-primary" type="submit">סנן</button>
    </form>
</div>

<!-- Stat cards -->
<div class="ocrm-stat-grid">
    <div class="ocrm-stat-card">
        <div class="stat-value"><?php echo number_format($summary['leads_period']); ?></div>
        <div class="stat-label">לידים בתקופה</div>
    </div>
    <div class="ocrm-stat-card orange">
        <div class="stat-value"><?php echo number_format($summary['leads_new']); ?></div>
        <div class="stat-label">לידים חדשים</div>
    </div>
    <div class="ocrm-stat-card">
        <div class="stat-value"><?php echo number_format($summary['customers_active']); ?></div>
        <div class="stat-label">לקוחות פעילים</div>
    </div>
    <div class="ocrm-stat-card green">
        <div class="stat-value"><?php echo get_option('ofnoacomps_crm_currency','₪') . number_format($summary['deals_won_period']); ?></div>
        <div class="stat-label">הכנסות בתקופה</div>
    </div>
    <div class="ocrm-stat-card">
        <div class="stat-value"><?php echo get_option('ofnoacomps_crm_currency','₪') . number_format($summary['deals_open_amount']); ?></div>
        <div class="stat-label">Pipeline פתוח</div>
    </div>
    <div class="ocrm-stat-card green">
        <div class="stat-value"><?php echo $summary['conversion_rate']; ?>%</div>
        <div class="stat-label">שיעור המרה</div>
    </div>
</div>

<!-- Charts -->
<div class="ocrm-charts-row">
    <div class="ocrm-chart-card">
        <h3>לידים לפי תאריך</h3>
        <canvas id="chart-leads-time"></canvas>
    </div>
    <div class="ocrm-chart-card">
        <h3>לידים לפי מקור</h3>
        <canvas id="chart-leads-source"></canvas>
    </div>
</div>

<div class="ocrm-chart-card" style="margin-bottom:24px;">
    <h3>פאנל מכירות</h3>
    <canvas id="chart-funnel"></canvas>
</div>

<!-- Recent leads -->
<div class="ocrm-table-wrap">
    <div style="padding:16px 20px;border-bottom:1px solid var(--crm-border);display:flex;justify-content:space-between;align-items:center;">
        <strong>לידים אחרונים</strong>
        <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-leads'); ?>" class="ocrm-btn ocrm-btn-outline ocrm-btn-sm">כל הלידים</a>
    </div>
    <table class="ocrm-table">
        <thead><tr>
            <th>שם</th><th>אימייל</th><th>טלפון</th><th>מקור</th><th>סטטוס</th><th>תאריך</th>
        </tr></thead>
        <tbody>
        <?php
        $recent = Ofnoacomps_CRM_Lead::list(['limit' => 10]);
        $statuses_map = Ofnoacomps_CRM_Lead::get_statuses();
        $sources_map  = Ofnoacomps_CRM_Lead::get_sources();
        foreach ($recent as $lead): ?>
            <tr>
                <td><a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-leads&action=view&id=' . $lead->id); ?>"><?php echo esc_html(trim($lead->first_name . ' ' . $lead->last_name)); ?></a></td>
                <td><?php echo esc_html($lead->email); ?></td>
                <td><?php echo esc_html($lead->phone); ?></td>
                <td><span class="source-chip source-<?php echo esc_attr($lead->source); ?>"><?php echo esc_html($sources_map[$lead->source] ?? $lead->source); ?></span></td>
                <td><span class="ocrm-badge badge-<?php echo esc_attr($lead->status); ?>"><?php echo esc_html($statuses_map[$lead->status] ?? $lead->status); ?></span></td>
                <td><?php echo date_i18n('d/m/Y H:i', strtotime($lead->created_at)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>
