<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Lead {

    public static function create(array $data): int {
        global $wpdb;
        $table = Ofnoacomps_CRM_Database::table('leads');

        $defaults = [
            'first_name'   => '',
            'last_name'    => '',
            'email'        => '',
            'phone'        => '',
            'message'      => '',
            'status'       => 'new',
            'score'        => 0,
            'owner_id'     => 0,
            'source'       => 'direct',
            'medium'       => '',
            'campaign'     => '',
            'utm_term'     => '',
            'utm_content'  => '',
            'referrer'     => '',
            'landing_page' => '',
            'device_type'  => '',
            'ip_address'   => '',
            'form_id'      => null,
            'form_name'    => null,
            'page_url'     => null,
        ];

        $row = array_merge($defaults, array_intersect_key($data, $defaults));

        // Auto-assign score based on source
        if ($row['score'] === 0) {
            $row['score'] = self::compute_score($row);
        }

        // Auto-assign to admin if no owner
        if (empty($row['owner_id'])) {
            $row['owner_id'] = self::get_default_owner();
        }

        $wpdb->insert($table, $row);
        $lead_id = (int) $wpdb->insert_id;

        if ($lead_id) {
            // Log initial status
            $wpdb->insert(Ofnoacomps_CRM_Database::table('lead_status_log'), [
                'lead_id'     => $lead_id,
                'from_status' => null,
                'to_status'   => $row['status'],
                'user_id'     => 0,
            ]);

            // Trigger notification
            self::notify_owner($lead_id, $row);
        }

        return $lead_id;
    }

    public static function get(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Ofnoacomps_CRM_Database::table('leads') . " WHERE id = %d",
            $id
        ));
    }

    public static function update(int $id, array $data): bool {
        global $wpdb;
        $table = Ofnoacomps_CRM_Database::table('leads');

        $allowed = ['first_name','last_name','email','phone','status','score',
                    'owner_id','notes','message','source','medium','campaign',
                    'utm_term','utm_content','customer_id','converted_at'];

        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return false;

        // Track status change
        if (isset($update['status'])) {
            $old = self::get($id);
            if ($old && $old->status !== $update['status']) {
                $wpdb->insert(Ofnoacomps_CRM_Database::table('lead_status_log'), [
                    'lead_id'     => $id,
                    'from_status' => $old->status,
                    'to_status'   => $update['status'],
                    'user_id'     => get_current_user_id(),
                ]);
            }
        }

        return (bool) $wpdb->update($table, $update, ['id' => $id]);
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete(Ofnoacomps_CRM_Database::table('leads'), ['id' => $id]);
    }

    public static function list(array $args = []): array {
        global $wpdb;
        $table = Ofnoacomps_CRM_Database::table('leads');

        $where  = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['source'])) {
            $where[]  = 'source = %s';
            $params[] = $args['source'];
        }
        if (!empty($args['owner_id'])) {
            $where[]  = 'owner_id = %d';
            $params[] = (int) $args['owner_id'];
        }
        if (!empty($args['search'])) {
            $s        = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[]  = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if (!empty($args['date_from'])) {
            $where[]  = 'created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if (!empty($args['date_to'])) {
            $where[]  = 'created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $order_by  = in_array($args['order_by'] ?? '', ['id','email','created_at','score','status']) ? $args['order_by'] : 'created_at';
        $order_dir = ($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit     = max(1, min(200, (int) ($args['limit'] ?? 50)));
        $offset    = max(0, (int) ($args['offset'] ?? 0));

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $order_by $order_dir LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = empty($params)
            ? $wpdb->get_results($sql)
            : $wpdb->get_results($wpdb->prepare($sql, $params));

        return $rows ?: [];
    }

    public static function count(array $args = []): int {
        global $wpdb;
        $table  = Ofnoacomps_CRM_Database::table('leads');
        $where  = ['1=1'];
        $params = [];

        if (!empty($args['status'])) { $where[] = 'status = %s'; $params[] = $args['status']; }
        if (!empty($args['source'])) { $where[] = 'source = %s'; $params[] = $args['source']; }
        if (!empty($args['date_from'])) { $where[] = 'created_at >= %s'; $params[] = $args['date_from'] . ' 00:00:00'; }
        if (!empty($args['date_to']))   { $where[] = 'created_at <= %s'; $params[] = $args['date_to']   . ' 23:59:59'; }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        return (int) (empty($params) ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    /** Convert a lead to customer + optionally create a deal. */
    public static function convert(int $lead_id, array $deal_data = []): array {
        $lead = self::get($lead_id);
        if (!$lead) return ['error' => 'Lead not found'];

        $customer_id = Ofnoacomps_CRM_Customer::create([
            'first_name' => $lead->first_name,
            'last_name'  => $lead->last_name,
            'email'      => $lead->email,
            'phone'      => $lead->phone,
            'source'     => $lead->source,
            'lead_id'    => $lead_id,
            'owner_id'   => $lead->owner_id,
        ]);

        $deal_id = null;
        if (!empty($deal_data)) {
            $deal_id = Ofnoacomps_CRM_Deal::create(array_merge($deal_data, [
                'customer_id' => $customer_id,
                'lead_id'     => $lead_id,
            ]));
        }

        self::update($lead_id, [
            'status'       => 'converted',
            'customer_id'  => $customer_id,
            'converted_at' => current_time('mysql'),
        ]);

        return ['customer_id' => $customer_id, 'deal_id' => $deal_id];
    }

    /** Capture hook handler. */
    public static function capture(array $data): void {
        self::create($data);
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    private static function compute_score(array $row): int {
        $score = 0;
        // Source score
        $source_scores = ['google_ads' => 30, 'facebook_ads' => 25, 'google_organic' => 20, 'direct' => 10, 'referral' => 15];
        $score += $source_scores[$row['source']] ?? 5;
        // Has phone
        if (!empty($row['phone'])) $score += 20;
        // Has email
        if (!empty($row['email'])) $score += 10;
        return min(100, $score);
    }

    private static function get_default_owner(): int {
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        return !empty($admins) ? (int) $admins[0] : 0;
    }

    private static function notify_owner(int $lead_id, array $row): void {
        $owner_id = (int) $row['owner_id'];
        if (!$owner_id) return;

        $owner = get_user_by('id', $owner_id);
        if (!$owner) return;

        $name    = trim($row['first_name'] . ' ' . $row['last_name']);
        $subject = "ליד חדש התקבל: $name";
        $link    = admin_url("admin.php?page=ofnoacomps-crm-leads&action=view&id=$lead_id");
        $message = "שלום {$owner->display_name},\n\nהתקבל ליד חדש מ-{$row['source']}.\n\nשם: $name\nאימייל: {$row['email']}\nטלפון: {$row['phone']}\n\nלצפייה בליד:\n$link";

        wp_mail($owner->user_email, $subject, $message);
    }

    public static function get_statuses(): array {
        return [
            'new'       => 'חדש',
            'contacted' => 'בטיפול',
            'qualified' => 'מוסמך',
            'converted' => 'הומר ללקוח',
            'lost'      => 'אבוד',
        ];
    }

    public static function get_sources(): array {
        return [
            'direct'         => 'ישיר',
            'google_organic' => 'גוגל אורגני',
            'google_ads'     => 'גוגל ממומן',
            'facebook_ads'   => 'פייסבוק ממומן',
            'facebook_organic'=> 'פייסבוק אורגני',
            'instagram'      => 'אינסטגרם',
            'email'          => 'אימייל',
            'referral'       => 'הפניה',
            'whatsapp'       => 'וואטסאפ',
            'other'          => 'אחר',
        ];
    }
}
