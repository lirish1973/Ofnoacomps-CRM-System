<?php
defined('ABSPATH') || exit;

/**
 * Ofnoacomps CRM — Analytics
 * Provides DB query methods for pageview and event data collected by tracker.js
 */
class Ofnoacomps_CRM_Analytics {

    // ── Date helpers ──────────────────────────────────────────────────────

    private static function date_range(array $args): array {
        if (!empty($args['date_from']) && !empty($args['date_to'])) {
            return [$args['date_from'] . ' 00:00:00', $args['date_to'] . ' 23:59:59'];
        }
        $days  = max(1, (int)($args['days'] ?? 30));
        $from  = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $to    = gmdate('Y-m-d H:i:s');
        return [$from, $to];
    }

    // ── Summary ───────────────────────────────────────────────────────────

    public static function get_summary(array $args = []): array {
        global $wpdb;
        [$from, $to] = self::date_range($args);

        $pv_table = $wpdb->prefix . 'ofnoacomps_pageviews';
        $ev_table = $wpdb->prefix . 'ofnoacomps_events';

        $total_pageviews = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$pv_table} WHERE created_at BETWEEN %s AND %s", $from, $to
        ));

        $unique_sessions = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$pv_table} WHERE created_at BETWEEN %s AND %s", $from, $to
        ));

        $total_clicks = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$ev_table} WHERE created_at BETWEEN %s AND %s", $from, $to
        ));

        $whatsapp_clicks = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$ev_table} WHERE event_type = 'whatsapp_click' AND created_at BETWEEN %s AND %s", $from, $to
        ));

        $phone_clicks = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$ev_table} WHERE event_type = 'phone_click' AND created_at BETWEEN %s AND %s", $from, $to
        ));

        $button_clicks = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$ev_table} WHERE event_type = 'button_click' AND created_at BETWEEN %s AND %s", $from, $to
        ));

        // Device breakdown
        $devices = $wpdb->get_results($wpdb->prepare(
            "SELECT device_type, COUNT(*) as count FROM {$pv_table}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY device_type ORDER BY count DESC", $from, $to
        ), ARRAY_A);

        // Top pages
        $top_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT page_url, COUNT(*) as views FROM {$pv_table}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY page_url ORDER BY views DESC LIMIT 10", $from, $to
        ), ARRAY_A);

        return [
            'total_pageviews' => $total_pageviews,
            'unique_sessions' => $unique_sessions,
            'total_clicks'    => $total_clicks,
            'whatsapp_clicks' => $whatsapp_clicks,
            'phone_clicks'    => $phone_clicks,
            'button_clicks'   => $button_clicks,
            'devices'         => $devices,
            'top_pages'       => $top_pages,
            'date_from'       => $from,
            'date_to'         => $to,
        ];
    }

    // ── Pageviews over time ───────────────────────────────────────────────

    public static function get_pageviews_over_time(array $args = []): array {
        global $wpdb;
        [$from, $to] = self::date_range($args);
        $group = ($args['group_by'] ?? 'day') === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';

        $pv_table = $wpdb->prefix . 'ofnoacomps_pageviews';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '{$group}') as period,
                    COUNT(*) as pageviews,
                    COUNT(DISTINCT session_id) as unique_sessions
             FROM {$pv_table}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY period ORDER BY period ASC", $from, $to
        ), ARRAY_A);

        return $rows ?: [];
    }

    // ── Events breakdown ──────────────────────────────────────────────────

    public static function get_events_breakdown(array $args = []): array {
        global $wpdb;
        [$from, $to] = self::date_range($args);
        $ev_table = $wpdb->prefix . 'ofnoacomps_events';

        $type_filter = '';
        $params      = [$from, $to];
        if (!empty($args['event_type'])) {
            $type_filter = ' AND event_type = %s';
            $params[]    = sanitize_text_field($args['event_type']);
        }

        // Counts by event type
        $by_type = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count
             FROM {$ev_table}
             WHERE created_at BETWEEN %s AND %s{$type_filter}
             GROUP BY event_type ORDER BY count DESC",
            ...$params
        ), ARRAY_A);

        // Top event labels
        $top_labels = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, event_label, COUNT(*) as count
             FROM {$ev_table}
             WHERE created_at BETWEEN %s AND %s{$type_filter}
             GROUP BY event_type, event_label ORDER BY count DESC LIMIT 20",
            ...$params
        ), ARRAY_A);

        // Events over time
        $over_time = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as period, event_type, COUNT(*) as count
             FROM {$ev_table}
             WHERE created_at BETWEEN %s AND %s{$type_filter}
             GROUP BY period, event_type ORDER BY period ASC",
            ...$params
        ), ARRAY_A);

        return [
            'by_type'    => $by_type   ?: [],
            'top_labels' => $top_labels ?: [],
            'over_time'  => $over_time  ?: [],
        ];
    }

    // ── Traffic sources ───────────────────────────────────────────────────

    public static function get_traffic_sources(array $args = []): array {
        global $wpdb;
        [$from, $to] = self::date_range($args);
        $pv_table = $wpdb->prefix . 'ofnoacomps_pageviews';

        $sources = $wpdb->get_results($wpdb->prepare(
            "SELECT source,
                    COUNT(*) as pageviews,
                    COUNT(DISTINCT session_id) as sessions
             FROM {$pv_table}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY source ORDER BY sessions DESC", $from, $to
        ), ARRAY_A);

        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT campaign, source, COUNT(*) as sessions
             FROM {$pv_table}
             WHERE campaign != '' AND created_at BETWEEN %s AND %s
             GROUP BY campaign, source ORDER BY sessions DESC LIMIT 20", $from, $to
        ), ARRAY_A);

        return [
            'sources'   => $sources   ?: [],
            'campaigns' => $campaigns ?: [],
        ];
    }
}