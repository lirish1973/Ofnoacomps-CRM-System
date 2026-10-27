<?php
/**
 * Command Center — a front-end page on the site itself (not inside wp-admin)
 * that only administrators can open.
 *
 * Access rules:
 *   - Not logged in            → redirected to wp-login.php, then back here.
 *   - Logged in, not an admin  → a normal 404, so the page's existence is not revealed.
 *   - Administrator            → the dashboard.
 *
 * The page is a virtual route (default /crm-dashboard/), rendered with its own
 * standalone template rather than the theme, and is always sent with
 * noindex + no-cache headers so it never lands in search engines or a page cache.
 * Without pretty permalinks it also answers at /?ofnoacomps_dash=1.
 */

defined('ABSPATH') || exit;

class Ofnoacomps_CRM_Frontend_Dashboard {

    const QUERY_VAR      = 'ofnoacomps_dash';
    const OPT_ENABLED    = 'ofnoacomps_crm_dash_enabled';
    const OPT_SLUG       = 'ofnoacomps_crm_dash_slug';
    const OPT_RULES_SIG  = 'ofnoacomps_crm_dash_rules';
    const DEFAULT_SLUG   = 'crm-dashboard';
    const CAPABILITY     = 'manage_options';

    public static function init(): void {
        add_action('init',              [__CLASS__, 'register_route'], 20);
        add_filter('query_vars',        [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'maybe_render'], 1);
        add_action('admin_bar_menu',    [__CLASS__, 'admin_bar_link'], 80);
    }

    // ── Settings helpers ──────────────────────────────────────────────────────

    public static function enabled(): bool {
        return (bool) get_option(self::OPT_ENABLED, 1);
    }

    public static function slug(): string {
        $slug = sanitize_title((string) get_option(self::OPT_SLUG, self::DEFAULT_SLUG));
        return $slug !== '' ? $slug : self::DEFAULT_SLUG;
    }

    public static function url(array $args = []): string {
        $base = get_option('permalink_structure')
            ? home_url(user_trailingslashit(self::slug()))
            : add_query_arg(self::QUERY_VAR, '1', home_url('/'));
        return $args ? add_query_arg($args, $base) : $base;
    }

    public static function user_can_view(): bool {
        return is_user_logged_in() && current_user_can(self::CAPABILITY);
    }

    // ── Routing ───────────────────────────────────────────────────────────────

    public static function query_vars(array $vars): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function register_route(): void {
        if (!self::enabled()) {
            // Turned off: drop our rule from the stored rewrite table once.
            if (get_option(self::OPT_RULES_SIG) !== 'off') {
                flush_rewrite_rules(false);
                update_option(self::OPT_RULES_SIG, 'off', true);
            }
            return;
        }
        $slug = self::slug();
        add_rewrite_rule('^' . preg_quote($slug, '/') . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top');

        // Flush once whenever the slug (or this rule's shape) changes — never on every load.
        $sig = 'v1:' . $slug;
        if (get_option(self::OPT_RULES_SIG) !== $sig) {
            flush_rewrite_rules(false);
            update_option(self::OPT_RULES_SIG, $sig, true);
        }
    }

    /** Force a rewrite flush on the next request (used after settings change / activation). */
    public static function reset_rules(): void {
        delete_option(self::OPT_RULES_SIG);
    }

    public static function maybe_render(): void {
        if (!get_query_var(self::QUERY_VAR) || !self::enabled()) {
            return;
        }

        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Referrer-Policy: same-origin', true);
        header('X-Frame-Options: SAMEORIGIN', true);
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // WP Rocket / W3TC / LiteSpeed etc.
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(self::url()));
            exit;
        }

        if (!current_user_can(self::CAPABILITY)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            // Let the theme render its regular 404 page.
            return;
        }

        $days = isset($_GET['range']) ? (int) $_GET['range'] : 30;
        $data = (new Ofnoacomps_CRM_Dashboard_Data($days))->all();

        status_header(200);
        require OFNOACOMPS_CRM_PLUGIN_DIR . 'templates/command-center.php';
        exit;
    }

    // ── Admin bar shortcut (admins only) ──────────────────────────────────────

    public static function admin_bar_link($bar): void {
        if (!self::enabled() || !self::user_can_view()) {
            return;
        }
        $bar->add_node([
            'id'    => 'ofnoacomps-command-center',
            'title' => '<span class="ab-icon dashicons dashicons-chart-area" style="top:2px"></span>מרכז שליטה',
            'href'  => self::url(),
            'meta'  => ['title' => 'דאשבורד לידים, סוכן מטא וסוכן SEO'],
        ]);
    }
}
