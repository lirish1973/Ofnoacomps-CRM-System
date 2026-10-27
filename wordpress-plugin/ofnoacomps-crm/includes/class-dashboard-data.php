<?php
/**
 * Command Center data layer.
 *
 * Collects read-only figures from three sources that may or may not be
 * installed on the same site:
 *   - Ofnoacomps CRM   (always — this plugin)
 *   - Meta Content Agent (tables wp_mca_*)  — optional
 *   - SEO Content Agent  (tables wp_sca_*)  — optional
 *
 * Nothing here writes to the database. Every query against a sibling plugin is
 * guarded by a table-existence check, so a missing or older agent simply shows
 * as "not installed" instead of producing SQL errors.
 *
 * All periods are computed in GMT. MCA stores its times with gmdate(); the CRM
 * tables use MySQL CURRENT_TIMESTAMP and SCA uses DATE columns, so a few hours of
 * edge drift at day boundaries is possible and acceptable for a dashboard.
 */

defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Dashboard_Data {

    /** Allowed range lengths in days. */
    const RANGES = [7, 30, 90];

    /** @var int */
    private $days;

    /** @var int Unix timestamp of range start (00:00 GMT). */
    private $start_ts;

    /** @var int Unix timestamp of now. */
    private $now_ts;

    /** @var array<string,bool> */
    private $table_cache = [];

    public function __construct(int $days = 30) {
        $this->days     = in_array($days, self::RANGES, true) ? $days : 30;
        $this->now_ts   = time();
        $this->start_ts = strtotime(gmdate('Y-m-d 00:00:00', $this->now_ts - ($this->days - 1) * DAY_IN_SECONDS));
    }

    public function days(): int {
        return $this->days;
    }

    // ── Range helpers ─────────────────────────────────────────────────────────

    private function from(): string {
        return gmdate('Y-m-d H:i:s', $this->start_ts);
    }

    private function to(): string {
        return gmdate('Y-m-d H:i:s', $this->now_ts);
    }

    private function prev_from(): string {
        return gmdate('Y-m-d H:i:s', $this->start_ts - $this->days * DAY_IN_SECONDS);
    }

    private function from_day(): string {
        return gmdate('Y-m-d', $this->start_ts);
    }

    private function to_day(): string {
        return gmdate('Y-m-d', $this->now_ts);
    }

    /** Every day in the range as Y-m-d keys, oldest first. */
    private function day_keys(): array {
        $out = [];
        for ($i = 0; $i < $this->days; $i++) {
            $out[] = gmdate('Y-m-d', $this->start_ts + $i * DAY_IN_SECONDS);
        }
        return $out;
    }

    /**
     * Map rows of {day, value} onto the full day axis (missing days = 0).
     *
     * @param array  $rows  Objects or arrays with the given keys.
     * @param string $day_key
     * @param string $val_key
     * @return int[] Indexed in the same order as day_keys().
     */
    private function fill_days(array $rows, string $day_key = 'day', string $val_key = 'total'): array {
        $map = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $map[(string) $r[$day_key]] = (float) $r[$val_key];
        }
        $out = [];
        foreach ($this->day_keys() as $d) {
            $out[] = $map[$d] ?? 0;
        }
        return $out;
    }

    /** Percentage change, or null when there is no baseline. */
    private static function delta($current, $previous): ?float {
        $current  = (float) $current;
        $previous = (float) $previous;
        if ($previous <= 0) {
            return null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function table_exists(string $table): bool {
        global $wpdb;
        if (!isset($this->table_cache[$table])) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            $this->table_cache[$table] = ($found === $table);
        }
        return $this->table_cache[$table];
    }

    /** Read an agent's setting through its own class when available. */
    private static function agent_setting(string $class, string $option, string $key, $default = null) {
        if (class_exists($class) && method_exists($class, 'get')) {
            return call_user_func([$class, 'get'], $key, $default);
        }
        $opts = get_option($option, []);
        return is_array($opts) && array_key_exists($key, $opts) ? $opts[$key] : $default;
    }

    // ── Public: everything at once ────────────────────────────────────────────

    public function all(): array {
        return [
            'generated_at' => current_time('mysql'),
            'range_days'   => $this->days,
            'labels'       => $this->day_keys(),
            'crm'          => $this->crm(),
            'meta'         => $this->meta(),
            'seo'          => $this->seo(),
        ];
    }

    // ── CRM ───────────────────────────────────────────────────────────────────

    public function crm(): array {
        global $wpdb;
        $t = Ofnoacomps_CRM_Database::table('leads');

        $count_between = function (string $from, string $to, string $extra = '') use ($wpdb, $t): int {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $t WHERE created_at >= %s AND created_at < %s $extra",
                $from,
                $to
            ));
        };

        $leads      = $count_between($this->from(), $this->to());
        $leads_prev = $count_between($this->prev_from(), $this->from());

        $meta_sources = "('facebook_organic','facebook_ads','instagram')";
        $leads_meta   = $count_between($this->from(), $this->to(), "AND source IN $meta_sources");
        $leads_seo    = $count_between($this->from(), $this->to(), "AND source = 'google_organic'");

        $converted = $count_between($this->from(), $this->to(), "AND status = 'converted'");

        $new_open = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE status = 'new'");

        $stale_cutoff = gmdate('Y-m-d H:i:s', $this->now_ts - 2 * DAY_IN_SECONDS);
        $stale_count  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $t WHERE status = 'new' AND created_at < %s",
            $stale_cutoff
        ));
        $stale_list = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, phone, email, source, created_at
             FROM $t WHERE status = 'new' AND created_at < %s
             ORDER BY created_at DESC LIMIT 6",
            $stale_cutoff
        ));

        $by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM $t WHERE created_at >= %s AND created_at < %s
             GROUP BY day",
            $this->from(),
            $this->to()
        ));

        $source_labels = Ofnoacomps_CRM_Lead::get_sources();
        $by_source_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT source, COUNT(*) AS total
             FROM $t WHERE created_at >= %s AND created_at < %s
             GROUP BY source ORDER BY total DESC LIMIT 8",
            $this->from(),
            $this->to()
        ));
        $by_source = [];
        foreach ($by_source_raw as $r) {
            $by_source[] = [
                'label' => $source_labels[$r->source] ?? $r->source,
                'total' => (int) $r->total,
            ];
        }

        $status_labels = Ofnoacomps_CRM_Lead::get_statuses();
        $by_status_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS total
             FROM $t WHERE created_at >= %s AND created_at < %s
             GROUP BY status",
            $this->from(),
            $this->to()
        ));
        $by_status = [];
        foreach ($status_labels as $key => $label) {
            $by_status[$key] = ['label' => $label, 'total' => 0];
        }
        foreach ($by_status_raw as $r) {
            if (!isset($by_status[$r->status])) {
                $by_status[$r->status] = ['label' => $r->status, 'total' => 0];
            }
            $by_status[$r->status]['total'] = (int) $r->total;
        }

        $recent = $wpdb->get_results(
            "SELECT id, first_name, last_name, email, phone, source, status, form_name, created_at
             FROM $t ORDER BY created_at DESC LIMIT 8"
        );

        $df = $this->from_day();
        $dt = $this->to_day();

        return [
            'leads'           => $leads,
            'leads_delta'     => self::delta($leads, $leads_prev),
            'leads_meta'      => $leads_meta,
            'leads_seo'       => $leads_seo,
            'conversion_rate' => $leads > 0 ? round($converted / $leads * 100, 1) : 0,
            'new_open'        => $new_open,
            'stale_count'     => $stale_count,
            'stale'           => array_map([$this, 'lead_row'], $stale_list ?: []),
            'pipeline_open'   => (float) Ofnoacomps_CRM_Reports::open_pipeline_value(),
            'won_period'      => (float) Ofnoacomps_CRM_Reports::won_deals_value($df, $dt),
            'by_day'          => $this->fill_days($by_day ?: []),
            'by_source'       => $by_source,
            'by_status'       => array_values($by_status),
            'recent'          => array_map([$this, 'lead_row'], $recent ?: []),
            'currency'        => get_option('ofnoacomps_crm_currency', '₪'),
        ];
    }

    private function lead_row($r): array {
        $sources  = Ofnoacomps_CRM_Lead::get_sources();
        $statuses = Ofnoacomps_CRM_Lead::get_statuses();
        $name     = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
        return [
            'id'       => (int) $r->id,
            'name'     => $name !== '' ? $name : '(ללא שם)',
            'phone'    => (string) ($r->phone ?? ''),
            'email'    => (string) ($r->email ?? ''),
            'source'   => $sources[$r->source ?? ''] ?? (string) ($r->source ?? ''),
            'status'   => (string) ($r->status ?? ''),
            'status_l' => $statuses[$r->status ?? ''] ?? (string) ($r->status ?? ''),
            'form'     => (string) ($r->form_name ?? ''),
            'created'  => (string) $r->created_at,
            'url'      => admin_url('admin.php?page=ofnoacomps-crm-leads&action=view&id=' . (int) $r->id),
        ];
    }

    // ── Meta Content Agent ────────────────────────────────────────────────────

    public function meta(): array {
        global $wpdb;
        $q    = $wpdb->prefix . 'mca_queue';
        $ins  = $wpdb->prefix . 'mca_insights';
        $logs = $wpdb->prefix . 'mca_logs';

        if (!$this->table_exists($q)) {
            return ['installed' => false];
        }

        $enabled          = (bool) self::agent_setting('MCA_Settings', 'mca_settings', 'enabled', 0);
        $require_approval = (bool) self::agent_setting('MCA_Settings', 'mca_settings', 'require_approval', 1);

        $status_counts = [];
        foreach ((array) $wpdb->get_results("SELECT status, COUNT(*) AS c FROM $q GROUP BY status") as $r) {
            $status_counts[$r->status] = (int) $r->c;
        }

        $published = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $q WHERE status = 'published' AND published_at >= %s AND published_at < %s",
            $this->from(),
            $this->to()
        ));
        $published_prev = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $q WHERE status = 'published' AND published_at >= %s AND published_at < %s",
            $this->prev_from(),
            $this->from()
        ));

        // 'ready' means "written, waiting" — with approval on that is a human task.
        $awaiting = ($require_approval ? ($status_counts['ready'] ?? 0) : 0) + ($status_counts['pending'] ?? 0);

        $failed_period = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $q WHERE status = 'failed' AND updated_at >= %s",
            $this->from()
        ));

        $by_channel_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(published_at) AS day, channel, COUNT(*) AS total
             FROM $q WHERE status = 'published' AND published_at >= %s AND published_at < %s
             GROUP BY day, channel",
            $this->from(),
            $this->to()
        ));
        $fb = $ig = [];
        foreach ((array) $by_channel_rows as $r) {
            if ($r->channel === 'instagram') {
                $ig[] = $r;
            } else {
                $fb[] = $r;
            }
        }

        // Insights are daily snapshots of lifetime values — take MAX per post.
        $has_ins = $this->table_exists($ins);
        $totals  = ['reach' => 0, 'impressions' => 0, 'engagements' => 0, 'clicks' => 0];
        $recent  = [];

        if ($has_ins) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COALESCE(SUM(m.reach),0) AS reach, COALESCE(SUM(m.impressions),0) AS impressions,
                        COALESCE(SUM(m.engagements),0) AS engagements, COALESCE(SUM(m.clicks),0) AS clicks
                 FROM $q qq
                 JOIN (SELECT remote_id, MAX(reach) reach, MAX(impressions) impressions,
                              MAX(engagements) engagements, MAX(clicks) clicks
                       FROM $ins GROUP BY remote_id) m ON m.remote_id = qq.remote_id
                 WHERE qq.status = 'published' AND qq.remote_id <> '' AND qq.published_at >= %s AND qq.published_at < %s",
                $this->from(),
                $this->to()
            ));
            if ($row) {
                $totals = array_map('intval', (array) $row);
            }

            $recent = $wpdb->get_results(
                "SELECT qq.id, qq.topic, qq.channel, qq.language, qq.target_name, qq.permalink, qq.published_at,
                        COALESCE(m.reach,0) reach, COALESCE(m.engagements,0) engagements
                 FROM $q qq
                 LEFT JOIN (SELECT remote_id, MAX(reach) reach, MAX(engagements) engagements
                            FROM $ins GROUP BY remote_id) m ON m.remote_id = qq.remote_id AND qq.remote_id <> ''
                 WHERE qq.status = 'published'
                 ORDER BY qq.published_at DESC LIMIT 8"
            );
        } else {
            $recent = $wpdb->get_results(
                "SELECT id, topic, channel, language, target_name, permalink, published_at, 0 reach, 0 engagements
                 FROM $q WHERE status = 'published' ORDER BY published_at DESC LIMIT 8"
            );
        }

        $upcoming = $wpdb->get_results($wpdb->prepare(
            "SELECT id, topic, channel, language, target_name, status, scheduled_for
             FROM $q WHERE status IN ('planned','writing','ready','approved','pending') AND scheduled_for >= %s
             ORDER BY scheduled_for ASC LIMIT 6",
            gmdate('Y-m-d H:i:s', $this->now_ts - HOUR_IN_SECONDS)
        ));

        $errors = $this->table_exists($logs) ? $this->recent_errors($logs) : [];

        $status_labels = [
            'planned'    => 'מתוכנן',
            'writing'    => 'בכתיבה',
            'ready'      => $require_approval ? 'ממתין לאישור' : 'מוכן',
            'pending'    => 'ממתין לאישור',
            'approved'   => 'אושר',
            'publishing' => 'בפרסום',
            'published'  => 'פורסם',
            'failed'     => 'נכשל',
            'rejected'   => 'נדחה',
            'skipped'    => 'דולג',
        ];

        return [
            'installed'        => true,
            'version'          => defined('MCA_VERSION') ? MCA_VERSION : '',
            'enabled'          => $enabled,
            'require_approval' => $require_approval,
            'published'        => $published,
            'published_delta'  => self::delta($published, $published_prev),
            'awaiting'         => $awaiting,
            'failed_period'    => $failed_period,
            'status_counts'    => $status_counts,
            'status_labels'    => $status_labels,
            'has_insights'     => $has_ins,
            'reach'            => $totals['reach'],
            'impressions'      => $totals['impressions'],
            'engagements'      => $totals['engagements'],
            'clicks'           => $totals['clicks'],
            'engagement_rate'  => $totals['reach'] > 0 ? round($totals['engagements'] / $totals['reach'] * 100, 2) : 0,
            'per_day_fb'       => $this->fill_days($fb),
            'per_day_ig'       => $this->fill_days($ig),
            'recent'           => array_map(function ($r) use ($status_labels) {
                return [
                    'topic'       => (string) $r->topic,
                    'channel'     => (string) $r->channel,
                    'language'    => (string) $r->language,
                    'target'      => (string) $r->target_name,
                    'permalink'   => (string) $r->permalink,
                    'when'        => $this->gmt_to_local((string) $r->published_at),
                    'reach'       => (int) $r->reach,
                    'engagements' => (int) $r->engagements,
                ];
            }, $recent ?: []),
            'upcoming'         => array_map(function ($r) use ($status_labels) {
                return [
                    'topic'    => (string) $r->topic,
                    'channel'  => (string) $r->channel,
                    'language' => (string) $r->language,
                    'target'   => (string) $r->target_name,
                    'status'   => (string) $r->status,
                    'status_l' => $status_labels[$r->status] ?? $r->status,
                    'when'     => $this->gmt_to_local((string) $r->scheduled_for),
                ];
            }, $upcoming ?: []),
            'errors'           => $errors,
            'admin_url'        => admin_url('admin.php?page=meta-content-agent-queue'),
            'logs_url'         => admin_url('admin.php?page=meta-content-agent-logs'),
        ];
    }

    // ── SEO Content Agent ─────────────────────────────────────────────────────

    public function seo(): array {
        global $wpdb;
        $q       = $wpdb->prefix . 'sca_queue';
        $perf    = $wpdb->prefix . 'sca_perf';
        $traffic = $wpdb->prefix . 'sca_traffic';
        $ai      = $wpdb->prefix . 'sca_ai_hits';
        $usage   = $wpdb->prefix . 'sca_ai_usage';
        $logs    = $wpdb->prefix . 'sca_logs';
        $kw      = $wpdb->prefix . 'sca_keywords';

        if (!$this->table_exists($q)) {
            return ['installed' => false];
        }

        $generation       = (bool) self::agent_setting('SCA_Settings', 'sca_settings', 'generation_enabled', 1);
        $agent_on         = (bool) self::agent_setting('SCA_Settings', 'sca_settings', 'enabled', 0);
        $require_approval = (bool) self::agent_setting('SCA_Settings', 'sca_settings', 'require_approval', 1);

        $status_counts = [];
        foreach ((array) $wpdb->get_results("SELECT status, COUNT(*) AS c FROM $q GROUP BY status") as $r) {
            $status_counts[$r->status] = (int) $r->c;
        }

        // Articles the agent wrote — identified by the _sca_generated post meta.
        $count_articles = function (string $from, string $to) use ($wpdb): int {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sca_generated'
                 WHERE p.post_status = 'publish' AND p.post_date_gmt >= %s AND p.post_date_gmt < %s",
                $from,
                $to
            ));
        };
        $published      = $count_articles($this->from(), $this->to());
        $published_prev = $count_articles($this->prev_from(), $this->from());

        $drafts = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sca_generated'
             WHERE p.post_status IN ('draft','pending')"
        );

        $recent_posts = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_status, p.post_date,
                    (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_sca_geo_score' LIMIT 1) AS geo
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sca_generated'
             WHERE p.post_status IN ('publish','draft','pending','future')
             GROUP BY p.ID
             ORDER BY p.post_date DESC LIMIT 8"
        );

        // Search Console.
        $has_perf     = $this->table_exists($perf);
        $gsc          = ['clicks' => 0, 'impressions' => 0, 'position' => 0, 'ctr' => 0];
        $gsc_prev     = 0;
        $clicks_day   = array_fill(0, $this->days, 0);
        $post_clicks  = [];
        if ($has_perf) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COALESCE(SUM(clicks),0) clicks, COALESCE(SUM(impressions),0) impressions,
                        COALESCE(SUM(position * impressions),0) wpos
                 FROM $perf WHERE day >= %s AND day <= %s",
                $this->from_day(),
                $this->to_day()
            ));
            if ($row) {
                $imp = (int) $row->impressions;
                $gsc = [
                    'clicks'      => (int) $row->clicks,
                    'impressions' => $imp,
                    'position'    => $imp > 0 ? round($row->wpos / $imp, 1) : 0,
                    'ctr'         => $imp > 0 ? round($row->clicks / $imp * 100, 2) : 0,
                ];
            }
            $gsc_prev = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(clicks),0) FROM $perf WHERE day >= %s AND day < %s",
                gmdate('Y-m-d', strtotime($this->prev_from())),
                $this->from_day()
            ));
            $clicks_day = $this->fill_days((array) $wpdb->get_results($wpdb->prepare(
                "SELECT day, SUM(clicks) total FROM $perf WHERE day >= %s AND day <= %s GROUP BY day",
                $this->from_day(),
                $this->to_day()
            )));
            if ($recent_posts) {
                $ids = implode(',', array_map('intval', wp_list_pluck($recent_posts, 'ID')));
                foreach ((array) $wpdb->get_results($wpdb->prepare(
                    "SELECT post_id, SUM(clicks) c FROM $perf WHERE post_id IN ($ids) AND day >= %s GROUP BY post_id",
                    $this->from_day()
                )) as $r) {
                    $post_clicks[(int) $r->post_id] = (int) $r->c;
                }
            }
        }

        // Site traffic split by channel (SCA's own lightweight counter).
        $channels = [];
        if ($this->table_exists($traffic)) {
            $labels = ['organic' => 'חיפוש אורגני', 'ai' => 'מנועי AI', 'social' => 'רשתות חברתיות',
                       'direct' => 'ישיר', 'referral' => 'הפניות', 'email' => 'אימייל', 'paid' => 'ממומן'];
            foreach ((array) $wpdb->get_results($wpdb->prepare(
                "SELECT channel, SUM(views) total FROM $traffic WHERE day >= %s AND day <= %s
                 GROUP BY channel ORDER BY total DESC LIMIT 8",
                $this->from_day(),
                $this->to_day()
            )) as $r) {
                $channels[] = ['label' => $labels[$r->channel] ?? $r->channel, 'total' => (int) $r->total];
            }
        }

        // AI engines: crawls and referral visits.
        $ai_engines = [];
        $ai_refs    = 0;
        if ($this->table_exists($ai)) {
            foreach ((array) $wpdb->get_results($wpdb->prepare(
                "SELECT engine, SUM(CASE WHEN hit_type='crawl' THEN hits ELSE 0 END) crawls,
                        SUM(CASE WHEN hit_type='referral' THEN hits ELSE 0 END) refs
                 FROM $ai WHERE day >= %s AND day <= %s AND engine <> ''
                 GROUP BY engine ORDER BY crawls + refs DESC LIMIT 6",
                $this->from_day(),
                $this->to_day()
            )) as $r) {
                $ai_engines[] = ['engine' => $r->engine, 'crawls' => (int) $r->crawls, 'refs' => (int) $r->refs];
                $ai_refs     += (int) $r->refs;
            }
        }

        $cost = null;
        if ($this->table_exists($usage)) {
            $cost = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(cost_usd),0) FROM $usage WHERE day >= %s AND day <= %s",
                $this->from_day(),
                $this->to_day()
            ));
        }

        $kw_new = $this->table_exists($kw)
            ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $kw WHERE status = 'new'")
            : 0;

        $status_labels = [
            'planned'    => 'מתוכנן',
            'queued'     => 'בתור',
            'generating' => 'בכתיבה',
            'published'  => 'נכתב',
            'failed'     => 'נכשל',
        ];

        $post_status_labels = ['publish' => 'פורסם', 'draft' => 'טיוטה', 'pending' => 'ממתין לאישור', 'future' => 'מתוזמן'];

        return [
            'installed'        => true,
            'version'          => defined('SCA_VERSION') ? SCA_VERSION : '',
            'enabled'          => $agent_on,
            'generation'       => $generation,
            'require_approval' => $require_approval,
            'published'        => $published,
            'published_delta'  => self::delta($published, $published_prev),
            'drafts'           => $drafts,
            'status_counts'    => $status_counts,
            'status_labels'    => $status_labels,
            'keywords_new'     => $kw_new,
            'has_gsc'          => $has_perf && ($gsc['impressions'] > 0 || $gsc_prev > 0),
            'gsc'              => $gsc,
            'gsc_delta'        => self::delta($gsc['clicks'], $gsc_prev),
            'clicks_day'       => $clicks_day,
            'channels'         => $channels,
            'ai_engines'       => $ai_engines,
            'ai_refs'          => $ai_refs,
            'cost_usd'         => $cost,
            'recent'           => array_map(function ($p) use ($post_clicks, $post_status_labels) {
                return [
                    'title'    => $p->post_title !== '' ? (string) $p->post_title : '(ללא כותרת)',
                    'status'   => (string) $p->post_status,
                    'status_l' => $post_status_labels[$p->post_status] ?? $p->post_status,
                    'date'     => (string) $p->post_date,
                    'geo'      => $p->geo !== null && $p->geo !== '' ? round((float) $p->geo) : null,
                    'clicks'   => $post_clicks[(int) $p->ID] ?? null,
                    'view'     => get_permalink((int) $p->ID),
                    'edit'     => get_edit_post_link((int) $p->ID, 'raw'),
                ];
            }, $recent_posts ?: []),
            'errors'           => $this->table_exists($logs) ? $this->recent_errors($logs) : [],
            'admin_url'        => admin_url('admin.php?page=sca-queue'),
            'logs_url'         => admin_url('admin.php?page=sca-logs'),
        ];
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    /** Last five error rows from an agent's log table, within the range. */
    private function recent_errors(string $table): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT context, message, created_at FROM $table
             WHERE level = 'error' AND created_at >= %s
             ORDER BY id DESC LIMIT 5",
            $this->from()
        ));
        $out = [];
        foreach ((array) $rows as $r) {
            $out[] = [
                'context' => (string) $r->context,
                'message' => wp_trim_words(wp_strip_all_tags((string) $r->message), 24, '…'),
                'when'    => (string) $r->created_at,
            ];
        }
        return $out;
    }

    private function gmt_to_local(string $gmt): string {
        if ($gmt === '' || $gmt === '0000-00-00 00:00:00') {
            return '';
        }
        return get_date_from_gmt($gmt, 'Y-m-d H:i:s');
    }
}
