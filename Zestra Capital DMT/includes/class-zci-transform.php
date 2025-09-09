<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ZCI_Transforms {
    public static function create_transform( string $base_slug, string $out_slug, string $out_name, string $method, array $params = [] ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'zci_transforms';
        $now = current_time( 'mysql' );
        $payload = [
            'base_indicator_slug'   => $base_slug,
            'output_indicator_slug' => $out_slug,
            'output_display_name'   => $out_name,
            'method'                => $method,
            'params'                => wp_json_encode( $params ),
            'created_at'            => $now,
            'updated_at'            => $now,
        ];
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE output_indicator_slug=%s", $out_slug ) );
        if ( $exists ) {
            $wpdb->update( $table, $payload, [ 'id' => (int) $exists ] );
            return true;
        }
        return (bool) $wpdb->insert( $table, $payload );
    }

    public static function run_transform( string $base_slug, string $out_slug, string $out_name, string $method, array $params = [] ): int {
        // Fetch base series
        $rows = ZCI_Repository::query_series( $base_slug, null, null );
        if ( empty( $rows ) ) { return 0; }

        // Map by date
        $by_date = [];
        foreach ( $rows as $r ) {
            $by_date[$r['obs_date']] = is_null( $r['value'] ) ? null : (float) $r['value'];
        }
        ksort( $by_date );

        $points = [];
        $dates = array_keys( $by_date );
        $prev_by_offset = function( $index, $offset ) use ( $dates, $by_date ) {
            $target = $index - $offset;
            if ( $target < 0 ) { return null; }
            $d = $dates[$target];
            return $by_date[$d] ?? null;
        };

        for ( $i = 0; $i < count( $dates ); $i++ ) {
            $d = $dates[$i];
            $v = $by_date[$d];
            if ( $v === null ) { $points[] = [ 'date' => $d, 'value' => null ]; continue; }

            switch ( $method ) {
                case 'mom_pct':
                    $prev = $prev_by_offset( $i, 1 );
                    $val = ( $prev === null || $prev == 0 ) ? null : ( ( $v - $prev ) / $prev ) * 100.0;
                    break;
                case 'qoq_pct':
                    $prev = $prev_by_offset( $i, 3 );
                    $val = ( $prev === null || $prev == 0 ) ? null : ( ( $v - $prev ) / $prev ) * 100.0;
                    break;
                case 'yoy_pct':
                    $prev = $prev_by_offset( $i, 12 );
                    $val = ( $prev === null || $prev == 0 ) ? null : ( ( $v - $prev ) / $prev ) * 100.0;
                    break;
                default:
                    $val = null;
            }
            $points[] = [ 'date' => $d, 'value' => ( $val === null ? null : (float) $val ) ];
        }

        $source_id = ZCI_Repository::upsert_source([
            'source_key'  => 'derived',
            'source_type' => 'derived',
            'name'        => 'Derived Indicators',
        ]);
        ZCI_Repository::upsert_indicator([
            'slug'         => $out_slug,
            'display_name' => $out_name,
            'source_id'    => $source_id,
            'metadata'     => wp_json_encode( [ 'base' => $base_slug, 'method' => $method ] ),
        ]);
        return ZCI_Repository::store_series_points( $out_slug, null, $source_id, $points );
    }
}


