<?php defined('ABSPATH') || exit;
$currency = get_option('ofnoacomps_crm_currency', '₪');
?>
<div class="wrap ocrm-wrap">
<div class="ocrm-page-header">
    <h1>📈 דוחות</h1>
    <form method="get" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="page" value="ocrm-crm-reports">
        <input type="date" id="rpt-from" name="from" value="<?php echo esc_attr($from); ?>" style="padding:7px 10px;border:1px solid #e2e8f0;border-radius:6px;">
        <span>עד</span>
        <input type="date" id="rpt-to" name="to" value="<?php echo esc_attr($to); ?>" style="padding:7px 10px;border:1px solid #e2e8f0;border-radius:6px;">
        <button class="ocrm-btn ocrm-btn-primary" type="submit">סנן</button>
    </form>
</div>

<!-- KPI cards -->
<div class="ocrm-stat-grid">
    <div class="ocrm-stat-card">
        <div class="stat-value"><?php echo number_format($summary['leads_period']); ?></div>
        <div class="stat-label">לידים בתקופה</div>
    </div>
    <div class="ocrm-stat-card orange">
        <div class="stat-value"><?php echo number_format($summary['leads_new']); ?></div>
        <div class="stat-label">לידים חדשים</div>
    </div>
    <div class="ocrm-stat-card green">
        <div class="stat-value"><?php echo $summary['conversion_rate']; ?>%</div>
        <div class="stat-label">שיעור המרה</div>
    </div>
    <div class="ocrm-stat-card green">
        <div class="stat-value"><?php echo $currency . number_format($summary['deals_won_period']); ?></div>
        <div class="stat-label">הכנסות בתקופה</div>
    </div>
    <div class="ocrm-stat-card">
        <div class="stat-value"><?php echo $currency . number_format($summary['deals_open_amount']); ?></div>
        <div class="stat-label">Pipeline פתוח</div>
    </div>
    <div class="ocrm-stat-card">
        <div class="stat-value"><?php echo number_format($summary['customers_total']); ?></div>
        <div class="stat-label">סה"כ לקוחות</div>
    </div>
</div>

<!-- Leads over time + Revenue -->
<div class="ocrm-charts-row">
    <div class="ocrm-chart-card">
        <h3>לידים לאורך זמן</h3>
        <canvas id="report-leads-time"></canvas>
    </div>
    <div class="ocrm-chart-card">
        <h3>הכנסות לאורך זמן</h3>
        <canvas id="report-revenue"></canvas>
    </div>
</div>

<!-- Source pie + Pipeline funnel -->
<div class="ocrm-charts-row" style="margin-bottom:28px;">
    <div class="ocrm-chart-card">
        <h3>לידים לפי מקור תנועה</h3>
        <canvas id="report-source-pie"></canvas>
        <!-- Source table -->
        <table class="ocrm-table" style="margin-top:16px;">
            <thead><tr><th>מקור</th><th>לידים</th><th>%</th></tr></thead>
            <tbody>
            <?php
            $total_src = array_sum(array_column((array)$by_source, 'count'));
            foreach ($by_source as $row):
                $pct = $total_src ? round($row->count / $total_src * 100, 1) : 0;
            ?>
            <tr>
                <td><span class="source-chip source-<?php echo esc_attr($row->source); ?>"><?php echo esc_html($row->label); ?></span></td>
                <td><?php echo number_format($row->count); ?></td>
                <td><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="ocrm-chart-card">
        <h3>פאנל — שלבי מכירה</h3>
        <?php if ($funnel): ?>
        <table class="ocrm-table">
            <thead><tr><th>שלב</th><th>עסקאות</th><th>ערך</th></tr></thead>
            <tbody>
            <?php foreach ($funnel as $row): ?>
            <tr>
                <td><span class="ocrm-badge" style="background:<?php echo esc_attr($row->color); ?>22;color:<?php echo esc_attr($row->color); ?>;"><?php echo esc_html($row->name); ?></span></td>
                <td><?php echo number_format($row->deal_count); ?></td>
                <td><?php echo $currency . number_format((float)$row->total_value, 0); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Leaderboard -->
<?php if ($leaderboard): ?>
<div class="ocrm-table-wrap" style="margin-bottom:28px;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--crm-border);"><strong>🏆 דירוג נציגים</strong></div>
    <table class="ocrm-table">
        <thead><tr><th>#</th><th>נציג</th><th>עסקאות שנסגרו</th><th>הכנסות</th></tr></thead>
        <tbody>
        <?php foreach ($leaderboard as $i => $row): ?>
        <tr>
            <td style="font-size:18px;"><?php echo ['🥇','🥈','🥉'][$i] ?? ($i+1); ?></td>
            <td><?php echo esc_html($row->user_name); ?></td>
            <td><?php echo number_format($row->deals_won); ?></td>
            <td><strong><?php echo $currency . number_format((float)$row->revenue, 0); ?></strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Leads by status -->
<div class="ocrm-table-wrap">
    <div style="padding:14px 20px;border-bottom:1px solid var(--crm-border);"><strong>סטטוס לידים</strong></div>
    <table class="ocrm-table">
        <thead><tr><th>סטטוס</th><th>כמות</th></tr></thead>
        <tbody>
        <?php foreach ($by_status as $row): ?>
        <tr>
            <td><span class="ocrm-badge badge-<?php echo esc_attr($row->status); ?>"><?php echo esc_html($row->label); ?></span></td>
            <td><?php echo number_format($row->count); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>
