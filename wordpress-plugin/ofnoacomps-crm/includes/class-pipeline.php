<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Pipeline {

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . Ofnoacomps_CRM_Database::table('pipelines') . " ORDER BY id ASC") ?: [];
    }

    public static function get(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Ofnoacomps_CRM_Database::table('pipelines') . " WHERE id=%d", $id
        ));
    }

    public static function get_stages(int $pipeline_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . Ofnoacomps_CRM_Database::table('stages') . " WHERE pipeline_id=%d ORDER BY sort_order ASC",
            $pipeline_id
        )) ?: [];
    }

    public static function get_stage(int $stage_id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Ofnoacomps_CRM_Database::table('stages') . " WHERE id=%d", $stage_id
        ));
    }

    public static function create(string $name, bool $is_default = false): int {
        global $wpdb;
        $wpdb->insert(Ofnoacomps_CRM_Database::table('pipelines'), ['name' => $name, 'is_default' => (int)$is_default]);
        return (int) $wpdb->insert_id;
    }

    public static function add_stage(int $pipeline_id, array $data): int {
        global $wpdb;
        $wpdb->insert(Ofnoacomps_CRM_Database::table('stages'), [
            'pipeline_id' => $pipeline_id,
            'name'        => $data['name'] ?? 'שלב',
            'sort_order'  => $data['sort_order'] ?? 99,
            'probability' => $data['probability'] ?? 50,
            'color'       => $data['color'] ?? '#3b82f6',
            'is_won'      => (int) ($data['is_won'] ?? 0),
            'is_lost'     => (int) ($data['is_lost'] ?? 0),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function update_stage(int $stage_id, array $data): bool {
        global $wpdb;
        $allowed = ['name','sort_order','probability','color','is_won','is_lost'];
        $update  = array_intersect_key($data, array_flip($allowed));
        return !empty($update) && (bool) $wpdb->update(Ofnoacomps_CRM_Database::table('stages'), $update, ['id' => $stage_id]);
    }

    public static function delete_stage(int $stage_id): bool {
        global $wpdb;
        return (bool) $wpdb->delete(Ofnoacomps_CRM_Database::table('stages'), ['id' => $stage_id]);
    }

    /** Get full pipeline with stages for API response. */
    public static function get_with_stages(int $pipeline_id): ?array {
        $pipeline = self::get($pipeline_id);
        if (!$pipeline) return null;
        return ['pipeline' => $pipeline, 'stages' => self::get_stages($pipeline_id)];
    }
}
