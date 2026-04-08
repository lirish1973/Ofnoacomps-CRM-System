<?php
defined('ABSPATH') || exit;

class Ofnoacomps_CRM_REST_API {

    const NS = 'ofnoacomps-crm/v1';

    public function register_routes(): void {
        // ── Leads ────────────────────────────────────────────────────────────
        register_rest_route(self::NS, '/leads', [
            ['methods' => 'GET',  'callback' => [$this, 'list_leads'],   'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'create_lead'],  'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/leads/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [$this, 'get_lead'],    'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'PATCH',  'callback' => [$this, 'update_lead'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_lead'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/leads/(?P<id>\d+)/convert', [
            ['methods' => 'POST', 'callback' => [$this, 'convert_lead'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/leads/(?P<id>\d+)/activities', [
            ['methods' => 'GET',  'callback' => [$this, 'lead_activities'],  'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'add_lead_activity'],'permission_callback' => [$this, 'can_manage']],
        ]);

        // ── Customers ────────────────────────────────────────────────────────
        register_rest_route(self::NS, '/customers', [
            ['methods' => 'GET',  'callback' => [$this, 'list_customers'],  'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'create_customer'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/customers/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [$this, 'get_customer'],    'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'PATCH',  'callback' => [$this, 'update_customer'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_customer'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/customers/(?P<id>\d+)/activities', [
            ['methods' => 'GET',  'callback' => [$this, 'customer_activities'],  'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'add_customer_activity'],'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/customers/(?P<id>\d+)/deals', [
            ['methods' => 'GET', 'callback' => [$this, 'customer_deals'], 'permission_callback' => [$this, 'can_manage']],
        ]);

        // ── Deals ────────────────────────────────────────────────────────────
        register_rest_route(self::NS, '/deals', [
            ['methods' => 'GET',  'callback' => [$this, 'list_deals'],   'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'POST', 'callback' => [$this, 'create_deal'],  'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/deals/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [$this, 'get_deal'],    'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'PATCH',  'callback' => [$this, 'update_deal'], 'permission_callback' => [$this, 'can_manage']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_deal'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/deals/kanban', [
            ['methods' => 'GET', 'callback' => [$this, 'deals_kanban'], 'permission_callback' => [$this, 'can_manage']],
        ]);

        // ── Pipelines ────────────────────────────────────────────────────────
        register_rest_route(self::NS, '/pipelines', [
            ['methods' => 'GET', 'callback' => [$this, 'list_pipelines'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/pipelines/(?P<id>\d+)/stages', [
            ['methods' => 'GET', 'callback' => [$this, 'get_stages'], 'permission_callback' => [$this, 'can_manage']],
        ]);

        // ── Activities ───────────────────────────────────────────────────────
        register_rest_route(self::NS, '/activities', [
            ['methods' => 'POST', 'callback' => [$this, 'create_activity'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/activities/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_activity'], 'permission_callback' => [$this, 'can_manage']],
        ]);

        // ── Reports ──────────────────────────────────────────────────────────
        register_rest_route(self::NS, '/reports/summary', [
            ['methods' => 'GET', 'callback' => [$this, 'report_summary'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/reports/leads-over-time', [
            ['methods' => 'GET', 'callback' => [$this, 'report_leads_over_time'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/reports/leads-by-source', [
            ['methods' => 'GET', 'callback' => [$this, 'report_leads_by_source'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/reports/pipeline-funnel', [
            ['methods' => 'GET', 'callback' => [$this, 'report_pipeline_funnel'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/reports/revenue', [
            ['methods' => 'GET', 'callback' => [$this, 'report_revenue'], 'permission_callback' => [$this, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/reports/leaderboard', [
            ['methods' => 'GET', 'callback' => [$this, 'report_leaderboard'], 'permission_callback' => [$this, 'can_manage']],
        ]);


        // ── API Keys (admin only) ─────────────────────────────────────────────
        register_rest_route(self::NS, '/api-keys', [
            ['methods' => 'GET',  'callback' => [$this, 'list_api_keys'],   'permission_callback' => [$this, 'can_admin']],
            ['methods' => 'POST', 'callback' => [$this, 'create_api_key'],  'permission_callback' => [$this, 'can_admin']],
        ]);
        register_rest_route(self::NS, '/api-keys/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_api_key'], 'permission_callback' => [$this, 'can_admin']],
        ]);
        register_rest_route(self::NS, '/api-keys/(?P<id>\d+)/revoke', [
            ['methods' => 'POST', 'callback' => [$this, 'revoke_api_key'],  'permission_callback' => [$this, 'can_admin']],
        ]);
        // ── Public lead capture (no auth) ────────────────────────────────────
        register_rest_route(self::NS, '/capture', [
            ['methods' => 'POST', 'callback' => [$this, 'public_capture'], 'permission_callback' => '__return_true'],
        ]);

        // AJAX fallback for WP ajax
        add_action('wp_ajax_nopriv_ofnoacomps_crm_submit_lead', [$this, 'ajax_submit_lead']);
        add_action('wp_ajax_ofnoacomps_crm_submit_lead',        [$this, 'ajax_submit_lead']);
    }

    // ── Permission ────────────────────────────────────────────────────────────

    public function can_manage($req) {
        // Accept logged-in WP user
        if (current_user_can('edit_posts')) return true;
        // Accept valid API key
        $key = Ofnoacomps_CRM_API_Keys::get_key_from_request();
        return $key !== false;
    }

    public function can_admin($req) {
        return current_user_can('manage_options');
    }

    // ── Lead handlers ─────────────────────────────────────────────────────────

    public function list_leads(WP_REST_Request $req): WP_REST_Response {
        $args   = $this->pagination_args($req);
        $leads  = Ofnoacomps_CRM_Lead::list($args);
        $total  = Ofnoacomps_CRM_Lead::count($args);
        return $this->paginated($leads, $total, $args);
    }

    public function create_lead(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        $id   = Ofnoacomps_CRM_Lead::create($this->sanitize($data, ['first_name','last_name','email','phone','message','status','source','medium','campaign','utm_term','utm_content','owner_id']));
        return $id ? $this->success(Ofnoacomps_CRM_Lead::get($id), 201) : $this->error('Could not create lead', 500);
    }

    public function get_lead(WP_REST_Request $req): WP_REST_Response {
        $lead = Ofnoacomps_CRM_Lead::get((int)$req['id']);
        return $lead ? $this->success($lead) : $this->error('Not found', 404);
    }

    public function update_lead(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        Ofnoacomps_CRM_Lead::update((int)$req['id'], $data);
        return $this->success(Ofnoacomps_CRM_Lead::get((int)$req['id']));
    }

    public function delete_lead(WP_REST_Request $req): WP_REST_Response {
        return Ofnoacomps_CRM_Lead::delete((int)$req['id']) ? $this->success(['deleted' => true]) : $this->error('Not found', 404);
    }

    public function convert_lead(WP_REST_Request $req): WP_REST_Response {
        $data   = $req->get_json_params() ?: [];
        $result = Ofnoacomps_CRM_Lead::convert((int)$req['id'], $data['deal'] ?? []);
        return isset($result['error']) ? $this->error($result['error'], 404) : $this->success($result);
    }

    public function lead_activities(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Activity::list(['entity_type' => 'lead', 'entity_id' => (int)$req['id']]));
    }

    public function add_lead_activity(WP_REST_Request $req): WP_REST_Response {
        $data     = $req->get_json_params() ?: $req->get_body_params();
        $data['entity_type'] = 'lead';
        $data['entity_id']   = (int)$req['id'];
        $id = Ofnoacomps_CRM_Activity::create($data);
        return $this->success(Ofnoacomps_CRM_Activity::get($id), 201);
    }

    // ── Customer handlers ─────────────────────────────────────────────────────

    public function list_customers(WP_REST_Request $req): WP_REST_Response {
        $args      = $this->pagination_args($req);
        $customers = Ofnoacomps_CRM_Customer::list($args);
        $total     = Ofnoacomps_CRM_Customer::count($args);
        return $this->paginated($customers, $total, $args);
    }

    public function create_customer(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        $id   = Ofnoacomps_CRM_Customer::create($data);
        return $id ? $this->success(Ofnoacomps_CRM_Customer::get($id), 201) : $this->error('Could not create', 500);
    }

    public function get_customer(WP_REST_Request $req): WP_REST_Response {
        $c = Ofnoacomps_CRM_Customer::get((int)$req['id']);
        return $c ? $this->success($c) : $this->error('Not found', 404);
    }

    public function update_customer(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        Ofnoacomps_CRM_Customer::update((int)$req['id'], $data);
        return $this->success(Ofnoacomps_CRM_Customer::get((int)$req['id']));
    }

    public function delete_customer(WP_REST_Request $req): WP_REST_Response {
        return Ofnoacomps_CRM_Customer::delete((int)$req['id']) ? $this->success(['deleted' => true]) : $this->error('Not found', 404);
    }

    public function customer_activities(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Activity::list(['entity_type' => 'customer', 'entity_id' => (int)$req['id']]));
    }

    public function add_customer_activity(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        $data['entity_type'] = 'customer'; $data['entity_id'] = (int)$req['id'];
        return $this->success(Ofnoacomps_CRM_Activity::get(Ofnoacomps_CRM_Activity::create($data)), 201);
    }

    public function customer_deals(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Deal::list(['customer_id' => (int)$req['id']]));
    }

    // ── Deal handlers ─────────────────────────────────────────────────────────

    public function list_deals(WP_REST_Request $req): WP_REST_Response {
        $args  = $this->pagination_args($req);
        $deals = Ofnoacomps_CRM_Deal::list($args);
        return $this->success($deals);
    }

    public function create_deal(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        $id   = Ofnoacomps_CRM_Deal::create($data);
        return $id ? $this->success(Ofnoacomps_CRM_Deal::get($id), 201) : $this->error('Could not create', 500);
    }

    public function get_deal(WP_REST_Request $req): WP_REST_Response {
        $d = Ofnoacomps_CRM_Deal::get((int)$req['id']);
        return $d ? $this->success($d) : $this->error('Not found', 404);
    }

    public function update_deal(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        Ofnoacomps_CRM_Deal::update((int)$req['id'], $data);
        return $this->success(Ofnoacomps_CRM_Deal::get((int)$req['id']));
    }

    public function delete_deal(WP_REST_Request $req): WP_REST_Response {
        return Ofnoacomps_CRM_Deal::delete((int)$req['id']) ? $this->success(['deleted' => true]) : $this->error('Not found', 404);
    }

    public function deals_kanban(WP_REST_Request $req): WP_REST_Response {
        $pipeline_id = (int)($req->get_param('pipeline_id') ?: 0);
        return $this->success(Ofnoacomps_CRM_Deal::kanban($pipeline_id));
    }

    // ── Pipeline handlers ─────────────────────────────────────────────────────

    public function list_pipelines(WP_REST_Request $req): WP_REST_Response {
        $pipelines = Ofnoacomps_CRM_Pipeline::get_all();
        foreach ($pipelines as &$p) {
            $p->stages = Ofnoacomps_CRM_Pipeline::get_stages($p->id);
        }
        return $this->success($pipelines);
    }

    public function get_stages(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Pipeline::get_stages((int)$req['id']));
    }

    // ── Activity handlers ─────────────────────────────────────────────────────

    public function create_activity(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        $id   = Ofnoacomps_CRM_Activity::create($data);
        return $this->success(Ofnoacomps_CRM_Activity::get($id), 201);
    }

    public function delete_activity(WP_REST_Request $req): WP_REST_Response {
        return Ofnoacomps_CRM_Activity::delete((int)$req['id']) ? $this->success(['deleted' => true]) : $this->error('Not found', 404);
    }

    // ── Report handlers ────────────────────────────────────────────────────────

    public function report_summary(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Reports::dashboard_summary(
            $req->get_param('from') ?: '',
            $req->get_param('to')   ?: ''
        ));
    }

    public function report_leads_over_time(WP_REST_Request $req): WP_REST_Response {
        $from = $req->get_param('from') ?: date('Y-m-01');
        $to   = $req->get_param('to')   ?: date('Y-m-d');
        return $this->success(Ofnoacomps_CRM_Reports::leads_over_time($from, $to, $req->get_param('group_by') ?: 'day'));
    }

    public function report_leads_by_source(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Reports::leads_by_source($req->get_param('from'), $req->get_param('to')));
    }

    public function report_pipeline_funnel(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Reports::pipeline_funnel((int)($req->get_param('pipeline_id') ?: 0)));
    }

    public function report_revenue(WP_REST_Request $req): WP_REST_Response {
        $from = $req->get_param('from') ?: date('Y-01-01');
        $to   = $req->get_param('to')   ?: date('Y-m-d');
        return $this->success(Ofnoacomps_CRM_Reports::revenue_over_time($from, $to, $req->get_param('group_by') ?: 'month'));
    }

    public function report_leaderboard(WP_REST_Request $req): WP_REST_Response {
        return $this->success(Ofnoacomps_CRM_Reports::rep_leaderboard(
            $req->get_param('from') ?: date('Y-01-01'),
            $req->get_param('to')   ?: date('Y-m-d')
        ));
    }

    // ── Public capture ─────────────────────────────────────────────────────────

    public function public_capture(WP_REST_Request $req): WP_REST_Response {
        $data = $req->get_json_params() ?: $req->get_body_params();
        // Require at least a name or email or phone
        if (empty($data['email']) && empty($data['phone']) && empty($data['first_name'])) {
            return $this->error('חסרים פרטים', 400);
        }
        $id = Ofnoacomps_CRM_Lead::create(array_map('sanitize_text_field', (array)$data));
        return $id ? $this->success(['id' => $id], 201) : $this->error('Error', 500);
    }

    public function ajax_submit_lead(): void {
        check_ajax_referer('ofnoacomps_crm_nonce', 'nonce');
        $allowed = ['first_name','last_name','email','phone','message','source','medium','campaign','utm_term','utm_content','referrer','landing_page','device_type'];
        $data = [];
        foreach ($allowed as $k) {
            if (!empty($_POST[$k])) $data[$k] = sanitize_text_field(wp_unslash($_POST[$k]));
        }
        $data['ip_address'] = ofnoacomps_crm_get_ip();
        $id = Ofnoacomps_CRM_Lead::create($data);
        wp_send_json_success(['id' => $id]);
    }


    // ── API Key handlers ──────────────────────────────────────────────────────

    public function list_api_keys($req) {
        return $this->success(Ofnoacomps_CRM_API_Keys::list_keys());
    }

    public function create_api_key($req) {
        $data = $req->get_json_params() ?: $req->get_body_params();
        $result = Ofnoacomps_CRM_API_Keys::generate(
            $data['name'] ?? '',
            $data['capabilities'] ?? ['read'],
            get_current_user_id()
        );
        if (isset($result['error'])) return $this->error($result['error'], 400);
        return $this->success($result, 201);
    }

    public function revoke_api_key($req) {
        $ok = Ofnoacomps_CRM_API_Keys::revoke((int)$req['id']);
        return $ok ? $this->success(['revoked' => true]) : $this->error('Not found', 404);
    }

    public function delete_api_key($req) {
        $ok = Ofnoacomps_CRM_API_Keys::delete((int)$req['id']);
        return $ok ? $this->success(['deleted' => true]) : $this->error('Not found', 404);
    }
    // ── Response helpers ────────────────────────────────────────────────────────

    private function success($data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response(['data' => $data], $status);
    }

    private function error(string $message, int $status = 400): WP_REST_Response {
        return new WP_REST_Response(['error' => $message], $status);
    }

    private function paginated(array $items, int $total, array $args): WP_REST_Response {
        return new WP_REST_Response([
            'data' => $items,
            'meta' => [
                'total'  => $total,
                'limit'  => $args['limit']  ?? 50,
                'offset' => $args['offset'] ?? 0,
            ],
        ], 200);
    }

    private function pagination_args(WP_REST_Request $req): array {
        return [
            'limit'    => (int) ($req->get_param('limit')    ?: 50),
            'offset'   => (int) ($req->get_param('offset')   ?: 0),
            'search'   => $req->get_param('search')   ?: '',
            'status'   => $req->get_param('status')   ?: '',
            'source'   => $req->get_param('source')   ?: '',
            'owner_id' => (int) ($req->get_param('owner_id') ?: 0),
            'date_from'=> $req->get_param('date_from') ?: '',
            'date_to'  => $req->get_param('date_to')   ?: '',
        ];
    }

    private function sanitize(array $data, array $fields): array {
        $clean = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) $clean[$f] = sanitize_text_field($data[$f]);
        }
        return $clean;
    }
}
