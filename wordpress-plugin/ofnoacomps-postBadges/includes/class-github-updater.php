<?php
/**
 * OPB GitHub Auto-Updater
 * Identical logic to the CRM updater — adapted for ofnoacomps-postBadges.
 * Uses a different class name (OPB_GitHub_Updater) to avoid conflicts
 * if both plugins are active simultaneously.
 */

defined( 'ABSPATH' ) || exit;

class OPB_GitHub_Updater {

    const MANIFEST_URL = 'https://raw.githubusercontent.com/lirish1973/Ofnoacomps-CRM-System/main/plugin-updates.json';
    const CACHE_TTL    = HOUR_IN_SECONDS;

    private $plugin_file;
    private $plugin_slug;
    private $plugin_key;
    private $current_version;
    private $transient_key;

    public function __construct( $plugin_file, $plugin_key, $current_version ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->plugin_key      = $plugin_key;
        $this->current_version = $current_version;
        $this->transient_key   = 'opb_ghupd_' . md5( $plugin_key );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'auto_update_plugin',                    [ $this, 'force_auto_update' ], 10, 2 );
        add_action( 'upgrader_process_complete',             [ $this, 'clear_cache' ], 10, 2 );
        add_action( 'rest_api_init',                         [ $this, 'register_flush_endpoint' ] );
    }

    private function get_remote_info() {
        $cached = get_transient( $this->transient_key );
        if ( $cached !== false ) {
            return $cached ?: null;
        }

        $response = wp_remote_get( self::MANIFEST_URL, [
            'headers' => [
                'Accept'        => 'application/json',
                'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                'Cache-Control' => 'no-cache',
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

    public function inject_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $info = $this->get_remote_info();
        if ( ! $info || empty( $info->version ) ) return $transient;

        if ( version_compare( $info->version, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'id'           => $this->plugin_slug,
                'slug'         => dirname( $this->plugin_slug ),
                'plugin'       => $this->plugin_slug,
                'new_version'  => $info->version,
                'url'          => isset( $info->url ) ? $info->url : 'https://github.com/lirish1973/Ofnoacomps-CRM-System',
                'package'      => isset( $info->download_url ) ? $info->download_url : '',
                'icons'        => [],
                'banners'      => [],
                'banners_rtl'  => [],
                'tested'       => get_bloginfo( 'version' ),
                'requires_php' => '7.4',
                'compatibility'=> new stdClass(),
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

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ( isset( $args->slug ) ? $args->slug : '' ) !== dirname( $this->plugin_slug ) ) return $result;

        $info = $this->get_remote_info();
        if ( ! $info ) return $result;

        return (object) [
            'name'         => isset( $info->name )         ? $info->name         : $this->plugin_key,
            'slug'         => dirname( $this->plugin_slug ),
            'version'      => isset( $info->version )      ? $info->version      : $this->current_version,
            'author'       => isset( $info->author )       ? $info->author       : 'Ofnoacomps',
            'requires'     => isset( $info->requires_wp )  ? $info->requires_wp  : '5.8',
            'requires_php' => isset( $info->requires_php ) ? $info->requires_php : '7.4',
            'last_updated' => isset( $info->last_updated ) ? $info->last_updated : '',
            'download_link'=> isset( $info->download_url ) ? $info->download_url : '',
            'sections'     => [
                'description' => isset( $info->description ) ? $info->description : '',
                'changelog'   => isset( $info->changelog )   ? $info->changelog   : '',
            ],
        ];
    }

    public function force_auto_update( $update, $item ) {
        if ( isset( $item->plugin ) && $item->plugin === $this->plugin_slug ) return true;
        return $update;
    }

    public function clear_cache( $upgrader, $hook_extra ) {
        if ( isset( $hook_extra['type'] ) && $hook_extra['type'] === 'plugin' ) {
            delete_transient( $this->transient_key );
        }
    }

    public function flush() {
        delete_transient( $this->transient_key );
        delete_site_transient( 'update_plugins' );
    }

    public function register_flush_endpoint() {
        register_rest_route( 'ofnoacomps-postbadges/v1', '/flush-update-cache', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_flush_cache' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function rest_flush_cache( $req ) {
        $secret = defined( 'OPB_UPDATE_SECRET' ) ? OPB_UPDATE_SECRET : '';

        if ( empty( $secret ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return new WP_REST_Response( [ 'error' => 'Unauthorized' ], 403 );
            }
        } else {
            $token = $req->get_header( 'X-OPB-Flush-Token' );
            if ( $token !== $secret ) {
                return new WP_REST_Response( [ 'error' => 'Invalid token' ], 403 );
            }
        }

        $this->flush();
        return new WP_REST_Response( [
            'flushed'  => true,
            'plugin'   => $this->plugin_key,
            'manifest' => self::MANIFEST_URL,
        ], 200 );
    }
}
