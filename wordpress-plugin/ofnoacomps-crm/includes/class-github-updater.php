<?php
/**
 * GitHub Auto-Updater
 *
 * Checks a JSON manifest on GitHub for newer versions (major + minor + patch).
 * Works for any plugin in the Ofnoacomps-CRM-System repo.
 * Compatible with PHP 7.4+
 *
 * Usage (inside main plugin file, on plugins_loaded):
 *   new Ofnoacomps_GitHub_Updater( __FILE__, 'ofnoacomps-crm', OFNOACOMPS_CRM_VERSION );
 */

defined( 'ABSPATH' ) || exit;

class Ofnoacomps_GitHub_Updater {

    /** URL of the central version manifest in the GitHub repo. */
    const MANIFEST_URL = 'https://raw.githubusercontent.com/lirish1973/Ofnoacomps-CRM-System/main/plugin-updates.json';

    /** How long to cache the manifest (seconds). */
    const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /** @var string */
    private $plugin_file;

    /** @var string */
    private $plugin_slug;

    /** @var string */
    private $plugin_key;

    /** @var string */
    private $current_version;

    /** @var string */
    private $transient_key;

    /**
     * @param string $plugin_file    Absolute path to main plugin file.
     * @param string $plugin_key     Key inside manifest JSON, e.g. "ofnoacomps-crm".
     * @param string $current_version Current plugin version string.
     */
    public function __construct( $plugin_file, $plugin_key, $current_version ) {
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
     *
     * @return object|null
     */
    private function get_remote_info() {
        $cached = get_transient( $this->transient_key );
        if ( $cached !== false ) {
            return $cached ?: null;
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
     *
     * @param object $transient
     * @return object
     */
    public function inject_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $info = $this->get_remote_info();
        if ( ! $info || empty( $info->version ) ) {
            return $transient;
        }

        if ( version_compare( $info->version, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'id'            => $this->plugin_slug,
                'slug'          => dirname( $this->plugin_slug ),
                'plugin'        => $this->plugin_slug,
                'new_version'   => $info->version,
                'url'           => isset( $info->url ) ? $info->url : 'https://github.com/lirish1973/Ofnoacomps-CRM-System',
                'package'       => isset( $info->download_url ) ? $info->download_url : '',
                'icons'         => [],
                'banners'       => [],
                'banners_rtl'   => [],
                'tested'        => get_bloginfo( 'version' ),
                'requires_php'  => '7.4',
                'compatibility' => new stdClass(),
            ];
        } else {
            $transient->no_update[ $this->plugin_slug ] = (object) [
                'id'          => $this->plugin_slug,
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $this->current_version,
                'url'         => isset( $info->url ) ? $info->url : '',
                'package'     => '',
                'icons'       => [],
                'banners'     => [],
            ];
        }

        return $transient;
    }

    /**
     * Provides plugin info popup in the admin (changelog, version, etc.).
     *
     * @param mixed  $result
     * @param string $action
     * @param object $args
     * @return mixed
     */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( ( isset( $args->slug ) ? $args->slug : '' ) !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $info = $this->get_remote_info();
        if ( ! $info ) {
            return $result;
        }

        return (object) [
            'name'          => isset( $info->name )         ? $info->name         : $this->plugin_key,
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => isset( $info->version )      ? $info->version      : $this->current_version,
            'author'        => isset( $info->author )        ? $info->author       : 'Ofnoacomps',
            'requires'      => isset( $info->requires_wp )  ? $info->requires_wp  : '5.8',
            'requires_php'  => isset( $info->requires_php ) ? $info->requires_php : '7.4',
            'last_updated'  => isset( $info->last_updated ) ? $info->last_updated : '',
            'download_link' => isset( $info->download_url ) ? $info->download_url : '',
            'sections'      => [
                'description' => isset( $info->description ) ? $info->description : '',
                'changelog'   => isset( $info->changelog )   ? $info->changelog   : '',
            ],
        ];
    }

    /**
     * Forces auto-update ON for this plugin (covers major + minor + patch).
     *
     * @param bool|null $update
     * @param object    $item
     * @return bool|null
     */
    public function force_auto_update( $update, $item ) {
        if ( isset( $item->plugin ) && $item->plugin === $this->plugin_slug ) {
            return true;
        }
        return $update;
    }

    /**
     * Clears the version cache after any plugin upgrade.
     *
     * @param \WP_Upgrader $upgrader
     * @param array        $hook_extra
     * @return void
     */
    public function clear_cache( $upgrader, $hook_extra ) {
        if ( isset( $hook_extra['type'] ) && $hook_extra['type'] === 'plugin' ) {
            delete_transient( $this->transient_key );
        }
    }

    /**
     * Manually clear the cache (useful for admin "force check" button).
     *
     * @return void
     */
    public function flush() {
        delete_transient( $this->transient_key );
    }
}
