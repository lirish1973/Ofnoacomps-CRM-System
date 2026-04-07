<?php
/**
 * GitHub Auto-Updater
 *
 * Checks a JSON manifest on GitHub for newer versions (major + minor + patch).
 * Works for any plugin in the Ofnoacomps-CRM-System repo.
 *
 * Usage (inside main plugin file, after plugins_loaded):
 *   new Ofnoacomps_GitHub_Updater( __FILE__, 'ofnoacomps-crm', OFNOACOMPS_CRM_VERSION );
 */

defined( 'ABSPATH' ) || exit;

class Ofnoacomps_GitHub_Updater {

    /** URL of the central version manifest in the GitHub repo. */
    const MANIFEST_URL = 'https://raw.githubusercontent.com/lirish1973/Ofnoacomps-CRM-System/main/plugin-updates.json';

    /** How long to cache the manifest (seconds). */
    const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    private string $plugin_file;    // absolute path to main plugin file
    private string $plugin_slug;    // e.g. "ofnoacomps-crm/ofnoacomps-crm.php"
    private string $plugin_key;     // key inside manifest JSON, e.g. "ocrm-crm"
    private string $current_version;
    private string $transient_key;

    public function __construct( string $plugin_file, string $plugin_key, string $current_version ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->plugin_key      = $plugin_key;
        $this->current_version = $current_version;
        $this->transient_key   = 'ofnoacomps_ghupd_' . md5( $plugin_key );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'auto_update_plugin',                    [ $this, 'force_auto_update' ], 10, 2 );
        add_action( 'upgrader_process_complete',             [ $this, 'clear_cache' ], 10, 2 );
    }

    /* -----------------------------------------------------------------------
     * Private helpers
     * --------------------------------------------------------------------- */

    /**
     * Fetches (and caches) the JSON manifest from GitHub.
     * Returns the entry for this plugin, or null on failure.
     */
    private function get_remote_info(): ?object {
        $cached = get_transient( $this->transient_key );
        if ( $cached !== false ) {
            return $cached ?: null;   // false = not set; '' = cached "no data"
        }

        $response = wp_remote_get( self::MANIFEST_URL, [
            'headers'   => [
                'Accept'     => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ],
            'timeout'   => 10,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $this->transient_key, '', self::CACHE_TTL );
            return null;
        }

        $manifest = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $manifest || ! isset( $manifest->{ $this->plugin_key } ) ) {
            set_transient( $this->transient_key, '', self::CACHE_TTL );
            return null;
        }

        $info = $manifest->{ $this->plugin_key };
        set_transient( $this->transient_key, $info, self::CACHE_TTL );
        return $info;
    }

    /* -----------------------------------------------------------------------
     * WordPress hooks
     * --------------------------------------------------------------------- */

    /**
     * Injects update data into WordPress's update transient when a newer
     * version is available (major, minor, OR patch).
     */
    public function inject_update( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $info = $this->get_remote_info();
        if ( ! $info || empty( $info->version ) ) {
            return $transient;
        }

        // version_compare handles "1.0.1 > 1.0.0", "1.1.0 > 1.0.0", "2.0.0 > 1.0.0"
        if ( version_compare( $info->version, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'id'            => $this->plugin_slug,
                'slug'          => dirname( $this->plugin_slug ),
                'plugin'        => $this->plugin_slug,
                'new_version'   => $info->version,
                'url'           => $info->url ?? 'https://github.com/lirish1973/Ofnoacomps-CRM-System',
                'package'       => $info->download_url ?? '',
                'icons'         => [],
                'banners'       => [],
                'banners_rtl'   => [],
                'tested'        => get_bloginfo( 'version' ),
                'requires_php'  => '7.4',
                'compatibility' => new stdClass(),
            ];
        } else {
            // Confirm plugin is up-to-date (prevents "update available" stuck notices)
            $transient->no_update[ $this->plugin_slug ] = (object) [
                'id'          => $this->plugin_slug,
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $this->current_version,
                'url'         => $info->url ?? '',
                'package'     => '',
                'icons'       => [],
                'banners'     => [],
            ];
        }

        return $transient;
    }

    /**
     * Provides plugin info popup in the admin (changelog, version, etc.).
     */
    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( ( $args->slug ?? '' ) !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $info = $this->get_remote_info();
        if ( ! $info ) {
            return $result;
        }

        return (object) [
            'name'          => $info->name ?? $this->plugin_key,
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => $info->version ?? $this->current_version,
            'author'        => $info->author ?? 'Ofnoacomps',
            'requires'      => $info->requires_wp ?? '5.8',
            'requires_php'  => $info->requires_php ?? '7.4',
            'last_updated'  => $info->last_updated ?? '',
            'download_link' => $info->download_url ?? '',
            'sections'      => [
                'description' => $info->description ?? '',
                'changelog'   => $info->changelog ?? '',
            ],
        ];
    }

    /**
     * Forces auto-update ON for this plugin (covers major + minor + patch).
     * WordPress auto-updates respect both major and minor by default when
     * this filter returns true.
     */
    public function force_auto_update( bool|null $update, object $item ): bool|null {
        if ( isset( $item->plugin ) && $item->plugin === $this->plugin_slug ) {
            return true;
        }
        return $update;
    }

    /**
     * Clears the version cache after any plugin upgrade so the next check
     * fetches fresh data.
     */
    public function clear_cache( WP_Upgrader $upgrader, array $hook_extra ): void {
        if ( ( $hook_extra['type'] ?? '' ) === 'plugin' ) {
            delete_transient( $this->transient_key );
        }
    }

    /**
     * Manually clear the cache (useful for admin "force check" button).
     */
    public function flush(): void {
        delete_transient( $this->transient_key );
    }
}
