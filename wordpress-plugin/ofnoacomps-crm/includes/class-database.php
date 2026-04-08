<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Database {

    const SCHEMA_VERSION = '1.0.0';

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ─── Leads ──────────────────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_leads (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name    VARCHAR(100)  NOT NULL DEFAULT '',
            last_name     VARCHAR(100)  NOT NULL DEFAULT '',
            email         VARCHAR(200)  NOT NULL DEFAULT '',
            phone         VARCHAR(50)   NOT NULL DEFAULT '',
            message       TEXT,
            status        VARCHAR(50)   NOT NULL DEFAULT 'new',
            score         TINYINT UNSIGNED NOT NULL DEFAULT 0,
            owner_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            form_id       VARCHAR(100)  DEFAULT NULL,
            form_name     VARCHAR(200)  DEFAULT NULL,
            page_url      VARCHAR(500)  DEFAULT NULL,
            source        VARCHAR(100)  NOT NULL DEFAULT 'direct',
            medium        VARCHAR(100)  NOT NULL DEFAULT '',
            campaign      VARCHAR(200)  NOT NULL DEFAULT '',
            utm_term      VARCHAR(200)  NOT NULL DEFAULT '',
            utm_content   VARCHAR(200)  NOT NULL DEFAULT '',
            referrer      VARCHAR(500)  DEFAULT NULL,
            landing_page  VARCHAR(500)  DEFAULT NULL,
            device_type   VARCHAR(30)   NOT NULL DEFAULT '',
            ip_address    VARCHAR(45)   NOT NULL DEFAULT '',
            notes         TEXT,
            converted_at  DATETIME      DEFAULT NULL,
            customer_id   BIGINT UNSIGNED DEFAULT NULL,
            created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_email  (email(100)),
            KEY idx_source (source),
            KEY idx_owner  (owner_id),
            KEY idx_created (created_at)
        ) $charset;");

        // ─── Customers ──────────────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_customers (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name    VARCHAR(100)  NOT NULL DEFAULT '',
            last_name     VARCHAR(100)  NOT NULL DEFAULT '',
            email         VARCHAR(200)  NOT NULL DEFAULT '',
            phone         VARCHAR(50)   NOT NULL DEFAULT '',
            company       VARCHAR(200)  NOT NULL DEFAULT '',
            city          VARCHAR(100)  NOT NULL DEFAULT '',
            address       VARCHAR(300)  NOT NULL DEFAULT '',
            owner_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            lead_id       BIGINT UNSIGNED DEFAULT NULL,
            source        VARCHAR(100)  NOT NULL DEFAULT '',
            tags          TEXT,
            notes         TEXT,
            custom_fields LONGTEXT,
            status        VARCHAR(50)   NOT NULL DEFAULT 'active',
            created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email  (email(100)),
            KEY idx_status (status),
            KEY idx_owner  (owner_id),
            KEY idx_created (created_at)
        ) $charset;");

        // ─── Pipelines ──────────────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_pipelines (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(200) NOT NULL,
            is_default TINYINT(1)   NOT NULL DEFAULT 0,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;");

        // ─── Pipeline Stages ────────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_stages (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pipeline_id   BIGINT UNSIGNED NOT NULL,
            name          VARCHAR(200) NOT NULL,
            sort_order    INT          NOT NULL DEFAULT 0,
            probability   TINYINT UNSIGNED NOT NULL DEFAULT 0,
            color         VARCHAR(10)  NOT NULL DEFAULT '#3b82f6',
            is_won        TINYINT(1)   NOT NULL DEFAULT 0,
            is_lost       TINYINT(1)   NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_pipeline (pipeline_id)
        ) $charset;");

        // ─── Deals ──────────────────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_deals (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name         VARCHAR(300)  NOT NULL DEFAULT '',
            pipeline_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
            stage_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id  BIGINT UNSIGNED DEFAULT NULL,
            lead_id      BIGINT UNSIGNED DEFAULT NULL,
            owner_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            amount       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            currency     VARCHAR(5)    NOT NULL DEFAULT 'ILS',
            probability  TINYINT UNSIGNED NOT NULL DEFAULT 0,
            close_date   DATE          DEFAULT NULL,
            status       VARCHAR(20)   NOT NULL DEFAULT 'open',
            lost_reason  VARCHAR(300)  DEFAULT NULL,
            notes        TEXT,
            created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at    DATETIME      DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_stage   (stage_id),
            KEY idx_pipeline (pipeline_id),
            KEY idx_owner   (owner_id),
            KEY idx_status  (status),
            KEY idx_created (created_at)
        ) $charset;");

        // ─── Activities ─────────────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_activities (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type         VARCHAR(30)   NOT NULL DEFAULT 'note',
            subject      VARCHAR(300)  NOT NULL DEFAULT '',
            body         TEXT,
            entity_type  VARCHAR(30)   NOT NULL DEFAULT 'lead',
            entity_id    BIGINT UNSIGNED NOT NULL,
            user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            direction    VARCHAR(10)   NOT NULL DEFAULT 'outbound',
            duration_sec INT UNSIGNED  NOT NULL DEFAULT 0,
            outcome      VARCHAR(100)  NOT NULL DEFAULT '',
            scheduled_at DATETIME      DEFAULT NULL,
            completed_at DATETIME      DEFAULT NULL,
            created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_user   (user_id),
            KEY idx_type   (type),
            KEY idx_created (created_at)
        ) $charset;");

        // ─── Deal Stage History ─────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_deal_stage_log (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deal_id       BIGINT UNSIGNED NOT NULL,
            from_stage_id BIGINT UNSIGNED DEFAULT NULL,
            to_stage_id   BIGINT UNSIGNED NOT NULL,
            user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            changed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deal (deal_id)
        ) $charset;");

        // ─── Lead Status History ────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_lead_status_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id     BIGINT UNSIGNED NOT NULL,
            from_status VARCHAR(50) DEFAULT NULL,
            to_status   VARCHAR(50) NOT NULL,
            user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lead (lead_id)
        ) $charset;");


        // ─── API Keys ────────────────────────────────────────────────────────
        dbDelta("CREATE TABLE {$wpdb->prefix}ofnoacomps_api_keys (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name         VARCHAR(200)  NOT NULL,
            key_prefix   VARCHAR(12)   NOT NULL,
            key_hash     VARCHAR(64)   NOT NULL,
            capabilities TEXT,
            is_active    TINYINT(1)    NOT NULL DEFAULT 1,
            last_used_at DATETIME      DEFAULT NULL,
            created_by   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_hash   (key_hash),
            KEY idx_prefix (key_prefix),
            KEY idx_active (is_active)
        ) $charset;");
        // Seed default pipeline + stages if none exist
        self::seed_defaults();

        update_option('ofnoacomps_crm_schema_version', self::SCHEMA_VERSION);
    }

    public static function deactivate() {
        // Nothing destructive on deactivate — data is preserved.
    }

    public static function uninstall() {
        global $wpdb;
        $tables = [
            'ofnoacomps_leads', 'ofnoacomps_customers', 'ofnoacomps_pipelines',
            'ofnoacomps_stages', 'ofnoacomps_deals', 'ofnoacomps_activities',
            'ofnoacomps_deal_stage_log', 'ofnoacomps_lead_status_log',
            'ofnoacomps_api_keys',
        ];
        foreach ($tables as $t) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$t}");
        }
        delete_option('ofnoacomps_crm_schema_version');
    }

    private static function seed_defaults() {
        global $wpdb;

        $pipelines_table = $wpdb->prefix . 'ofnoacomps_pipelines';
        $stages_table    = $wpdb->prefix . 'ofnoacomps_stages';

        $exists = $wpdb->get_var("SELECT COUNT(*) FROM $pipelines_table");
        if ($exists > 0) return;

        $wpdb->insert($pipelines_table, ['name' => 'מכירות', 'is_default' => 1]);
        $pipeline_id = $wpdb->insert_id;

        $stages = [
            ['name' => 'ליד חדש',           'sort_order' => 1, 'probability' => 10, 'color' => '#6366f1'],
            ['name' => 'יצירת קשר ראשונית', 'sort_order' => 2, 'probability' => 25, 'color' => '#3b82f6'],
            ['name' => 'הצעת מחיר',          'sort_order' => 3, 'probability' => 50, 'color' => '#f59e0b'],
            ['name' => 'משא ומתן',            'sort_order' => 4, 'probability' => 70, 'color' => '#f97316'],
            ['name' => 'סגור - זכייה',       'sort_order' => 5, 'probability' => 100,'color' => '#22c55e', 'is_won' => 1],
            ['name' => 'סגור - הפסד',        'sort_order' => 6, 'probability' => 0,  'color' => '#ef4444', 'is_lost' => 1],
        ];

        foreach ($stages as $s) {
            $wpdb->insert($stages_table, array_merge(
                ['pipeline_id' => $pipeline_id, 'is_won' => 0, 'is_lost' => 0],
                $s
            ));
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'ofnoacomps_' . $name;
    }
}
