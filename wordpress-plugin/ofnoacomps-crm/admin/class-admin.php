<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Admin {

    public function __construct() {
        add_action('admin_menu',            [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init',            [$this, 'handle_actions']);
    }

    public function register_menu(): void {
        add_menu_page(
            'Ofnoacomps CRM',
            'Ofnoacomps CRM',
            'edit_posts',
            'ofnoacomps-crm',
            [$this, 'page_dashboard'],
            'dashicons-groups',
            26
        );

        add_submenu_page('ofnoacomps-crm', 'דשבורד',      'דשבורד',      'edit_posts', 'ofnoacomps-crm',           [$this, 'page_dashboard']);
        add_submenu_page('ofnoacomps-crm', 'לידים',        'לידים',        'edit_posts', 'ofnoacomps-crm-leads',     [$this, 'page_leads']);
        add_submenu_page('ofnoacomps-crm', 'לקוחות',      'לקוחות',      'edit_posts', 'ofnoacomps-crm-customers', [$this, 'page_customers']);
        add_submenu_page('ofnoacomps-crm', 'Pipeline',     'Pipeline',     'edit_posts', 'ofnoacomps-crm-pipeline',  [$this, 'page_pipeline']);
        add_submenu_page('ofnoacomps-crm', 'דוחות',        'דוחות',        'edit_posts', 'ofnoacomps-crm-reports',   [$this, 'page_reports']);
        add_submenu_page('ofnoacomps-crm', 'הגדרות',      'הגדרות',      'manage_options', 'ofnoacomps-crm-settings', [$this, 'page_settings']);
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'ofnoacomps-crm') === false) return;

        wp_enqueue_style(
            'ofnoacomps-crm-admin',
            OFNOACOMPS_CRM_PLUGIN_URL . 'admin/assets/admin.css',
            [],
            OFNOACOMPS_CRM_VERSION
        );

        // Chart.js from CDN
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], null, true);

        wp_enqueue_script(
            'ofnoacomps-crm-admin',
            OFNOACOMPS_CRM_PLUGIN_URL . 'admin/assets/admin.js',
            ['jquery', 'chartjs'],
            OFNOACOMPS_CRM_VERSION,
            true
        );

        wp_localize_script('ofnoacomps-crm-admin', 'ofnoacompsCRMAdmin', [
            'apiBase'  => rest_url('ofnoacomps-crm/v1'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'currency' => get_option('ofnoacomps_crm_currency', '₪'),
            'ajaxUrl'  => admin_url('admin-ajax.php'),
        ]);
    }

    public function handle_actions(): void {
        // Bulk delete leads
        if (isset($_POST['ofnoacomps_bulk_action']) && $_POST['ofnoacomps_bulk_action'] === 'delete_leads') {
            check_admin_referer('ofnoacomps_bulk_action');
            $ids = array_map('intval', (array) ($_POST['lead_ids'] ?? []));
            foreach ($ids as $id) Ofnoacomps_CRM_Lead::delete($id);
            wp_redirect(admin_url('admin.php?page=ofnoacomps-crm-leads&deleted=' . count($ids)));
            exit;
        }
    }

    // ── Pages ──────────────────────────────────────────────────────────────────

    public function page_dashboard(): void {
        $summary = Ofnoacomps_CRM_Reports::dashboard_summary();
        require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function page_leads(): void {
        $action = sanitize_text_field($_GET['action'] ?? 'list');
        $id     = (int) ($_GET['id'] ?? 0);

        if ($action === 'view' && $id) {
            $lead       = Ofnoacomps_CRM_Lead::get($id);
            $activities = Ofnoacomps_CRM_Activity::list(['entity_type' => 'lead', 'entity_id' => $id]);
            $statuses   = Ofnoacomps_CRM_Lead::get_statuses();
            require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/lead-detail.php';
            return;
        }

        $search   = sanitize_text_field($_GET['s'] ?? '');
        $status   = sanitize_text_field($_GET['status'] ?? '');
        $source   = sanitize_text_field($_GET['source'] ?? '');
        $paged    = max(1, (int)($_GET['paged'] ?? 1));
        $per_page = 30;
        $offset   = ($paged - 1) * $per_page;

        $leads   = Ofnoacomps_CRM_Lead::list(['search' => $search, 'status' => $status, 'source' => $source, 'limit' => $per_page, 'offset' => $offset]);
        $total   = Ofnoacomps_CRM_Lead::count(['search' => $search, 'status' => $status, 'source' => $source]);
        $pages   = ceil($total / $per_page);
        $statuses= Ofnoacomps_CRM_Lead::get_statuses();
        $sources = Ofnoacomps_CRM_Lead::get_sources();
        $users   = get_users(['role__in' => ['administrator','editor'], 'fields' => ['ID','display_name']]);

        require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/leads.php';
    }

    public function page_customers(): void {
        $action = sanitize_text_field($_GET['action'] ?? 'list');
        $id     = (int) ($_GET['id'] ?? 0);

        if ($action === 'view' && $id) {
            $customer   = Ofnoacomps_CRM_Customer::get($id);
            $activities = Ofnoacomps_CRM_Activity::list(['entity_type' => 'customer', 'entity_id' => $id]);
            $deals      = Ofnoacomps_CRM_Deal::list(['customer_id' => $id]);
            require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/customer-detail.php';
            return;
        }

        $search = sanitize_text_field($_GET['s'] ?? '');
        $paged  = max(1, (int)($_GET['paged'] ?? 1));
        $per_page = 30;
        $offset = ($paged - 1) * $per_page;

        $customers = Ofnoacomps_CRM_Customer::list(['search' => $search, 'limit' => $per_page, 'offset' => $offset]);
        $total     = Ofnoacomps_CRM_Customer::count();
        $pages     = ceil($total / $per_page);

        require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/customers.php';
    }

    public function page_pipeline(): void {
        $pipelines = Ofnoacomps_CRM_Pipeline::get_all();
        $pipeline_id = (int) ($_GET['pipeline'] ?? ($pipelines[0]->id ?? 0));
        $kanban    = $pipeline_id ? Ofnoacomps_CRM_Deal::kanban($pipeline_id) : [];
        $users     = get_users(['role__in' => ['administrator','editor'], 'fields' => ['ID','display_name']]);
        require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/pipeline.php';
    }

    public function page_reports(): void {
        $from = sanitize_text_field($_GET['from'] ?? date('Y-m-01'));
        $to   = sanitize_text_field($_GET['to']   ?? date('Y-m-d'));

        $summary      = Ofnoacomps_CRM_Reports::dashboard_summary($from, $to);
        $by_source    = Ofnoacomps_CRM_Reports::leads_by_source($from, $to);
        $by_status    = Ofnoacomps_CRM_Reports::leads_by_status();
        $funnel       = Ofnoacomps_CRM_Reports::pipeline_funnel();
        $leaderboard  = Ofnoacomps_CRM_Reports::rep_leaderboard($from, $to);
        $leads_time   = Ofnoacomps_CRM_Reports::leads_over_time($from, $to);
        $revenue_time = Ofnoacomps_CRM_Reports::revenue_over_time($from, $to);

        require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/reports.php';
    }

    public function page_settings(): void {
        if (isset($_POST['ofnoacomps_settings_nonce']) && wp_verify_nonce($_POST['ofnoacomps_settings_nonce'], 'ofnoacomps_settings')) {
            update_option('ofnoacomps_crm_currency', sanitize_text_field($_POST['currency'] ?? '₪'));
            update_option('ofnoacomps_crm_notify_email', sanitize_email($_POST['notify_email'] ?? ''));
            echo '<div class="notice notice-success"><p>הגדרות נשמרו.</p></div>';
        }
        $currency     = get_option('ofnoacomps_crm_currency', '₪');
        $notify_email = get_option('ofnoacomps_crm_notify_email', get_option('admin_email'));
        require OFNOACOMPS_CRM_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
