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

    /**
     * שלח מייל התראה על ליד חדש.
     * שולח תמיד ל: ofnoacomps@gmail.com + לאדמין האתר + לבעלים (אם שונה).
     */
    private static function notify_owner(int $lead_id, array $row): void {
        $name    = trim($row['first_name'] . ' ' . $row['last_name']) ?: 'ליד חדש';
        $link    = admin_url("admin.php?page=ofnoacomps-crm-leads&action=view&id=$lead_id");
        $site    = get_bloginfo('name') ?: get_bloginfo('url');
        $time    = wp_date('d/m/Y H:i', time());

        // נמענים: תמיד שלח ל-ofnoacomps@gmail.com + אדמין האתר + בעלים
        $recipients = ['ofnoacomps@gmail.com'];

        $site_admin = get_option('admin_email');
        if ($site_admin && !in_array($site_admin, $recipients, true)) {
            $recipients[] = $site_admin;
        }

        if (!empty($row['owner_id'])) {
            $owner = get_user_by('id', (int) $row['owner_id']);
            if ($owner && !in_array($owner->user_email, $recipients, true)) {
                $recipients[] = $owner->user_email;
            }
        }

        $subject = "ליד חדש מאתר $site: $name";

        // HTML email
        $rows_html = '';
        $fields = [
            'שם מלא'    => $name,
            'אימייל'    => $row['email']   ?: '-',
            'טלפון'     => $row['phone']   ?: '-',
            'הודעה'     => $row['message'] ?: '-',
            'מקור'      => $row['source']  ?: 'direct',
            'דף נחיתה' => $row['landing_page'] ?: '-',
            'ציון'      => $row['score'],
            'נקלט בתאריך' => $time,
        ];
        foreach ($fields as $label => $value) {
            $rows_html .= "<tr>
                <td style='padding:10px 14px;border-bottom:1px solid #eee;background:#f9f9f9;font-weight:bold;color:#555;width:140px;'>$label</td>
                <td style='padding:10px 14px;border-bottom:1px solid #eee;color:#333;'>$value</td>
            </tr>";
        }

        $html = "<!DOCTYPE html><html dir='rtl' lang='he'>
<head><meta charset='UTF-8'></head>
<body style='font-family:Arial,sans-serif;background:#f0f0f0;margin:0;padding:20px;'>
  <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);'>
    <div style='background:#2563eb;padding:20px 24px;'>
      <h2 style='color:#fff;margin:0;font-size:20px;'>&#128640; ליד חדש התקבל!</h2>
      <p style='color:#bfdbfe;margin:6px 0 0;font-size:13px;'>$site</p>
    </div>
    <table style='width:100%;border-collapse:collapse;'>$rows_html</table>
    <div style='padding:20px 24px;'>
      <a href='$link' style='display:inline-block;background:#2563eb;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;'>
        &#128065; צפה בליד במערכת
      </a>
    </div>
    <div style='padding:12px 24px;background:#f9f9f9;border-top:1px solid #eee;font-size:11px;color:#888;'>
      הודעה זו נשלחה אוטומטית על ידי מערכת Ofnoacomps CRM
    </div>
  </div>
</body></html>";

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $html, $headers);
        }
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
            'direct'          => 'ישיר',
            'google_organic'  => 'גוגל אורגני',
            'google_ads'      => 'גוגל ממומן',
            'facebook_ads'    => 'פייסבוק ממומן',
            'facebook_organic'=> 'פייסבוק אורגני',
            'instagram'       => 'אינסטגרם',
            'email'           => 'אימייל',
            'referral'        => 'הפניה',
            'whatsapp'        => 'וואטסאפ',
            'other'           => 'אחר',
        ];
    }
}
