<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Reports {

    /** Summary stats for the dashboard header cards. */
    public static function dashboard_summary(string $date_from = '', string $date_to = ''): array {
        $df = $date_from ?: date('Y-m-01');
        $dt = $date_to   ?: date('Y-m-d');

        return [
            'leads_total'      => Ofnoacomps_CRM_Lead::count(),
            'leads_period'     => Ofnoacomps_CRM_Lead::count(['date_from' => $df, 'date_to' => $dt]),
            'leads_new'        => Ofnoacomps_CRM_Lead::count(['status' => 'new']),
            'customers_total'  => Ofnoacomps_CRM_Customer::count(),
            'customers_active' => Ofnoacomps_CRM_Customer::count(['status' => 'active']),
            'deals_open_amount'=> self::open_pipeline_value(),
            'deals_won_period' => self::won_deals_value($df, $dt),
            'conversion_rate'  => self::lead_conversion_rate($df, $dt),
        ];
    }

    /** Leads per day for a time series chart. */
    public static function leads_over_time(string $date_from, string $date_to, string $group_by = 'day'): array {
        global $wpdb;
        $table = Ofnoacomps_CRM_Database::table('leads');

        $format = $group_by === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '$format') AS period, COUNT(*) AS count
             FROM $table
             WHERE created_at >= %s AND created_at <= %s
             GROUP BY period ORDER BY period ASC",
            $date_from . ' 00:00:00',
            $date_to   . ' 23:59:59'
        ));

        return $rows ?: [];
    }

    /** Leads by source. */
    public static function leads_by_source(string $date_from = '', string $date_to = ''): array {
        global $wpdb;
        $table  = Ofnoacomps_CRM_Database::table('leads');
        $params = [];
        $where  = '1=1';

        if ($date_from) { $where .= ' AND created_at >= %s'; $params[] = $date_from . ' 00:00:00'; }
        if ($date_to)   { $where .= ' AND created_at <= %s'; $params[] = $date_to   . ' 23:59:59'; }

        $sql = "SELECT source, COUNT(*) AS total FROM $table WHERE $where GROUP BY source ORDER BY total DESC";
        $rows = empty($params) ? $wpdb->get_results($sql) : $wpdb->get_results($wpdb->prepare($sql, $params));

        $labels = Ofnoacomps_CRM_Lead::get_sources();
        foreach ($rows as &$row) {
            $row->label = $labels[$row->source] ?? $row->source;
        }
        return $rows ?: [];
    }

    /** Leads by status. */
    public static function leads_by_status(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS count FROM " . Ofnoacomps_CRM_Database::table('leads') . " GROUP BY status"
        );
        $labels = Ofnoacomps_CRM_Lead::get_statuses();
        foreach ($rows as &$row) {
            $row->label = $labels[$row->status] ?? $row->status;
        }
        return $rows ?: [];
    }

    /** Pipeline funnel — deals count and value per stage. */
    public static function pipeline_funnel(int $pipeline_id = 0): array {
        global $wpdb;
        $d_table = Ofnoacomps_CRM_Database::table('deals');
        $s_table = Ofnoacomps_CRM_Database::table('stages');

        if (!$pipeline_id) {
            $pipeline_id = (int) $wpdb->get_var(
                "SELECT id FROM " . Ofnoacomps_CRM_Database::table('pipelines') . " WHERE is_default=1 LIMIT 1"
            );
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.name, s.color, s.sort_order, s.is_won, s.is_lost,
                    COUNT(d.id) AS deal_count,
                    COALESCE(SUM(d.amount),0) AS total_value
             FROM $s_table s
             LEFT JOIN $d_table d ON d.stage_id = s.id AND d.status = 'open'
             WHERE s.pipeline_id = %d
             GROUP BY s.id ORDER BY s.sort_order ASC",
            $pipeline_id
        )) ?: [];
    }

    /** Won deals revenue over time. */
    public static function revenue_over_time(string $date_from, string $date_to, string $group_by = 'month'): array {
        global $wpdb;
        $table  = Ofnoacomps_CRM_Database::table('deals');
        $format = $group_by === 'day' ? '%Y-%m-%d' : '%Y-%m';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(closed_at, '$format') AS period,
                    COUNT(*) AS count, SUM(amount) AS revenue
             FROM $table
             WHERE status='won' AND closed_at >= %s AND closed_at <= %s
             GROUP BY period ORDER BY period ASC",
            $date_from . ' 00:00:00',
            $date_to   . ' 23:59:59'
        )) ?: [];
    }

    /** Lead-to-customer conversion rate in %. */
    public static function lead_conversion_rate(string $date_from = '', string $date_to = ''): float {
        $total     = Ofnoacomps_CRM_Lead::count(['date_from' => $date_from, 'date_to' => $date_to]);
        $converted = Ofnoacomps_CRM_Lead::count(['status' => 'converted', 'date_from' => $date_from, 'date_to' => $date_to]);
        if (!$total) return 0.0;
        return round($converted / $total * 100, 1);
    }

    /** Total value of open deals. */
    public static function open_pipeline_value(): float {
        global $wpdb;
        return (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount),0) FROM " . Ofnoacomps_CRM_Database::table('deals') . " WHERE status='open'"
        );
    }

    /** Total value of won deals in a period. */
    public static function won_deals_value(string $date_from, string $date_to): float {
        global $wpdb;
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM " . Ofnoacomps_CRM_Database::table('deals') .
            " WHERE status='won' AND closed_at >= %s AND closed_at <= %s",
            $date_from . ' 00:00:00',
            $date_to   . ' 23:59:59'
        ));
    }

    /** Activity breakdown by type for a user/period. */
    public static function activities_by_type(string $date_from = '', string $date_to = '', int $user_id = 0): array {
        global $wpdb;
        $table  = Ofnoacomps_CRM_Database::table('activities');
        $where  = '1=1';
        $params = [];
        if ($user_id)  { $where .= ' AND user_id = %d';     $params[] = $user_id; }
        if ($date_from){ $where .= ' AND created_at >= %s'; $params[] = $date_from . ' 00:00:00'; }
        if ($date_to)  { $where .= ' AND created_at <= %s'; $params[] = $date_to   . ' 23:59:59'; }

        $sql  = "SELECT type, COUNT(*) AS count FROM $table WHERE $where GROUP BY type ORDER BY count DESC";
        $rows = empty($params) ? $wpdb->get_results($sql) : $wpdb->get_results($wpdb->prepare($sql, $params));

        $labels = Ofnoacomps_CRM_Activity::get_types();
        foreach ($rows as &$r) { $r->label = $labels[$r->type] ?? $r->type; }
        return $rows ?: [];
    }

    /** Rep leaderboard — deals won + value per user. */
    public static function rep_leaderboard(string $date_from, string $date_to): array {
        global $wpdb;
        $table = Ofnoacomps_CRM_Database::table('deals');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT owner_id, COUNT(*) AS deals_won, SUM(amount) AS revenue
             FROM $table
             WHERE status='won' AND closed_at >= %s AND closed_at <= %s
             GROUP BY owner_id ORDER BY revenue DESC",
            $date_from . ' 00:00:00',
            $date_to   . ' 23:59:59'
        ));

        foreach ($rows as &$row) {
            $user = get_user_by('id', $row->owner_id);
            $row->user_name = $user ? $user->display_name : 'משתמש #' . $row->owner_id;
        }

        return $rows ?: [];
    }
}
