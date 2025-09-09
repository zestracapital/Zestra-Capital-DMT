<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ZCI_Importer {
    public static function import_google_sheet_url( string $sheet_url, string $indicator_slug, string $indicator_name, ?string $country_iso = null ): int {
        $csv_url = self::to_google_csv_url( $sheet_url );
        if ( ! $csv_url ) { return 0; }
        return self::import_csv_from_url( $csv_url, $indicator_slug, $indicator_name, $country_iso );
    }

    private static function to_google_csv_url( string $url ): ?string {
        // Accept already-published CSV URLs
        if ( strpos( $url, 'tqx=out:csv' ) !== false || preg_match( '/\.csv(\?|$)/i', $url ) ) {
            return $url;
        }
        // Common edit URL -> CSV export
        if ( strpos( $url, 'docs.google.com/spreadsheets' ) !== false ) {
            $parts = explode( '/edit', $url );
            if ( ! empty( $parts[0] ) ) {
                return rtrim( $parts[0], '/' ) . '/gviz/tq?tqx=out:csv';
            }
        }
        return null;
    }

    public static function import_csv_from_url( string $url, string $indicator_slug, string $indicator_name, ?string $country_iso = null ): int {
        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) { return 0; }
        $count = self::import_csv_from_file( $tmp, $indicator_slug, $indicator_name, $country_iso );
        @unlink( $tmp );
        return $count;
    }

    public static function import_csv_from_file( string $filepath, string $indicator_slug, string $indicator_name, ?string $country_iso = null ): int {
        $handle = fopen( $filepath, 'r' );
        if ( ! $handle ) { return 0; }

        // Detect header: expect columns Date, Value, optional Country
        $header = fgetcsv( $handle );
        if ( ! $header ) { fclose( $handle ); return 0; }

        $date_idx = self::find_col_index( $header, [ 'date', 'Date', 'DATE', 'obs_date' ] );
        $value_idx = self::find_col_index( $header, [ 'value', 'Value', 'VALUE', 'obs_value' ] );
        $country_idx = self::find_col_index( $header, [ 'country', 'Country', 'COUNTRY', 'iso', 'ISO', 'iso_code' ] );

        if ( $date_idx === -1 || $value_idx === -1 ) { fclose( $handle ); return 0; }

        $source_id = ZCI_Repository::upsert_source([
            'source_key'  => 'manual_csv',
            'source_type' => 'manual',
            'name'        => 'Manual CSV/Sheet',
        ]);
        ZCI_Repository::upsert_indicator([
            'slug'         => $indicator_slug,
            'display_name' => $indicator_name,
            'source_id'    => $source_id,
        ]);

        $points = [];
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $date = isset( $row[$date_idx] ) ? trim( (string) $row[$date_idx] ) : '';
            $value = isset( $row[$value_idx] ) ? trim( (string) $row[$value_idx] ) : '';
            $country = $country_iso;
            if ( $country_idx !== -1 ) {
                $detected = isset( $row[$country_idx] ) ? strtoupper( substr( trim( (string) $row[$country_idx] ), 0, 3 ) ) : '';
                if ( $detected ) { $country = $detected; }
            }
            if ( $date === '' ) { continue; }
            // Normalize date to YYYY-MM-DD if possible
            $date_norm = substr( $date, 0, 10 );
            $points[] = [ 'date' => $date_norm, 'value' => $value, 'country' => $country ];
        }
        fclose( $handle );

        // Split by country to store
        $by_country = [];
        foreach ( $points as $p ) {
            $key = $p['country'] ?: null;
            if ( ! isset( $by_country[$key] ) ) { $by_country[$key] = []; }
            $by_country[$key][] = [ 'date' => $p['date'], 'value' => $p['value'] ];
        }

        $total = 0;
        foreach ( $by_country as $c => $chunk ) {
            $total += ZCI_Repository::store_series_points( $indicator_slug, $c, $source_id, $chunk );
        }
        return $total;
    }

    private static function find_col_index( array $header, array $candidates ): int {
        foreach ( $header as $idx => $name ) {
            foreach ( $candidates as $cand ) {
                if ( strcasecmp( trim( (string) $name ), $cand ) === 0 ) { return (int) $idx; }
            }
        }
        return -1;
    }
}


