<?php
/**
 * Plugin Name: Zestra Capital Economic Insights (Minimal)
 * Plugin URI: https://client.zestracapital.com
 * Description: Simple economic data comparison tool - no admin conflicts.
 * Version: 1.0.0
 * Author: Zestra Capital
 * Author URI: https://zestracapital.com
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// NO EXTERNAL CLASS LOADING - EVERYTHING INLINE
class ZCI_Minimal_Plugin {
    
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_shortcode( 'zci_compare', [ $this, 'shortcode_compare' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }
    
    public function register_rest_routes() {
        register_rest_route( 'zci/v1', '/search-indicators', [
            'methods'  => 'GET',
            'callback' => [ $this, 'search_indicators' ],
            'permission_callback' => '__return_true',
        ] );
        
        register_rest_route( 'zci/v1', '/chart', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_chart_data' ],
            'permission_callback' => '__return_true',
        ] );
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
    
    public function get_chart_data( $request ) {
        global $wpdb;
        $indicators = sanitize_text_field( $request->get_param( 'indicators' ) );
        $range = sanitize_text_field( $request->get_param( 'range' ) ) ?: '1y';
        
        if ( empty( $indicators ) ) {
            return new WP_REST_Response( [ 'labels' => [], 'datasets' => [] ], 200 );
        }
        
        $indicator_slugs = array_filter( array_map( 'trim', explode( ',', $indicators ) ) );
        $labels = [];
        $datasets = [];
        
        foreach ( $indicator_slugs as $slug ) {
            $table = $wpdb->prefix . 'zci_series';
            $where = [ $wpdb->prepare( 'indicator_slug = %s', $slug ) ];
            
            // Apply date range filter
            if ( $range && $range !== 'all' ) {
                $cutoff = $this->range_to_date( $range );
                if ( $cutoff ) {
                    $where[] = $wpdb->prepare( 'obs_date >= %s', $cutoff );
                }
            }
            
            $sql = 'SELECT obs_date, value FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY obs_date ASC';
            $rows = $wpdb->get_results( $sql, ARRAY_A );
            
            if ( $rows ) {
                $series_data = [];
                foreach ( $rows as $row ) {
                    $labels[$row['obs_date']] = true;
                    $series_data[] = $row['value'] ? (float) $row['value'] : null;
                }
                $datasets[] = [
                    'label' => $slug,
                    'data' => $series_data
                ];
            }
        }
        
        $labels = array_keys( $labels );
        sort( $labels );
        
        // Align all datasets to same labels
        foreach ( $datasets as &$ds ) {
            $aligned = [];
            $label_map = array_flip( $labels );
            foreach ( $labels as $label ) {
                $idx = isset( $ds['data_map'][$label] ) ? array_search( $label, array_keys( $ds['data_map'] ) ) : null;
                $aligned[] = $idx !== null ? $ds['data'][$idx] : null;
            }
            $ds['data'] = $aligned;
        }
        
        return new WP_REST_Response( [
            'labels' => $labels,
            'datasets' => $datasets,
            'last_updated' => current_time( 'mysql' )
        ], 200 );
    }
    
    private function range_to_date( $range ) {
        $now = current_time( 'timestamp' );
        $map = [
            '6m' => '-6 months',
            '1y' => '-1 year', 
            '2y' => '-2 years',
            '3y' => '-3 years',
            '5y' => '-5 years',
            '10y' => '-10 years',
            '15y' => '-15 years',
            '20y' => '-20 years',
            '25y' => '-25 years'
        ];
        if ( ! isset( $map[$range] ) ) return null;
        return gmdate( 'Y-m-d', strtotime( $map[$range], $now ) );
    }
    
    public function enqueue_assets() {
        // Chart.js CDN
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true );
        
        // Inline CSS
        wp_add_inline_style( 'wp-block-library', '
            .zci-compare-builder{border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fff;margin:20px 0}
            .zci-chart-title{font-weight:700;margin:0 0 8px 0;font-size:18px}
            .zci-compare-controls{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
            .zci-compare-search{flex:1;min-width:240px;padding:8px;border:1px solid #e5e7eb;border-radius:8px}
            .zci-compare-results{list-style:none;margin:6px 0 0;padding:0;max-height:200px;overflow-y:auto}
            .zci-compare-results li{padding:6px 8px;border:1px solid #e5e7eb;margin-bottom:2px;cursor:pointer;background:#f9fafb}
            .zci-compare-results li:hover{background:#f3f4f6}
            .zci-compare-selected{margin-top:6px;color:#111827}
            .zci-compare-selected span{margin-right:8px;cursor:pointer;color:#ef4444}
            .zci-chart-updated{margin-top:8px;color:#6b7280;font-size:12px}
        ' );
        
        // Inline JavaScript
        wp_add_inline_script( 'chartjs', '
            function initZCICompare(){
                var builders = document.querySelectorAll(".zci-compare-builder");
                builders.forEach(function(root){
                    var search = root.querySelector(".zci-compare-search");
                    var results = root.querySelector(".zci-compare-results");
                    var selected = root.querySelector(".zci-compare-selected");
                    var canvas = root.querySelector("canvas");
                    var type = root.getAttribute("data-type") || "line";
                    var range = root.getAttribute("data-range") || "1y";
                    
                    var chosen = [];
                    var timer = null;
                    
                    search.addEventListener("input", function(){
                        var q = search.value.trim();
                        if(q.length < 2){ results.innerHTML=""; return; }
                        clearTimeout(timer);
                        timer = setTimeout(function(){
                            var url = "' . esc_js( rest_url( 'zci/v1/search-indicators' ) ) . '?q=" + encodeURIComponent(q);
                            fetch(url)
                                .then(function(r){ return r.json(); })
                                .then(function(j){
                                    results.innerHTML = "";
                                    if(j.items && j.items.length > 0){
                                        j.items.forEach(function(it){
                                            var li = document.createElement("li");
                                            li.textContent = it.display_name + " ("+ it.slug +")";
                                            li.onclick = function(){
                                                if(chosen.indexOf(it.slug)===-1){
                                                    chosen.push(it.slug);
                                                    renderSelected();
                                                    fetchAndRender();
                                                }
                                                results.innerHTML = "";
                                                search.value = "";
                                            };
                                            results.appendChild(li);
                                        });
                                    } else {
                                        results.innerHTML = "<li>No indicators found</li>";
                                    }
                                });
                        }, 250);
                    });
                    
                    function renderSelected(){
                        selected.innerHTML = "";
                        chosen.forEach(function(slug, i){
                            var tag = document.createElement("span");
                            tag.textContent = slug + " ✕";
                            tag.onclick = function(){
                                chosen.splice(i,1);
                                renderSelected();
                                fetchAndRender();
                            };
                            selected.appendChild(tag);
                        });
                    }
                    
                    function fetchAndRender(){
                        if(chosen.length === 0) return;
                        var url = "' . esc_js( rest_url( 'zci/v1/chart' ) ) . '?indicators=" + encodeURIComponent(chosen.join(",")) + "&range=" + encodeURIComponent(range);
                        fetch(url)
                            .then(function(r){ return r.json(); })
                            .then(function(json){
                                if(!json.labels) return;
                                var ctx = canvas.getContext("2d");
                                if(root._chart) root._chart.destroy();
                                root._chart = new Chart(ctx, {
                                    type: type,
                                    data: {
                                        labels: json.labels,
                                        datasets: json.datasets.map(function(ds, i){
                                            var colors = ["#3b82f6", "#ef4444", "#10b981", "#f59e0b", "#8b5cf6"];
                                            return {
                                                label: ds.label,
                                                data: ds.data,
                                                borderColor: colors[i % colors.length],
                                                backgroundColor: colors[i % colors.length] + "20",
                                                tension: 0.2
                                            };
                                        })
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: { x: { grid: { display: false } }, y: { grid: { display: false } } }
                                    }
                                });
                            });
                    }
                });
            }
            document.addEventListener("DOMContentLoaded", initZCICompare);
        ' );
    }
    
    public function shortcode_compare( $atts ) {
        $atts = shortcode_atts([
            'title' => 'Compare Economic Indicators',
            'range' => '1y',
            'type'  => 'line',
            'height' => '500px'
        ], $atts, 'zci_compare');
        
        $id = 'zci-compare-' . wp_generate_uuid4();
        return '
            <div class="zci-compare-builder" id="' . esc_attr( $id ) . '" data-range="' . esc_attr( $atts['range'] ) . '" data-type="' . esc_attr( $atts['type'] ) . '">
                <div class="zci-chart-title">' . esc_html( $atts['title'] ) . '</div>
                <div class="zci-compare-controls">
                    <input type="text" class="zci-compare-search" placeholder="Search indicators..." />
                    <ul class="zci-compare-results"></ul>
                    <div class="zci-compare-selected"></div>
                </div>
                <div style="height:' . esc_attr( $atts['height'] ) . ';"><canvas></canvas></div>
                <div class="zci-chart-updated">Last updated: ' . current_time( 'mysql' ) . '</div>
            </div>
        ';
    }
}

// Initialize the plugin
new ZCI_Minimal_Plugin();

// Add sample data if no indicators exist
add_action( 'init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'zci_indicators';
    
    // Check if table exists and has data
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count == 0 ) {
            // Add sample indicators
            $indicators = [
                ['slug' => 'cpi_us', 'display_name' => 'Consumer Price Index (US)'],
                ['slug' => 'gdp_us', 'display_name' => 'GDP (US)'],
                ['slug' => 'unemployment_us', 'display_name' => 'Unemployment Rate (US)'],
                ['slug' => 'fed_funds_rate', 'display_name' => 'Federal Funds Rate'],
            ];
            
            foreach ( $indicators as $ind ) {
                $wpdb->insert( $table, [
                    'slug' => $ind['slug'],
                    'display_name' => $ind['display_name'],
                    'source_id' => 1,
                    'is_active' => 1,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' )
                ] );
            }
        }
    }
});