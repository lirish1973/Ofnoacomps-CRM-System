<?php defined('ABSPATH') || exit; ?>
<div class="wrap ocrm-wrap">
<div class="ocrm-page-header">
    <h1>🏢 לקוחות <span style="font-size:14px;color:#64748b;">(<?php echo number_format($total); ?>)</span></h1>
</div>

<form method="get" class="ocrm-filters">
    <input type="hidden" name="page" value="ocrm-crm-customers">
    <input type="search" name="s" placeholder="חיפוש שם, אימייל, חברה..." value="<?php echo esc_attr($search); ?>" style="min-width:220px;">
    <button class="ocrm-btn ocrm-btn-primary" type="submit">חפש</button>
    <a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-customers'); ?>" class="ocrm-btn ocrm-btn-outline">נקה</a>
</form>

<div class="ocrm-table-wrap">
    <table class="ocrm-table">
        <thead><tr>
            <th>שם</th><th>חברה</th><th>אימייל / טלפון</th><th>מקור</th><th>סטטוס</th><th>תאריך הצטרפות</th><th>פעולות</th>
        </tr></thead>
        <tbody>
        <?php foreach ($customers as $c):
            $name = trim($c->first_name . ' ' . $c->last_name) ?: '(ללא שם)';
        ?>
        <tr>
            <td><a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-customers&action=view&id='.$c->id); ?>"><?php echo esc_html($name); ?></a></td>
            <td><?php echo esc_html($c->company ?: '—'); ?></td>
            <td>
                <?php if ($c->email): ?><div><?php echo esc_html($c->email); ?></div><?php endif; ?>
                <?php if ($c->phone): ?><div style="color:#64748b;font-size:12px;"><?php echo esc_html($c->phone); ?></div><?php endif; ?>
            </td>
            <td><?php echo esc_html($c->source ?: '—'); ?></td>
            <td><span class="ocrm-badge badge-<?php echo $c->status==='active'?'qualified':'lost'; ?>"><?php echo $c->status==='active'?'פעיל':'לא פעיל'; ?></span></td>
            <td style="font-size:12px;"><?php echo date_i18n('d/m/Y', strtotime($c->created_at)); ?></td>
            <td><a href="<?php echo admin_url('admin.php?page=ofnoacomps-crm-customers&action=view&id='.$c->id); ?>" class="ocrm-btn ocrm-btn-outline ocrm-btn-sm">פתח</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<div class="ocrm-pagination">
    <?php for ($i = 1; $i <= $pages; $i++):
        $url = add_query_arg(['paged' => $i]);
        if ($i === $paged): ?><span class="current"><?php echo $i; ?></span>
        <?php else: ?><a href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
        <?php endif; endfor; ?>
</div>
<?php endif; ?>
</div>
