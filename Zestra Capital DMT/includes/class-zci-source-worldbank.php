<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ZCI_Source_WorldBank {
    public static function get_or_create_source(): int {
        return ZCI_Repository::upsert_source([
            'source_key'  => 'worldbank',
            'source_type' => 'api',
            'name'        => 'World Bank Open Data',
        ]);
    }

    public static function fetch_series( string $country_iso, string $indicator_code ): array {
        $country_iso = strtolower( trim( $country_iso ) );
        $indicator_code = trim( $indicator_code );
        $url = add_query_arg([
            'format'   => 'json',
            'per_page' => '20000',
        ], "https://api.worldbank.org/v2/country/{$country_iso}/indicator/{$indicator_code}" );

        $response = wp_remote_get( $url, [ 'timeout' => 30 ] );
        if ( is_wp_error( $response ) ) { return []; }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) { return []; }
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );
        if ( ! is_array( $json ) || count( $json ) < 2 || ! is_array( $json[1] ) ) { return []; }

        $points = [];
        foreach ( $json[1] as $row ) {
            if ( ! isset( $row['date'] ) ) { continue; }
            $date = $row['date'];
            // WB can be annual; normalize to YYYY-12-31 for consistency
            $date_norm = strlen( $date ) === 4 ? $date . '-12-31' : substr( (string) $date, 0, 10 );
            $value = $row['value'];
            $points[] = [ 'date' => $date_norm, 'value' => $value ];
        }
        // Reverse chronological order returned; ensure ascending by date
        usort( $points, function( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } );
        return $points;
    }
}


