<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Activity {

    public static function create(array $data): int {
        global $wpdb;
        $defaults = [
            'type'        => 'note',
            'subject'     => '',
            'body'        => '',
            'entity_type' => 'lead',
            'entity_id'   => 0,
            'user_id'     => get_current_user_id() ?: 0,
            'direction'   => 'outbound',
            'duration_sec'=> 0,
            'outcome'     => '',
            'scheduled_at'=> null,
            'completed_at'=> null,
        ];

        $row = array_merge($defaults, array_intersect_key($data, $defaults));
        $wpdb->insert(Ofnoacomps_CRM_Database::table('activities'), $row);
        return (int) $wpdb->insert_id;
    }

    public static function get(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Ofnoacomps_CRM_Database::table('activities') . " WHERE id=%d", $id
        ));
    }

    public static function update(int $id, array $data): bool {
        global $wpdb;
        $allowed = ['type','subject','body','direction','duration_sec','outcome','scheduled_at','completed_at'];
        $update  = array_intersect_key($data, array_flip($allowed));
        return !empty($update) && (bool) $wpdb->update(Ofnoacomps_CRM_Database::table('activities'), $update, ['id' => $id]);
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete(Ofnoacomps_CRM_Database::table('activities'), ['id' => $id]);
    }

    public static function list(array $args = []): array {
        global $wpdb;
        $table  = Ofnoacomps_CRM_Database::table('activities');
        $where  = ['1=1'];
        $params = [];

        if (!empty($args['entity_type'])) { $where[] = 'entity_type = %s'; $params[] = $args['entity_type']; }
        if (!empty($args['entity_id']))   { $where[] = 'entity_id = %d';   $params[] = (int)$args['entity_id']; }
        if (!empty($args['type']))        { $where[] = 'type = %s';        $params[] = $args['type']; }
        if (!empty($args['user_id']))     { $where[] = 'user_id = %d';     $params[] = (int)$args['user_id']; }

        $limit  = max(1, min(200, (int)($args['limit'] ?? 50)));
        $offset = max(0, (int)($args['offset'] ?? 0));
        $where_sql = implode(' AND ', $where);
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit; $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
    }

    public static function get_types(): array {
        return [
            'note'    => 'הערה',
            'call'    => 'שיחה',
            'email'   => 'אימייל',
            'meeting' => 'פגישה',
            'task'    => 'משימה',
            'sms'     => 'SMS',
            'whatsapp'=> 'וואטסאפ',
        ];
    }
}
