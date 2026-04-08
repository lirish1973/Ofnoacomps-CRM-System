<?php
defined('ABSPATH') || exit;

/**
 * API Key management for Ofnoacomps CRM.
 *
 * Keys format: ocrm_<32 hex chars>
 * Only the SHA-256 hash is stored in the DB — the full key is shown once.
 */
class Ofnoacomps_CRM_API_Keys {

    /**
     * Generate a new API key, store the hash, and return the full key (once only).
     *
     * @param string $name         Human-readable label.
     * @param array  $capabilities e.g. ['read','write']
     * @param int    $created_by   WP user ID.
     * @return array ['key' => '...', 'id' => int, 'prefix' => '...'] or ['error' => '...']
     */
    public static function generate( $name, $capabilities = [], $created_by = 0 ) {
        global $wpdb;

        $name = sanitize_text_field( $name );
        if ( empty( $name ) ) {
            return [ 'error' => 'שם המפתח לא יכול להיות ריק' ];
        }

        // Build key: ocrm_ + 32 hex chars
        $raw_key   = 'ocrm_' . bin2hex( random_bytes( 16 ) );
        $prefix    = substr( $raw_key, 0, 12 );          // "ocrm_XXXXXX"
        $key_hash  = hash( 'sha256', $raw_key );
        $caps_json = wp_json_encode( array_values( (array) $capabilities ) );

        $result = $wpdb->insert(
            Ofnoacomps_CRM_Database::table( 'api_keys' ),
            [
                'name'         => $name,
                'key_prefix'   => $prefix,
                'key_hash'     => $key_hash,
                'capabilities' => $caps_json,
                'is_active'    => 1,
                'created_by'   => (int) $created_by,
            ]
        );

        if ( ! $result ) {
            return [ 'error' => 'שגיאה ביצירת המפתח' ];
        }

        return [
            'id'     => (int) $wpdb->insert_id,
            'key'    => $raw_key,   // returned ONCE — never stored in plain text
            'prefix' => $prefix,
            'name'   => $name,
        ];
    }

    /**
     * Authenticate an incoming API key.
     * Updates last_used_at on success.
     *
     * @param string $raw_key Full key string (ocrm_...).
     * @return object|false   Row object on success, false on failure.
     */
    public static function authenticate( $raw_key ) {
        global $wpdb;

        if ( empty( $raw_key ) || strpos( $raw_key, 'ocrm_' ) !== 0 ) {
            return false;
        }

        $hash = hash( 'sha256', sanitize_text_field( $raw_key ) );
        $row  = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Ofnoacomps_CRM_Database::table( 'api_keys' ) .
            " WHERE key_hash = %s AND is_active = 1",
            $hash
        ) );

        if ( ! $row ) {
            return false;
        }

        // Update last used timestamp (best effort)
        $wpdb->update(
            Ofnoacomps_CRM_Database::table( 'api_keys' ),
            [ 'last_used_at' => current_time( 'mysql' ) ],
            [ 'id' => $row->id ]
        );

        return $row;
    }

    /**
     * List all API keys (without hash).
     *
     * @return array
     */
    public static function list_keys() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id, name, key_prefix, capabilities, is_active, last_used_at, created_by, created_at
             FROM " . Ofnoacomps_CRM_Database::table( 'api_keys' ) .
            " ORDER BY created_at DESC"
        );

        foreach ( $rows as &$row ) {
            $row->capabilities = json_decode( $row->capabilities, true ) ?: [];
            $user = get_user_by( 'id', $row->created_by );
            $row->created_by_name = $user ? $user->display_name : 'מערכת';
        }

        return $rows ?: [];
    }

    /**
     * Revoke (deactivate) a key.
     *
     * @param int $id
     * @return bool
     */
    public static function revoke( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            Ofnoacomps_CRM_Database::table( 'api_keys' ),
            [ 'is_active' => 0 ],
            [ 'id' => (int) $id ]
        );
    }

    /**
     * Permanently delete a key.
     *
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete(
            Ofnoacomps_CRM_Database::table( 'api_keys' ),
            [ 'id' => (int) $id ]
        );
    }

    /**
     * Check if the current request is authenticated via API key.
     * Reads: Authorization: Bearer ocrm_... or X-API-Key: ocrm_...
     *
     * @return object|false  Key row or false.
     */
    public static function get_key_from_request() {
        // Try Authorization: Bearer header
        $auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if ( empty( $auth ) && function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
            $auth    = isset( $headers['Authorization'] ) ? $headers['Authorization'] : '';
        }
        if ( ! empty( $auth ) && preg_match( '/^Bearer\s+(ocrm_\S+)$/i', $auth, $m ) ) {
            return self::authenticate( $m[1] );
        }

        // Try X-API-Key header
        $xkey = isset( $_SERVER['HTTP_X_API_KEY'] ) ? $_SERVER['HTTP_X_API_KEY'] : '';
        if ( ! empty( $xkey ) ) {
            return self::authenticate( $xkey );
        }

        return false;
    }
}
