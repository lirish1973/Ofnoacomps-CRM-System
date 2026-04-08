<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Customer {

    public static function create(array $data): int {
        global $wpdb;
        $table = Ofnoacomps_CRM_Database::table('customers');

        $defaults = [
            'first_name'    => '',
            'last_name'     => '',
            'email'         => '',
            'phone'         => '',
            'company'       => '',
            'city'          => '',
            'address'       => '',
            'owner_id'      => 0,
            'lead_id'       => null,
            'source'        => '',
            'tags'          => '',
            'notes'         => '',
            'custom_fields' => '{}',
            'status'        => 'active',
        ];

        $row = array_merge($defaults, array_intersect_key($data, $defaults));
        if (is_array($row['tags'])) $row['tags'] = implode(',', $row['tags']);
        if (is_array($row['custom_fields'])) $row['custom_fields'] = json_encode($row['custom_fields']);

        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    public static function get(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Ofnoacomps_CRM_Database::table('customers') . " WHERE id = %d",
            $id
        ));
    }

    public static function update(int $id, array $data): bool {
        global $wpdb;
        $allowed = ['first_name','last_name','email','phone','company','city',
                    'address','owner_id','tags','notes','custom_fields','status'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (is_array($update['tags'] ?? null)) $update['tags'] = implode(',', $update['tags']);
        if (is_array($update['custom_fields'] ?? null)) $update['custom_fields'] = json_encode($update['custom_fields']);
        return !empty($update) && (bool) $wpdb->update(Ofnoacomps_CRM_Database::table('customers'), $update, ['id' => $id]);
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete(Ofnoacomps_CRM_Database::table('customers'), ['id' => $id]);
    }

    public static function list(array $args = []): array {
        global $wpdb;
        $table  = Ofnoacomps_CRM_Database::table('customers');
        $where  = ['1=1'];
        $params = [];

        if (!empty($args['status'])) { $where[] = 'status = %s'; $params[] = $args['status']; }
        if (!empty($args['owner_id'])) { $where[] = 'owner_id = %d'; $params[] = (int)$args['owner_id']; }
        if (!empty($args['search'])) {
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR company LIKE %s)';
            $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if (!empty($args['date_from'])) { $where[] = 'created_at >= %s'; $params[] = $args['date_from'] . ' 00:00:00'; }
        if (!empty($args['date_to']))   { $where[] = 'created_at <= %s'; $params[] = $args['date_to']   . ' 23:59:59'; }

        $order_by  = in_array($args['order_by'] ?? '', ['id','email','company','created_at']) ? $args['order_by'] : 'created_at';
        $order_dir = ($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit     = max(1, min(200, (int) ($args['limit'] ?? 50)));
        $offset    = max(0, (int) ($args['offset'] ?? 0));

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $order_by $order_dir LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
    }

    public static function count(array $args = []): int {
        global $wpdb;
        $table  = Ofnoacomps_CRM_Database::table('customers');
        $where  = ['1=1'];
        $params = [];
        if (!empty($args['status'])) { $where[] = 'status = %s'; $params[] = $args['status']; }
        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        return (int) (empty($params) ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    public static function get_deals(int $customer_id): array {
        return Ofnoacomps_CRM_Deal::list(['customer_id' => $customer_id]);
    }

    public static function get_activities(int $customer_id): array {
        return Ofnoacomps_CRM_Activity::list(['entity_type' => 'customer', 'entity_id' => $customer_id]);
    }
}
