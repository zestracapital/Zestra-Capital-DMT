<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ZCI_Repository {
    public static function upsert_source( array $data ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'zci_sources';
        $defaults = [ 'source_key' => '', 'source_type' => '', 'name' => '', 'credentials' => null, 'config' => null, 'status' => 'active' ];
        $row = wp_parse_args( $data, $defaults );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source_key = %s", $row['source_key'] ) );
        $now = current_time( 'mysql' );
        $payload = [
            'source_key' => $row['source_key'],
            'source_type'=> $row['source_type'],
            'name'       => $row['name'],
            'credentials'=> $row['credentials'],
            'config'     => $row['config'],
            'status'     => $row['status'],
            'updated_at' => $now,
        ];
        if ( $existing ) {
            $wpdb->update( $table, $payload, [ 'id' => (int) $existing ] );
            return (int) $existing;
        } else {
            $payload['created_at'] = $now;
            $wpdb->insert( $table, $payload );
            return (int) $wpdb->insert_id;
        }
    }

    public static function upsert_indicator( array $data ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'zci_indicators';
        $defaults = [ 'slug' => '', 'display_name' => '', 'category' => null, 'frequency' => null, 'units' => null, 'source_id' => null, 'external_code' => null, 'metadata' => null, 'is_active' => 1 ];
        $row = wp_parse_args( $data, $defaults );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $row['slug'] ) );
        $now = current_time( 'mysql' );
        $payload = [
            'slug'         => $row['slug'],
            'display_name' => $row['display_name'],
            'category'     => $row['category'],
            'frequency'    => $row['frequency'],
            'units'        => $row['units'],
            'source_id'    => $row['source_id'],
            'external_code'=> $row['external_code'],
            'metadata'     => $row['metadata'],
            'is_active'    => (int) $row['is_active'],
            'updated_at'   => $now,
        ];
        if ( $existing ) {
            $wpdb->update( $table, $payload, [ 'id' => (int) $existing ] );
            return (int) $existing;
        } else {
            $payload['created_at'] = $now;
            $wpdb->insert( $table, $payload );
            return (int) $wpdb->insert_id;
        }
    }

    public static function store_series_points( string $indicator_slug, ?string $country_iso, int $source_id, array $points ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'zci_series';
        $count = 0;
        $now = current_time( 'mysql' );
        foreach ( $points as $p ) {
            if ( empty( $p['date'] ) ) { continue; }
            $value = isset( $p['value'] ) && $p['value'] !== '' ? (float) $p['value'] : null;
            $wpdb->insert( $table, [
                'country_iso'    => $country_iso,
                'indicator_slug' => $indicator_slug,
                'obs_date'       => $p['date'],
                'value'          => $value,
                'source_id'      => $source_id,
                'last_updated_at'=> $now,
            ] );
            $count++;
        }
        return $count;
    }

    public static function query_series( string $indicator_slug, ?array $countries = null, ?string $range = null ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'zci_series';
        $where = [ $wpdb->prepare( 'indicator_slug = %s', $indicator_slug ) ];
        if ( $countries && count( $countries ) > 0 ) {
            $in = '(' . implode( ',', array_fill( 0, count( $countries ), '%s' ) ) . ')';
            $where[] = $wpdb->prepare( 'country_iso IN ' . $in, $countries );
        }
        if ( $range ) {
            $cutoff = self::range_to_cutoff_date( $range );
            if ( $cutoff ) {
                $where[] = $wpdb->prepare( 'obs_date >= %s', $cutoff );
            }
        }
        $sql = 'SELECT country_iso, obs_date, value FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY obs_date ASC';
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return $rows ?: [];
    }

    private static function range_to_cutoff_date( string $range ): ?string {
        $range = strtolower( trim( $range ) );
        $now = current_time( 'timestamp' );
        $map = [
            '6m' => '-6 months', '1y' => '-1 year', '2y' => '-2 years', '3y' => '-3 years',
            '5y' => '-5 years', '10y' => '-10 years', '15y' => '-15 years', '20y' => '-20 years',
            '25y' => '-25 years', 'all' => null,
        ];
        if ( ! array_key_exists( $range, $map ) ) { return null; }
        if ( $map[$range] === null ) { return null; }
        $ts = strtotime( $map[$range], $now );
        return gmdate( 'Y-m-d', $ts );
    }
}


