<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ZCI_Source_FRED {
    public static function get_or_create_source( string $api_key ): int {
        $credentials = wp_json_encode( [ 'api_key' => $api_key ] );
        return ZCI_Repository::upsert_source([
            'source_key'  => 'fred',
            'source_type' => 'api',
            'name'        => 'FRED',
            'credentials' => $credentials,
            'config'      => null,
        ]);
    }

    public static function fetch_series( string $api_key, string $series_id ): array {
        $url = add_query_arg([
            'series_id' => $series_id,
            'api_key'   => rawurlencode( $api_key ),
            'file_type' => 'json',
        ], 'https://api.stlouisfed.org/fred/series/observations' );

        $response = wp_remote_get( $url, [ 'timeout' => 30 ] );
        if ( is_wp_error( $response ) ) {
            return [];
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) { return []; }
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );
        if ( ! $json || empty( $json['observations'] ) ) { return []; }
        $points = [];
        foreach ( $json['observations'] as $obs ) {
            $date = isset( $obs['date'] ) ? substr( $obs['date'], 0, 10 ) : null;
            $value = isset( $obs['value'] ) ? $obs['value'] : null;
            if ( $date ) { $points[] = [ 'date' => $date, 'value' => $value ]; }
        }
        return $points;
    }
}


