<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCI_REST {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    
    public function register_routes() {
        register_rest_route( 'zci/v1', '/chart', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_chart_data' ],
            'permission_callback' => '__return_true',
        ] );
        
        register_rest_route( 'zci/v1', '/search-indicators', [
            'methods' => 'GET',
            'callback' => [ $this, 'search_indicators' ],
            'permission_callback' => '__return_true',
        ] );
    }
    
    public function get_chart_data( $request ) {
        global $wpdb;
        $indicators = sanitize_text_field( $request->get_param( 'indicators' ) );
        
        if ( empty( $indicators ) ) {
            return new WP_REST_Response( [ 'labels' => [], 'datasets' => [] ], 200 );
        }
        
        $indicator_slugs = array_filter( array_map( 'trim', explode( ',', $indicators ) ) );
        $labels = [];
        $datasets = [];
        
        $table = $wpdb->prefix . 'zci_series';
        
        foreach ( $indicator_slugs as $slug ) {
            $sql = $wpdb->prepare(
                "SELECT obs_date, value FROM {$table} WHERE indicator_slug = %s ORDER BY obs_date ASC", 
                $slug
            );
            $rows = $wpdb->get_results( $sql, ARRAY_A );
            
            if ( $rows ) {
                $data = [];
                foreach ( $rows as $row ) {
                    $labels[ $row['obs_date'] ] = true;
                    $data[] = $row['value'] ? (float) $row['value'] : null;
                }
                $datasets[] = [
                    'label' => $slug,
                    'data' => $data
                ];
            }
        }
        
        $labels = array_keys( $labels );
        sort( $labels );
        
        return new WP_REST_Response( [
            'labels' => $labels,
            'datasets' => $datasets,
            'last_updated' => current_time( 'mysql' )
        ], 200 );
    }
    
    public function search_indicators( $request ) {
        global $wpdb;
        $q = '%' . $wpdb->esc_like( sanitize_text_field( $request->get_param( 'q' ) ) ) . '%';
        $table = $wpdb->prefix . 'zci_indicators';
        
        $sql = $wpdb->prepare(
            "SELECT slug, display_name FROM {$table} WHERE display_name LIKE %s OR slug LIKE %s ORDER BY updated_at DESC LIMIT 20", 
            $q, $q 
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A ) ?: [];
        
        return new WP_REST_Response( [ 'items' => $rows ], 200 );
    }
}