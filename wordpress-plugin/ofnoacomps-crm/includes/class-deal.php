<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Deal {

    public static function create(array $data): int {
        global $wpdb;
        $table = Ofnoacomps_CRM_Database::table('deals');

        // Get default pipeline if not specified
        if (empty($data['pipeline_id'])) {
            $data['pipeline_id'] = self::get_default_pipeline();
        }
        if (empty($data['stage_id'])) {
            $data['stage_id'] = self::get_first_stage((int) $data['pipeline_id']);
        }

        // Get probability from stage
        $stage = Ofnoacomps_CRM_Pipeline::get_stage((int) $data['stage_id']);
        if ($stage && empty($data['probability'])) {
            $data['probability'] = $stage->probability;
        }

        $defaults = [
            'name'        => '',
            'pipeline_id' => 0,
            'stage_id'    => 0,
            'customer_id' => null,
            'lead_id'     => null,
            'owner_id'    => get_current_user_id() ?: 0,
            'amount'      => 0.00,
            'currency'    => 'ILS',
            'probability' => 0,
            'close_date'  => null,
            'status'      => 'open',
            'notes'       => '',
        ];

        $row = array_merge($defaults, array_intersect_key($data, $defaults));
        $wpdb->insert($table, $row);
        $deal_id = (int) $wpdb->insert_id;

        if ($deal_id && !empty($row['stage_id'])) {
            $wpdb->insert(Ofnoacomps_CRM_Database::table('deal_stage_log'), [
                'deal_id'       => $deal_id,
                'from_stage_id' => null,
                'to_stage_id'   => $row['stage_id'],
                'user_id'       => get_current_user_id(),
            ]);
        }

        return $deal_id;
    }

    public static function get(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, s.name AS stage_name, s.color AS stage_color, s.is_won, s.is_lost,
                    p.name AS pipeline_name
             FROM " . Ofnoacomps_CRM_Database::table('deals') . " d
             LEFT JOIN " . Ofnoacomps_CRM_Database::table('stages') . " s ON s.id = d.stage_id
             LEFT JOIN " . Ofnoacomps_CRM_Database::table('pipelines') . " p ON p.id = d.pipeline_id
             WHERE d.id = %d",
            $id
        ));
    }

    public static function update(int $id, array $data): bool {
        global $wpdb;
        $table   = Ofnoacomps_CRM_Database::table('deals');
        $allowed = ['name','stage_id','amount','currency','probability','close_date',
                    'status','lost_reason','notes','owner_id','customer_id'];
        $update  = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return false;

        // Track stage change
        if (isset($update['stage_id'])) {
            $old = self::get($id);
            if ($old && (int)$old->stage_id !== (int)$update['stage_id']) {
                $wpdb->insert(Ofnoacomps_CRM_Database::table('deal_stage_log'), [
                    'deal_id'       => $id,
                    'from_stage_id' => $old->stage_id,
                    'to_stage_id'   => $update['stage_id'],
                    'user_id'       => get_current_user_id(),
                ]);
                // Auto-set probability from new stage
                $stage = Ofnoacomps_CRM_Pipeline::get_stage((int)$update['stage_id']);
                if ($stage && !isset($update['probability'])) {
                    $update['probability'] = $stage->probability;
                }
            }
        }

        // Handle won/lost
        if (isset($update['status'])) {
            if ($update['status'] === 'won' || $update['status'] === 'lost') {
                $update['closed_at'] = current_time('mysql');
            }
        }

        return (bool) $wpdb->update($table, $update, ['id' => $id]);
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete(Ofnoacomps_CRM_Database::table('deals'), ['id' => $id]);
    }

    public static function list(array $args = []): array {
        global $wpdb;
        $d_table = Ofnoacomps_CRM_Database::table('deals');
        $s_table = Ofnoacomps_CRM_Database::table('stages');

        $where  = ['d.1=1'];
        $params = [];

        if (!empty($args['pipeline_id'])) { $where[] = 'd.pipeline_id = %d'; $params[] = (int)$args['pipeline_id']; }
        if (!empty($args['stage_id']))    { $where[] = 'd.stage_id = %d';    $params[] = (int)$args['stage_id']; }
        if (!empty($args['owner_id']))    { $where[] = 'd.owner_id = %d';    $params[] = (int)$args['owner_id']; }
        if (!empty($args['customer_id'])) { $where[] = 'd.customer_id = %d'; $params[] = (int)$args['customer_id']; }
        if (!empty($args['status']))      { $where[] = 'd.status = %s';      $params[] = $args['status']; }
        if (!empty($args['search'])) {
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = 'd.name LIKE %s'; $params[] = $s;
        }
        if (!empty($args['date_from'])) { $where[] = 'd.created_at >= %s'; $params[] = $args['date_from'] . ' 00:00:00'; }
        if (!empty($args['date_to']))   { $where[] = 'd.created_at <= %s'; $params[] = $args['date_to']   . ' 23:59:59'; }

        $order_by  = 'd.created_at';
        $order_dir = 'DESC';
        $limit     = max(1, min(200, (int) ($args['limit'] ?? 100)));
        $offset    = max(0, (int) ($args['offset'] ?? 0));

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT d.*, s.name AS stage_name, s.color AS stage_color, s.is_won, s.is_lost, s.sort_order AS stage_order
                FROM $d_table d
                LEFT JOIN $s_table s ON s.id = d.stage_id
                WHERE $where_sql ORDER BY $order_by $order_dir LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
    }

    /** Get deals grouped by stage for Kanban view. */
    public static function kanban(int $pipeline_id): array {
        global $wpdb;
        $stages  = Ofnoacomps_CRM_Pipeline::get_stages($pipeline_id);
        $deals   = self::list(['pipeline_id' => $pipeline_id, 'status' => 'open', 'limit' => 200]);

        $grouped = [];
        foreach ($stages as $stage) {
            $grouped[$stage->id] = [
                'stage'  => $stage,
                'deals'  => [],
                'total'  => 0,
            ];
        }
        foreach ($deals as $deal) {
            if (isset($grouped[$deal->stage_id])) {
                $grouped[$deal->stage_id]['deals'][] = $deal;
                $grouped[$deal->stage_id]['total'] += (float) $deal->amount;
            }
        }
        return array_values($grouped);
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    private static function get_default_pipeline(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT id FROM " . Ofnoacomps_CRM_Database::table('pipelines') . " WHERE is_default=1 LIMIT 1"
        );
    }

    private static function get_first_stage(int $pipeline_id): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . Ofnoacomps_CRM_Database::table('stages') .
            " WHERE pipeline_id=%d AND is_lost=0 ORDER BY sort_order ASC LIMIT 1",
            $pipeline_id
        ));
    }
}
