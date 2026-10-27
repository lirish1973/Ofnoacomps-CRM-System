<?php
/**
 * Command Center template — standalone page (no theme header/footer).
 *
 * @var array $data Output of Ofnoacomps_CRM_Dashboard_Data::all()
 */
defined('ABSPATH') || exit;

$crm  = $data['crm'];
$meta = $data['meta'];
$seo  = $data['seo'];
$days = (int) $data['range_days'];
$cur  = $crm['currency'];

$fmt = function ($n, int $dec = 0): string {
    return number_format_i18n((float) $n, $dec);
};

$short = function ($n) use ($fmt): string {
    $n = (float) $n;
    if ($n >= 1000000) return $fmt($n / 1000000, 1) . 'M';
    if ($n >= 10000)   return $fmt($n / 1000, 1) . 'K';
    return $fmt($n);
};

$delta_html = function ($d): string {
    if ($d === null) return '<span class="cc-delta cc-delta--none">אין נתון להשוואה</span>';
    $cls  = $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat');
    $sign = $d > 0 ? '▲ ' : ($d < 0 ? '▼ ' : '');
    return '<span class="cc-delta cc-delta--' . $cls . '">' . $sign . esc_html(number_format_i18n(abs($d), 1)) . '% מהתקופה הקודמת</span>';
};

// CRM timestamps come from MySQL CURRENT_TIMESTAMP (server time, UTC on most hosts).
$ago = function (string $mysql): string {
    $ts = strtotime($mysql . ' UTC');
    if (!$ts) return '';
    return human_time_diff($ts, time());
};

$when = function (string $mysql): string {
    $ts = strtotime($mysql);
    return $ts ? date_i18n('d/m H:i', $ts) : '';
};

$channel_name = function (string $c): string {
    return $c === 'instagram' ? 'אינסטגרם' : ($c === 'facebook' ? 'פייסבוק' : $c);
};

// ── Things that need a human ──────────────────────────────────────────────
$alerts = [];
if ($crm['stale_count'] > 0) {
    $alerts[] = ['critical', $crm['stale_count'] . ' לידים חדשים מחכים יותר מ-48 שעות בלי טיפול', admin_url('admin.php?page=ofnoacomps-crm-leads&status=new')];
}
if (!empty($meta['installed'])) {
    if (!$meta['enabled']) {
        $alerts[] = ['warning', 'סוכן מטא כבוי — לא יתפרסמו פוסטים', admin_url('admin.php?page=meta-content-agent-settings')];
    }
    if ($meta['awaiting'] > 0) {
        $alerts[] = ['warning', $meta['awaiting'] . ' פוסטים במטא ממתינים לאישור שלך', $meta['admin_url']];
    }
    if ($meta['failed_period'] > 0) {
        $alerts[] = ['critical', $meta['failed_period'] . ' פוסטים במטא נכשלו בפרסום בתקופה', $meta['logs_url']];
    }
}
if (!empty($seo['installed'])) {
    if (!$seo['generation']) {
        $alerts[] = ['warning', 'כתיבת המאמרים בסוכן ה-SEO כבויה', admin_url('admin.php?page=sca-settings')];
    }
    if ($seo['drafts'] > 0) {
        $alerts[] = ['warning', $seo['drafts'] . ' מאמרי SEO מחכים כטיוטה לאישור', admin_url('edit.php?post_status=draft&post_type=post')];
    }
    if (!empty($seo['status_counts']['failed'])) {
        $alerts[] = ['serious', $seo['status_counts']['failed'] . ' פריטים בתור ה-SEO במצב "נכשל"', $seo['logs_url']];
    }
}

$chart_payload = [
    'labels'   => array_map(function ($d) { return date_i18n('d/m', strtotime($d)); }, $data['labels']),
    'leads'    => $crm['by_day'],
    'sources'  => $crm['by_source'],
    'meta_fb'  => $meta['per_day_fb'] ?? [],
    'meta_ig'  => $meta['per_day_ig'] ?? [],
    'clicks'   => $seo['clicks_day'] ?? [],
    'channels' => $seo['channels'] ?? [],
];

$asset_ver = OFNOACOMPS_CRM_VERSION;
?><!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>מרכז שליטה · <?php echo esc_html(get_bloginfo('name')); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(OFNOACOMPS_CRM_PLUGIN_URL . 'assets/command-center.css?ver=' . $asset_ver); ?>">
</head>
<body class="cc">

<header class="cc-top">
    <div class="cc-top__title">
        <span class="cc-top__eyebrow"><?php echo esc_html(get_bloginfo('name')); ?></span>
        <h1>מרכז שליטה</h1>
    </div>
    <nav class="cc-range" aria-label="טווח תאריכים">
        <?php foreach (Ofnoacomps_CRM_Dashboard_Data::RANGES as $r): ?>
            <a href="<?php echo esc_url(Ofnoacomps_CRM_Frontend_Dashboard::url(['range' => $r])); ?>"
               class="<?php echo $r === $days ? 'is-active' : ''; ?>"
               <?php echo $r === $days ? 'aria-current="page"' : ''; ?>><?php echo (int) $r; ?> ימים</a>
        <?php endforeach; ?>
    </nav>
    <div class="cc-top__meta">
        <span>עודכן <?php echo esc_html(date_i18n('H:i', strtotime($data['generated_at']))); ?></span>
        <a class="cc-btn" href="<?php echo esc_url(admin_url('admin.php?page=ofnoacomps-crm')); ?>">לניהול באתר ←</a>
    </div>
</header>

<main class="cc-main">

<!-- ── KPI strip ─────────────────────────────────────────────────────── -->
<section class="cc-kpis" aria-label="מדדים מרכזיים">
    <div class="cc-kpi">
        <div class="cc-kpi__label">לידים</div>
        <div class="cc-kpi__value"><?php echo esc_html($fmt($crm['leads'])); ?></div>
        <?php echo $delta_html($crm['leads_delta']); ?>
    </div>
    <div class="cc-kpi">
        <div class="cc-kpi__label">לידים פתוחים (חדש)</div>
        <div class="cc-kpi__value"><?php echo esc_html($fmt($crm['new_open'])); ?></div>
        <span class="cc-delta <?php echo $crm['stale_count'] ? 'cc-delta--down' : 'cc-delta--none'; ?>">
            <?php echo $crm['stale_count'] ? esc_html($crm['stale_count'] . ' מעל 48 שעות') : 'אין ממתינים ישנים'; ?>
        </span>
    </div>
    <div class="cc-kpi">
        <div class="cc-kpi__label">שיעור המרה</div>
        <div class="cc-kpi__value"><?php echo esc_html($fmt($crm['conversion_rate'], 1)); ?>%</div>
        <span class="cc-delta cc-delta--none">Pipeline פתוח <?php echo esc_html($cur . $short($crm['pipeline_open'])); ?></span>
    </div>
    <?php if (!empty($meta['installed'])): ?>
    <div class="cc-kpi">
        <div class="cc-kpi__label">פוסטים שפורסמו במטא</div>
        <div class="cc-kpi__value"><?php echo esc_html($fmt($meta['published'])); ?></div>
        <?php echo $delta_html($meta['published_delta']); ?>
    </div>
    <div class="cc-kpi">
        <div class="cc-kpi__label">חשיפה (Reach) במטא</div>
        <div class="cc-kpi__value"><?php echo esc_html($short($meta['reach'])); ?></div>
        <span class="cc-delta cc-delta--none"><?php echo esc_html($fmt($meta['engagements'])); ?> מעורבויות · <?php echo esc_html($fmt($meta['engagement_rate'], 2)); ?>%</span>
    </div>
    <?php endif; ?>
    <?php if (!empty($seo['installed'])): ?>
    <div class="cc-kpi">
        <div class="cc-kpi__label">מאמרי SEO שפורסמו</div>
        <div class="cc-kpi__value"><?php echo esc_html($fmt($seo['published'])); ?></div>
        <?php echo $delta_html($seo['published_delta']); ?>
    </div>
    <div class="cc-kpi">
        <div class="cc-kpi__label">קליקים מגוגל</div>
        <div class="cc-kpi__value"><?php echo $seo['has_gsc'] ? esc_html($short($seo['gsc']['clicks'])) : '—'; ?></div>
        <?php echo $seo['has_gsc'] ? $delta_html($seo['gsc_delta']) : '<span class="cc-delta cc-delta--none">Search Console לא מחובר</span>'; ?>
    </div>
    <?php endif; ?>
    <div class="cc-kpi">
        <div class="cc-kpi__label">לידים ממטא / מגוגל אורגני</div>
        <div class="cc-kpi__value"><?php echo esc_html($fmt($crm['leads_meta'])); ?> <small>/</small> <?php echo esc_html($fmt($crm['leads_seo'])); ?></div>
        <span class="cc-delta cc-delta--none">לפי מקור הליד ב-CRM</span>
    </div>
</section>

<!-- ── Needs attention ──────────────────────────────────────────────── -->
<section class="cc-card cc-alerts" aria-label="דורש טיפול">
    <h2>דורש טיפול</h2>
    <?php if ($alerts): ?>
        <ul>
        <?php foreach ($alerts as [$level, $text, $link]): ?>
            <li class="cc-alert cc-alert--<?php echo esc_attr($level); ?>">
                <span class="cc-alert__icon" aria-hidden="true"><?php echo $level === 'critical' ? '!' : ($level === 'serious' ? '!' : '•'); ?></span>
                <span class="cc-alert__text"><?php echo esc_html($text); ?></span>
                <a href="<?php echo esc_url($link); ?>">לטיפול</a>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="cc-ok"><span aria-hidden="true">✓</span> הכול תקין — אין משהו שמחכה לך כרגע.</p>
    <?php endif; ?>
</section>

<!-- ── Leads ─────────────────────────────────────────────────────────── -->
<section class="cc-section" id="leads">
    <div class="cc-section__head">
        <h2>לידים</h2>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ofnoacomps-crm-leads')); ?>">כל הלידים ←</a>
    </div>

    <div class="cc-grid cc-grid--2-1">
        <div class="cc-card">
            <h3>לידים לפי יום</h3>
            <div class="cc-chart"><canvas id="cc-leads-day" aria-label="לידים לפי יום"></canvas></div>
        </div>
        <div class="cc-card">
            <h3>לפי מקור</h3>
            <?php if ($crm['by_source']): ?>
                <div class="cc-chart"><canvas id="cc-leads-source" aria-label="לידים לפי מקור"></canvas></div>
            <?php else: ?>
                <p class="cc-empty">אין לידים בתקופה.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="cc-grid cc-grid--2-1">
        <div class="cc-card cc-card--table">
            <h3>לידים אחרונים</h3>
            <?php if ($crm['recent']): ?>
            <div class="cc-table-wrap"><table class="cc-table">
                <thead><tr><th>שם</th><th>טלפון</th><th>מקור</th><th>סטטוס</th><th>מתי</th></tr></thead>
                <tbody>
                <?php foreach ($crm['recent'] as $l): ?>
                    <tr>
                        <td><a href="<?php echo esc_url($l['url']); ?>"><?php echo esc_html($l['name']); ?></a></td>
                        <td dir="ltr" class="cc-num"><?php echo $l['phone'] ? '<a href="tel:' . esc_attr($l['phone']) . '">' . esc_html($l['phone']) . '</a>' : '—'; ?></td>
                        <td><?php echo esc_html($l['source']); ?></td>
                        <td><span class="cc-pill cc-pill--<?php echo esc_attr($l['status']); ?>"><?php echo esc_html($l['status_l']); ?></span></td>
                        <td class="cc-muted"><?php echo esc_html($when($l['created'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?>
                <p class="cc-empty">עוד לא נכנסו לידים.</p>
            <?php endif; ?>
        </div>
        <div class="cc-card">
            <h3>סטטוס הלידים בתקופה</h3>
            <?php
            $status_max = max(1, max(array_column($crm['by_status'], 'total') ?: [0]));
            ?>
            <ul class="cc-bars">
            <?php foreach ($crm['by_status'] as $s): ?>
                <li>
                    <span class="cc-bars__label"><?php echo esc_html($s['label']); ?></span>
                    <span class="cc-bars__track"><span class="cc-bars__fill" style="width:<?php echo esc_attr(round($s['total'] / $status_max * 100)); ?>%"></span></span>
                    <span class="cc-bars__val cc-num"><?php echo esc_html($fmt($s['total'])); ?></span>
                </li>
            <?php endforeach; ?>
            </ul>
            <?php if ($crm['stale']): ?>
                <h3 class="cc-sub">מחכים הכי הרבה זמן</h3>
                <ul class="cc-list">
                <?php foreach ($crm['stale'] as $l): ?>
                    <li><a href="<?php echo esc_url($l['url']); ?>"><?php echo esc_html($l['name']); ?></a>
                        <span class="cc-muted"><?php echo esc_html($l['source']); ?> · לפני <?php echo esc_html($ago($l['created'])); ?></span></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── Meta agent ────────────────────────────────────────────────────── -->
<section class="cc-section" id="meta">
    <div class="cc-section__head">
        <h2>סוכן מטא
            <?php if (!empty($meta['installed'])): ?>
                <span class="cc-state cc-state--<?php echo $meta['enabled'] ? 'on' : 'off'; ?>"><?php echo $meta['enabled'] ? 'פעיל' : 'כבוי'; ?></span>
                <?php if ($meta['version']): ?><span class="cc-ver">v<?php echo esc_html($meta['version']); ?></span><?php endif; ?>
            <?php endif; ?>
        </h2>
        <?php if (!empty($meta['installed'])): ?><a href="<?php echo esc_url($meta['admin_url']); ?>">לתור הפרסום ←</a><?php endif; ?>
    </div>

    <?php if (empty($meta['installed'])): ?>
        <div class="cc-card"><p class="cc-empty">Meta Content Agent לא מותקן באתר הזה.</p></div>
    <?php else: ?>
        <div class="cc-chips">
            <?php
            $order = ['planned', 'writing', 'ready', 'pending', 'approved', 'publishing', 'published', 'failed', 'rejected'];
            foreach ($order as $st):
                if (empty($meta['status_counts'][$st])) continue; ?>
                <span class="cc-chip cc-chip--<?php echo esc_attr($st); ?>"><?php echo esc_html($meta['status_labels'][$st] ?? $st); ?> <b class="cc-num"><?php echo esc_html($fmt($meta['status_counts'][$st])); ?></b></span>
            <?php endforeach; ?>
            <span class="cc-chip cc-chip--plain">אישור ידני: <?php echo $meta['require_approval'] ? 'כן' : 'לא'; ?></span>
        </div>

        <div class="cc-grid cc-grid--2-1">
            <div class="cc-card">
                <h3>פוסטים שפורסמו לפי יום</h3>
                <div class="cc-chart"><canvas id="cc-meta-day" aria-label="פוסטים שפורסמו לפי יום ולפי ערוץ"></canvas></div>
            </div>
            <div class="cc-card">
                <h3>הבאים בתור</h3>
                <?php if ($meta['upcoming']): ?>
                    <ul class="cc-list">
                    <?php foreach ($meta['upcoming'] as $u): ?>
                        <li>
                            <span class="cc-list__main"><?php echo esc_html($u['topic']); ?></span>
                            <span class="cc-muted"><?php echo esc_html($when($u['when']) . ' · ' . $channel_name($u['channel']) . ' · ' . strtoupper($u['language'])); ?>
                                · <span class="cc-pill cc-pill--<?php echo esc_attr($u['status']); ?>"><?php echo esc_html($u['status_l']); ?></span></span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="cc-empty">אין פוסטים מתוזמנים.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="cc-card cc-card--table">
            <h3>פורסמו לאחרונה</h3>
            <?php if ($meta['recent']): ?>
            <div class="cc-table-wrap"><table class="cc-table">
                <thead><tr><th>נושא</th><th>ערוץ</th><th>עמוד</th><th class="cc-num">חשיפה</th><th class="cc-num">מעורבות</th><th>מתי</th></tr></thead>
                <tbody>
                <?php foreach ($meta['recent'] as $p): ?>
                    <tr>
                        <td><?php echo $p['permalink'] ? '<a href="' . esc_url($p['permalink']) . '" target="_blank" rel="noopener">' . esc_html($p['topic']) . '</a>' : esc_html($p['topic']); ?></td>
                        <td><?php echo esc_html($channel_name($p['channel']) . ' · ' . strtoupper($p['language'])); ?></td>
                        <td class="cc-muted"><?php echo esc_html($p['target']); ?></td>
                        <td class="cc-num"><?php echo $meta['has_insights'] ? esc_html($fmt($p['reach'])) : '—'; ?></td>
                        <td class="cc-num"><?php echo $meta['has_insights'] ? esc_html($fmt($p['engagements'])) : '—'; ?></td>
                        <td class="cc-muted"><?php echo esc_html($when($p['when'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?>
                <p class="cc-empty">עוד לא פורסמו פוסטים.</p>
            <?php endif; ?>
        </div>

        <?php if ($meta['errors']): ?>
        <div class="cc-card cc-errors">
            <h3>שגיאות אחרונות <a href="<?php echo esc_url($meta['logs_url']); ?>">ליומן ←</a></h3>
            <ul><?php foreach ($meta['errors'] as $e): ?>
                <li><span class="cc-muted"><?php echo esc_html($when($e['when']) . ' · ' . $e['context']); ?></span> <?php echo esc_html($e['message']); ?></li>
            <?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ── SEO agent ─────────────────────────────────────────────────────── -->
<section class="cc-section" id="seo">
    <div class="cc-section__head">
        <h2>סוכן SEO
            <?php if (!empty($seo['installed'])): ?>
                <span class="cc-state cc-state--<?php echo $seo['generation'] ? 'on' : 'off'; ?>"><?php echo $seo['generation'] ? 'כותב' : 'כתיבה כבויה'; ?></span>
                <?php if ($seo['version']): ?><span class="cc-ver">v<?php echo esc_html($seo['version']); ?></span><?php endif; ?>
            <?php endif; ?>
        </h2>
        <?php if (!empty($seo['installed'])): ?><a href="<?php echo esc_url($seo['admin_url']); ?>">לתור התוכן ←</a><?php endif; ?>
    </div>

    <?php if (empty($seo['installed'])): ?>
        <div class="cc-card"><p class="cc-empty">SEO Content Agent לא מותקן באתר הזה.</p></div>
    <?php else: ?>
        <div class="cc-chips">
            <?php foreach (['planned', 'queued', 'generating', 'published', 'failed'] as $st):
                if (empty($seo['status_counts'][$st])) continue; ?>
                <span class="cc-chip cc-chip--<?php echo esc_attr($st); ?>"><?php echo esc_html($seo['status_labels'][$st]); ?> <b class="cc-num"><?php echo esc_html($fmt($seo['status_counts'][$st])); ?></b></span>
            <?php endforeach; ?>
            <span class="cc-chip cc-chip--plain">מילות מפתח חדשות <b class="cc-num"><?php echo esc_html($fmt($seo['keywords_new'])); ?></b></span>
            <?php if ($seo['has_gsc']): ?>
                <span class="cc-chip cc-chip--plain">חשיפות <b class="cc-num"><?php echo esc_html($short($seo['gsc']['impressions'])); ?></b></span>
                <span class="cc-chip cc-chip--plain">CTR <b class="cc-num"><?php echo esc_html($fmt($seo['gsc']['ctr'], 2)); ?>%</b></span>
                <span class="cc-chip cc-chip--plain">מיקום ממוצע <b class="cc-num"><?php echo esc_html($fmt($seo['gsc']['position'], 1)); ?></b></span>
            <?php endif; ?>
            <?php if ($seo['cost_usd'] !== null): ?>
                <span class="cc-chip cc-chip--plain">עלות AI בתקופה <b class="cc-num">$<?php echo esc_html($fmt($seo['cost_usd'], 2)); ?></b></span>
            <?php endif; ?>
        </div>

        <div class="cc-grid cc-grid--2-1">
            <div class="cc-card">
                <h3>קליקים מגוגל לפי יום</h3>
                <?php if ($seo['has_gsc']): ?>
                    <div class="cc-chart"><canvas id="cc-seo-clicks" aria-label="קליקים מגוגל לפי יום"></canvas></div>
                <?php else: ?>
                    <p class="cc-empty">Search Console עוד לא מחובר לסוכן, או שעדיין לא נאספו נתונים.</p>
                <?php endif; ?>
            </div>
            <div class="cc-card">
                <h3>כניסות לאתר לפי ערוץ</h3>
                <?php if ($seo['channels']): ?>
                    <div class="cc-chart"><canvas id="cc-seo-channels" aria-label="כניסות לפי ערוץ"></canvas></div>
                <?php else: ?>
                    <p class="cc-empty">אין נתוני תנועה בתקופה.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="cc-grid cc-grid--2-1">
            <div class="cc-card cc-card--table">
                <h3>מאמרים אחרונים של הסוכן</h3>
                <?php if ($seo['recent']): ?>
                <div class="cc-table-wrap"><table class="cc-table">
                    <thead><tr><th>כותרת</th><th>סטטוס</th><th class="cc-num">GEO</th><th class="cc-num">קליקים</th><th>תאריך</th></tr></thead>
                    <tbody>
                    <?php foreach ($seo['recent'] as $a): ?>
                        <tr>
                            <td><a href="<?php echo esc_url($a['status'] === 'publish' ? $a['view'] : $a['edit']); ?>" <?php echo $a['status'] === 'publish' ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($a['title']); ?></a></td>
                            <td><span class="cc-pill cc-pill--<?php echo esc_attr($a['status']); ?>"><?php echo esc_html($a['status_l']); ?></span></td>
                            <td class="cc-num"><?php echo $a['geo'] !== null ? esc_html($fmt($a['geo'])) : '—'; ?></td>
                            <td class="cc-num"><?php echo $a['clicks'] !== null ? esc_html($fmt($a['clicks'])) : '—'; ?></td>
                            <td class="cc-muted"><?php echo esc_html($when($a['date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php else: ?>
                    <p class="cc-empty">הסוכן עוד לא כתב מאמרים.</p>
                <?php endif; ?>
            </div>
            <div class="cc-card cc-card--table">
                <h3>מנועי AI באתר</h3>
                <?php if ($seo['ai_engines']): ?>
                <div class="cc-table-wrap"><table class="cc-table">
                    <thead><tr><th>מנוע</th><th class="cc-num">סריקות</th><th class="cc-num">הפניות</th></tr></thead>
                    <tbody>
                    <?php foreach ($seo['ai_engines'] as $e): ?>
                        <tr><td><?php echo esc_html($e['engine']); ?></td>
                            <td class="cc-num"><?php echo esc_html($fmt($e['crawls'])); ?></td>
                            <td class="cc-num"><?php echo esc_html($fmt($e['refs'])); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php else: ?>
                    <p class="cc-empty">לא נרשמו ביקורים של מנועי AI בתקופה.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($seo['errors']): ?>
        <div class="cc-card cc-errors">
            <h3>שגיאות אחרונות <a href="<?php echo esc_url($seo['logs_url']); ?>">ליומן ←</a></h3>
            <ul><?php foreach ($seo['errors'] as $e): ?>
                <li><span class="cc-muted"><?php echo esc_html($when($e['when']) . ' · ' . $e['context']); ?></span> <?php echo esc_html($e['message']); ?></li>
            <?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

</main>

<footer class="cc-foot">
    הדף גלוי למנהלי האתר בלבד ואינו נסרק במנועי חיפוש · Ofnoacomps CRM v<?php echo esc_html(OFNOACOMPS_CRM_VERSION); ?>
</footer>

<script>window.ccData = <?php echo wp_json_encode($chart_payload); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?php echo esc_url(OFNOACOMPS_CRM_PLUGIN_URL . 'assets/command-center.js?ver=' . $asset_ver); ?>"></script>
</body>
</html>
