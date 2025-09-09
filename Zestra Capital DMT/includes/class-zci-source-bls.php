<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ZCI_Source_BLS {
    public static function get_or_create_source( string $api_key ): int {
        return ZCI_Repository::upsert_source([
            'source_key'  => 'bls',
            'source_type' => 'api',
            'name'        => 'US BLS',
            'credentials' => wp_json_encode( [ 'api_key' => $api_key ] ),
        ]);
    }

    public static function fetch_series( string $api_key, string $series_id ): array {
        $endpoint = 'https://api.bls.gov/publicAPI/v2/timeseries/data/';
        $body = [
            'seriesid' => [ $series_id ],
            'registrationKey' => $api_key,
        ];
        $response = wp_remote_post( $endpoint, [
            'timeout' => 30,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ] );
        if ( is_wp_error( $response ) ) { return []; }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) { return []; }
        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $json['Results']['series'][0]['data'] ) ) { return []; }
        $data = $json['Results']['series'][0]['data'];
        $points = [];
        foreach ( $data as $row ) {
            $year = isset( $row['year'] ) ? (string) $row['year'] : null;
            $period = isset( $row['period'] ) ? (string) $row['period'] : null; // e.g., M01 for Jan
            $value = isset( $row['value'] ) ? $row['value'] : null;
            if ( $year && $period && preg_match( '/^M(\d{2})$/', $period, $m ) ) {
                $month = $m[1];
                $date = sprintf( '%s-%s-01', $year, $month );
                $points[] = [ 'date' => $date, 'value' => $value ];
            }
        }
        usort( $points, function( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } );
        return $points;
    }
}


