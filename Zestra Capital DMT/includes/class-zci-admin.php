<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCI_Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function enqueue_admin_assets( $hook = '' ): void {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( (string) $_GET['page'] ) : '';
        if ( $page === 'zci-derived' ) {
            wp_enqueue_script( 'zci-admin', ZCI_PLUGIN_URL . 'admin/js/zci-admin.js', [ 'wp-api-fetch' ], ZCI_VERSION, true );
            wp_localize_script( 'zci-admin', 'zciAdmin', [
                'restUrl' => untrailingslashit( esc_url_raw( rest_url( 'zci/v1' ) ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ] );
        }
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Zestra Insights', 'zc-economic-insights' ),
            __( 'Zestra Insights', 'zc-economic-insights' ),
            'manage_options',
            'zci-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-chart-line',
            56
        );

        add_submenu_page(
            'zci-dashboard',
            __( 'Data Sources', 'zc-economic-insights' ),
            __( 'Data Sources', 'zc-economic-insights' ),
            'manage_options',
            'zci-sources',
            [ $this, 'render_sources' ]
        );

        add_submenu_page(
            'zci-dashboard',
            __( 'Indicators', 'zc-economic-insights' ),
            __( 'Indicators', 'zc-economic-insights' ),
            'manage_options',
            'zci-indicators',
            [ $this, 'render_indicators' ]
        );
        add_submenu_page(
            'zci-dashboard',
            __( 'Derived', 'zc-economic-insights' ),
            __( 'Derived', 'zc-economic-insights' ),
            'manage_options',
            'zci-derived',
            [ $this, 'render_derived' ]
        );

        add_submenu_page(
            'zci-dashboard',
            __( 'Settings', 'zc-economic-insights' ),
            __( 'Settings', 'zc-economic-insights' ),
            'manage_options',
            'zci-settings',
            [ $this, 'render_settings' ]
        );

        add_submenu_page(
            'zci-dashboard',
            __( 'Manual Import', 'zc-economic-insights' ),
            __( 'Manual Import', 'zc-economic-insights' ),
            'manage_options',
            'zci-import',
            [ $this, 'render_import' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'zci_settings', 'zci_settings' );
        add_settings_section( 'zci_settings_section', __( 'Chart Appearance', 'zc-economic-insights' ), function(){
            echo '<p>Control default chart appearance. These apply unless overridden by shortcode options in future versions.</p>';
        }, 'zci_settings' );

        add_settings_field( 'legend_position', 'Legend Position', function(){
            $opts = (array) get_option( 'zci_settings', [] );
            $val = $opts['legend_position'] ?? 'top';
            echo '<select name="zci_settings[legend_position]">';
            foreach ( [ 'top', 'bottom', 'left', 'right' ] as $p ) {
                echo '<option value="' . esc_attr( $p ) . '"' . selected( $val, $p, false ) . '>' . esc_html( ucfirst( $p ) ) . '</option>';
            }
            echo '</select>';
        }, 'zci_settings', 'zci_settings_section' );

        add_settings_field( 'line_tension', 'Line Smoothing (0-0.5)', function(){
            $opts = (array) get_option( 'zci_settings', [] );
            $val = isset( $opts['line_tension'] ) ? (float) $opts['line_tension'] : 0.2;
            echo '<input type="number" step="0.05" min="0" max="0.5" name="zci_settings[line_tension]" value="' . esc_attr( $val ) . '" />';
        }, 'zci_settings', 'zci_settings_section' );

        add_settings_field( 'line_width', 'Line Width (1-4)', function(){
            $opts = (array) get_option( 'zci_settings', [] );
            $val = isset( $opts['line_width'] ) ? (int) $opts['line_width'] : 2;
            echo '<input type="number" min="1" max="4" name="zci_settings[line_width]" value="' . esc_attr( $val ) . '" />';
        }, 'zci_settings', 'zci_settings_section' );

        add_settings_field( 'show_grid', 'Show Grid Lines', function(){
            $opts = (array) get_option( 'zci_settings', [] );
            $val = ! empty( $opts['show_grid'] );
            echo '<label><input type="checkbox" name="zci_settings[show_grid]" value="1"' . checked( $val, true, false ) . '> Enable</label>';
        }, 'zci_settings', 'zci_settings_section' );

        add_settings_field( 'show_last_updated', 'Show Last Updated', function(){
            $opts = (array) get_option( 'zci_settings', [] );
            $val = ! empty( $opts['show_last_updated'] );
            echo '<label><input type="checkbox" name="zci_settings[show_last_updated]" value="1"' . checked( $val, true, false ) . '> Enable</label>';
        }, 'zci_settings', 'zci_settings_section' );
    }

    public function render_dashboard(): void {
        echo '<div class="wrap"><h1>Zestra Capital Economic Insights</h1>';
        echo '<p>Welcome. Use the quick links below to get started.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;max-width:1100px">';
        echo '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;"><h2 style="margin:0 0 8px 0;font-size:16px;">Data Sources</h2><p style="margin:0 0 10px 0;">Connect APIs (FRED/BLS) or import data.</p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=zci-sources' ) ) . '">Open Data Sources</a></div>';
        echo '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;"><h2 style="margin:0 0 8px 0;font-size:16px;">Indicators</h2><p style="margin:0 0 10px 0;">Manage indicators and copy shortcodes.</p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=zci-indicators' ) ) . '">Open Indicators</a></div>';
        echo '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;"><h2 style="margin:0 0 8px 0;font-size:16px;">Manual Import</h2><p style="margin:0 0 10px 0;">Import CSV or Google Sheets.</p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=zci-import' ) ) . '">Open Import</a></div>';
        echo '</div></div>';
    }

    public function render_sources(): void {
        if ( isset( $_POST['zci_fred_save'] ) && check_admin_referer( 'zci_fred_nonce', 'zci_fred_nonce' ) ) {
            $api_key = sanitize_text_field( $_POST['zci_fred_api_key'] ?? '' );
            if ( $api_key ) {
                \ZCI\ZCI_Repository::upsert_source([
                    'source_key'  => 'fred',
                    'source_type' => 'api',
                    'name'        => 'FRED',
                    'credentials' => wp_json_encode( [ 'api_key' => $api_key ] ),
                ]);
                echo '<div class="updated"><p>FRED API key saved.</p></div>';
            }
        }

        if ( isset( $_POST['zci_fred_delete'] ) && check_admin_referer( 'zci_fred_del_nonce', 'zci_fred_del_nonce' ) ) {
            global $wpdb; $t = $wpdb->prefix . 'zci_sources';
            $wpdb->update( $t, [ 'credentials' => null, 'updated_at' => current_time( 'mysql' ) ], [ 'source_key' => 'fred' ] );
            echo '<div class="updated"><p>FRED API key deleted.</p></div>';
        }

        if ( isset( $_POST['zci_fred_add_series'] ) && check_admin_referer( 'zci_fred_series_nonce', 'zci_fred_series_nonce' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'zci_sources';
            $api_key = $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='fred' LIMIT 1" );
            $api_key = $api_key ? ( json_decode( $api_key, true )['api_key'] ?? '' ) : '';
            $series_id = sanitize_text_field( $_POST['zci_fred_series_id'] ?? '' );
            $slug = sanitize_title( $_POST['zci_indicator_slug'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name'] ?? '' );
            if ( $api_key && $series_id && $slug && $name ) {
                $source_id = \ZCI\ZCI_Source_FRED::get_or_create_source( $api_key );
                $points = \ZCI\ZCI_Source_FRED::fetch_series( $api_key, $series_id );
                if ( $points ) {
                    $indicator_id = \ZCI\ZCI_Repository::upsert_indicator([
                        'slug'         => $slug,
                        'display_name' => $name,
                        'source_id'    => $source_id,
                        'external_code'=> $series_id,
                        'frequency'    => 'm',
                        'units'        => '',
                    ]);
                    \ZCI\ZCI_Repository::store_series_points( $slug, null, $source_id, $points );
                    echo '<div class="updated"><p>Indicator saved and data fetched: ' . esc_html( $name ) . ' (' . esc_html( $series_id ) . ')</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to fetch series from FRED.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill API key, series ID, indicator slug and name.</p></div>';
            }
        }

        echo '<div class="wrap"><h1>Data Sources</h1>';
        // Load existing keys for status badges
        global $wpdb; $src_table = $wpdb->prefix . 'zci_sources';
        $fred_row = $wpdb->get_row( "SELECT credentials FROM {$src_table} WHERE source_key='fred' LIMIT 1", ARRAY_A );
        $fred_key_present = $fred_row && ! empty( json_decode( (string) $fred_row['credentials'], true )['api_key'] );
        $bls_row = $wpdb->get_row( "SELECT credentials FROM {$src_table} WHERE source_key='bls' LIMIT 1", ARRAY_A );
        $bls_key_present = $bls_row && ! empty( json_decode( (string) $bls_row['credentials'], true )['api_key'] );

        echo '<h2>FRED ' . ( $fred_key_present ? '<span style="color:#059669;">(Saved)</span>' : '<span style="color:#b91c1c;">(Not set)</span>' ) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_fred_nonce', 'zci_fred_nonce' );
        echo '<table class="form-table"><tr><th>FRED API Key</th><td><input type="password" name="zci_fred_api_key" class="regular-text" placeholder="' . ( $fred_key_present ? '********' : 'Enter your FRED API key' ) . '"></td></tr></table>';
        submit_button( 'Save FRED API Key', 'primary', 'zci_fred_save' );
        echo '</form>';
        echo '<form method="post" style="margin-top:6px;">';
        wp_nonce_field( 'zci_fred_del_nonce', 'zci_fred_del_nonce' );
        submit_button( 'Delete FRED API Key', 'delete', 'zci_fred_delete', false );
        echo '</form>';

        echo '<h3>Add FRED Series</h3>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_fred_series_nonce', 'zci_fred_series_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Series ID</th><td><input type="text" name="zci_fred_series_id" class="regular-text" placeholder="e.g., CPIAUCSL"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug" class="regular-text" placeholder="e.g., cpi_us"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name" class="regular-text" placeholder="e.g., Consumer Price Index (US)"></td></tr>';
        echo '</table>';
        submit_button( 'Add & Fetch Series', 'secondary', 'zci_fred_add_series' );
        echo '</form>';

        echo '<hr /><h2>BLS (US Bureau of Labor Statistics) ' . ( $bls_key_present ? '<span style="color:#059669;">(Saved)</span>' : '<span style="color:#b91c1c;">(Not set)</span>' ) . '</h2>';
        if ( isset( $_POST['zci_bls_save'] ) && check_admin_referer( 'zci_bls_nonce', 'zci_bls_nonce' ) ) {
            $api_key = sanitize_text_field( $_POST['zci_bls_api_key'] ?? '' );
            if ( $api_key ) {
                \ZCI\ZCI_Repository::upsert_source([
                    'source_key'  => 'bls',
                    'source_type' => 'api',
                    'name'        => 'US BLS',
                    'credentials' => wp_json_encode( [ 'api_key' => $api_key ] ),
                ]);
                echo '<div class="updated"><p>BLS API key saved.</p></div>';
            }
        }

        if ( isset( $_POST['zci_bls_delete'] ) && check_admin_referer( 'zci_bls_del_nonce', 'zci_bls_del_nonce' ) ) {
            global $wpdb; $t = $wpdb->prefix . 'zci_sources';
            $wpdb->update( $t, [ 'credentials' => null, 'updated_at' => current_time( 'mysql' ) ], [ 'source_key' => 'bls' ] );
            echo '<div class="updated"><p>BLS API key deleted.</p></div>';
        }

        if ( isset( $_POST['zci_bls_add_series'] ) && check_admin_referer( 'zci_bls_series_nonce', 'zci_bls_series_nonce' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'zci_sources';
            $api_key = $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='bls' LIMIT 1" );
            $api_key = $api_key ? ( json_decode( $api_key, true )['api_key'] ?? '' ) : '';
            $series_id = sanitize_text_field( $_POST['zci_bls_series_id'] ?? '' );
            $slug = sanitize_title( $_POST['zci_indicator_slug_bls'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name_bls'] ?? '' );
            if ( $api_key && $series_id && $slug && $name ) {
                $source_id = \ZCI\ZCI_Source_BLS::get_or_create_source( $api_key );
                $points = \ZCI\ZCI_Source_BLS::fetch_series( $api_key, $series_id );
                if ( $points ) {
                    \ZCI\ZCI_Repository::upsert_indicator([
                        'slug'         => $slug,
                        'display_name' => $name,
                        'source_id'    => $source_id,
                        'external_code'=> $series_id,
                    ]);
                    \ZCI\ZCI_Repository::store_series_points( $slug, 'US', $source_id, $points );
                    echo '<div class="updated"><p>BLS series imported: ' . esc_html( $series_id ) . '</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to fetch BLS series.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill API key, series ID, indicator slug and name.</p></div>';
            }
        }

        echo '<form method="post">';
        wp_nonce_field( 'zci_bls_nonce', 'zci_bls_nonce' );
        echo '<table class="form-table"><tr><th>BLS API Key</th><td><input type="password" name="zci_bls_api_key" class="regular-text" placeholder="' . ( $bls_key_present ? '********' : 'Enter your BLS API key' ) . '"></td></tr></table>';
        submit_button( 'Save BLS API Key', 'primary', 'zci_bls_save' );
        echo '</form>';
        echo '<form method="post" style="margin-top:6px;">';
        wp_nonce_field( 'zci_bls_del_nonce', 'zci_bls_del_nonce' );
        submit_button( 'Delete BLS API Key', 'delete', 'zci_bls_delete', false );
        echo '</form>';

        echo '<h3>Add BLS Series</h3>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_bls_series_nonce', 'zci_bls_series_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Series ID</th><td><input type="text" name="zci_bls_series_id" class="regular-text" placeholder="e.g., CUSR0000SA0 (CPI All Urban) or CES0000000001"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug_bls" class="regular-text" placeholder="e.g., cpi_us_bls"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name_bls" class="regular-text" placeholder="e.g., CPI (BLS)"></td></tr>';
        echo '</table>';
        submit_button( 'Add & Fetch BLS Series', 'secondary', 'zci_bls_add_series' );
        echo '</form>';
        echo '</div>';
    }

    public function render_indicators(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zci_indicators';

        if ( isset( $_POST['zci_toggle_indicator'] ) && check_admin_referer( 'zci_toggle_indicator', 'zci_toggle_indicator' ) ) {
            $slug = sanitize_title( $_POST['slug'] ?? '' );
            $active = (int) ( $_POST['active'] ?? 1 );
            $wpdb->update( $table, [ 'is_active' => $active, 'updated_at' => current_time( 'mysql' ) ], [ 'slug' => $slug ] );
        }
        if ( isset( $_POST['zci_delete_indicator'] ) && check_admin_referer( 'zci_delete_indicator', 'zci_delete_indicator' ) ) {
            $slug = sanitize_title( $_POST['slug'] ?? '' );
            $wpdb->delete( $table, [ 'slug' => $slug ] );
        }

        $rows = $wpdb->get_results( "SELECT slug, display_name, is_active, updated_at FROM {$table} ORDER BY updated_at DESC", ARRAY_A );

        echo '<div class="wrap"><h1>Indicators</h1>';
        echo '<table class="widefat"><thead><tr><th>Slug</th><th>Name</th><th>Status</th><th>Shortcode</th><th>Actions</th></tr></thead><tbody>';
        if ( $rows ) {
            foreach ( $rows as $r ) {
                echo '<tr>';
                echo '<td>' . esc_html( $r['slug'] ) . '</td>';
                echo '<td>' . esc_html( $r['display_name'] ) . '</td>';
                echo '<td>' . ( $r['is_active'] ? 'Active' : 'Inactive' ) . '</td>';
                $sc = '[zci_chart indicator="' . esc_html( $r['slug'] ) . '" range="5y" type="line" title="' . esc_html( $r['display_name'] ) . '"]';
                echo '<td><code style="user-select:all;">' . $sc . '</code> <button type="button" class="button" onclick="navigator.clipboard.writeText(this.previousSibling.textContent);this.textContent=\'Copied\';setTimeout(()=>this.textContent=\'Copy\',1200);">Copy</button></td>';
                echo '<td>';
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field( 'zci_toggle_indicator', 'zci_toggle_indicator' );
                echo '<input type="hidden" name="slug" value="' . esc_attr( $r['slug'] ) . '">';
                echo '<input type="hidden" name="active" value="' . ( $r['is_active'] ? 0 : 1 ) . '">';
                submit_button( $r['is_active'] ? 'Deactivate' : 'Activate', 'small', 'zci_toggle_indicator', false );
                echo '</form> ';
                echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Delete indicator? This will not remove stored series.\');">';
                wp_nonce_field( 'zci_delete_indicator', 'zci_delete_indicator' );
                echo '<input type="hidden" name="slug" value="' . esc_attr( $r['slug'] ) . '">';
                submit_button( 'Delete', 'delete', 'zci_delete_indicator', false );
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">No indicators yet.</td></tr>';
        }
        echo '</tbody></table>';

        echo '</div>';
    }

    // duplicate render_sources removed

    public function render_derived(): void {
        if ( isset( $_POST['zci_add_transform'] ) && check_admin_referer( 'zci_add_transform', 'zci_add_transform' ) ) {
            $base = sanitize_title( $_POST['base_slug'] ?? '' );
            $out = sanitize_title( $_POST['out_slug'] ?? '' );
            $name = sanitize_text_field( $_POST['out_name'] ?? '' );
            $method = sanitize_text_field( $_POST['method'] ?? '' );
            if ( $base && $out && $name && in_array( $method, [ 'mom_pct', 'qoq_pct', 'yoy_pct' ], true ) ) {
                \ZCI\ZCI_Transforms::create_transform( $base, $out, $name, $method );
                $num = \ZCI\ZCI_Transforms::run_transform( $base, $out, $name, $method );
                echo '<div class="updated"><p>Derived series created with ' . (int) $num . ' points.</p></div>';
            } else {
                echo '<div class="error"><p>Please fill all fields and pick a valid method.</p></div>';
            }
        }

        echo '<div class="wrap"><h1>Derived Indicators</h1>';
        echo '<p>Create indicators from existing ones (MoM%, QoQ%, YoY%).</p>';
        echo '<form method="post" onsubmit="return true;">';
        wp_nonce_field( 'zci_add_transform', 'zci_add_transform' );
        echo '<table class="form-table">';
        echo '<tr><th>Base Indicator</th><td><input type="text" id="zci-base-search" class="regular-text" placeholder="Search indicators (type 2+ characters)"><p style="margin:6px 0 0 0;color:#6b7280;">Pick from search or enter slug manually below.</p><input type="text" name="base_slug" id="zci-base-slug" class="regular-text" placeholder="e.g., cpi_us"><ul id="zci-base-results" style="margin:6px 0 0;padding:0;list-style:none;"></ul></td></tr>';
        echo '<tr><th>Method</th><td><select name="method"><option value="mom_pct">Month-over-Month %</option><option value="qoq_pct">Quarter-over-Quarter %</option><option value="yoy_pct">Year-over-Year %</option></select></td></tr>';
        echo '<tr><th>Output Slug</th><td><input type="text" name="out_slug" class="regular-text" placeholder="e.g., cpi_us_yoy"></td></tr>';
        echo '<tr><th>Output Name</th><td><input type="text" name="out_name" class="regular-text" placeholder="e.g., CPI (US) YoY %"></td></tr>';
        echo '</table>';
        submit_button( 'Create Derived Indicator', 'primary', 'zci_add_transform' );
        echo '</form>';
        // Results are handled by admin JS (see admin/js/zci-admin.js)
        echo '</div>';
    }

    public function render_settings(): void {
        echo '<div class="wrap"><h1>Settings</h1><form method="post" action="options.php">';
        settings_fields( 'zci_settings' );
        do_settings_sections( 'zci_settings' );
        submit_button();
        echo '</form></div>';
    }

    public function render_import(): void {
        if ( isset( $_POST['zci_import_csv_url'] ) && check_admin_referer( 'zci_import_url_nonce', 'zci_import_url_nonce' ) ) {
            $url = esc_url_raw( $_POST['zci_csv_url'] ?? '' );
            $slug = sanitize_title( $_POST['zci_indicator_slug'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name'] ?? '' );
            $country = strtoupper( substr( sanitize_text_field( $_POST['zci_country_iso'] ?? '' ), 0, 3 ) );
            if ( $url && $slug && $name ) {
                $count = \ZCI\ZCI_Importer::import_csv_from_url( $url, $slug, $name, $country ?: null );
                if ( $count > 0 ) {
                    echo '<div class="updated"><p>Imported ' . (int) $count . ' rows from CSV URL.</p></div>';
                } else {
                    echo '<div class="error"><p>Import failed or no rows parsed.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill URL, indicator slug and name.</p></div>';
            }
        }

        if ( isset( $_POST['zci_import_gsheet_url'] ) && check_admin_referer( 'zci_import_gsheet_nonce', 'zci_import_gsheet_nonce' ) ) {
            $url = esc_url_raw( $_POST['zci_gsheet_url'] ?? '' );
            $slug = sanitize_title( $_POST['zci_indicator_slug_g'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name_g'] ?? '' );
            $country = strtoupper( substr( sanitize_text_field( $_POST['zci_country_iso_g'] ?? '' ), 0, 3 ) );
            if ( $url && $slug && $name ) {
                $count = \ZCI\ZCI_Importer::import_google_sheet_url( $url, $slug, $name, $country ?: null );
                if ( $count > 0 ) {
                    echo '<div class="updated"><p>Imported ' . (int) $count . ' rows from Google Sheets.</p></div>';
                } else {
                    echo '<div class="error"><p>Import failed or no rows parsed.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill Google Sheet URL, indicator slug and name.</p></div>';
            }
        }

        if ( isset( $_POST['zci_import_csv_upload'] ) && check_admin_referer( 'zci_import_upload_nonce', 'zci_import_upload_nonce' ) ) {
            $slug = sanitize_title( $_POST['zci_indicator_slug_u'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name_u'] ?? '' );
            $country = strtoupper( substr( sanitize_text_field( $_POST['zci_country_iso_u'] ?? '' ), 0, 3 ) );
            if ( ! empty( $_FILES['zci_csv_file']['tmp_name'] ) && $slug && $name ) {
                $file = $_FILES['zci_csv_file']['tmp_name'];
                $count = \ZCI\ZCI_Importer::import_csv_from_file( $file, $slug, $name, $country ?: null );
                if ( $count > 0 ) {
                    echo '<div class="updated"><p>Imported ' . (int) $count . ' rows from uploaded CSV.</p></div>';
                } else {
                    echo '<div class="error"><p>Import failed or no rows parsed.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please select a CSV file and fill indicator details.</p></div>';
            }
        }

        echo '<div class="wrap"><h1>Manual Import</h1>';
        echo '<h2>CSV via URL</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_import_url_nonce', 'zci_import_url_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>CSV URL</th><td><input type="url" name="zci_csv_url" class="regular-text" placeholder="https://example.com/data.csv"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug" class="regular-text" placeholder="e.g., cpi_uk"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name" class="regular-text" placeholder="e.g., Consumer Price Index (UK)"></td></tr>';
        echo '<tr><th>Country ISO (optional)</th><td><input type="text" name="zci_country_iso" class="regular-text" placeholder="e.g., UK"></td></tr>';
        echo '</table>';
        submit_button( 'Import from URL', 'primary', 'zci_import_csv_url' );
        echo '</form>';

        echo '<h2>Google Sheets (Published Link)</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_import_gsheet_nonce', 'zci_import_gsheet_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Google Sheet URL</th><td><input type="url" name="zci_gsheet_url" class="regular-text" placeholder="https://docs.google.com/spreadsheets/d/.../edit"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug_g" class="regular-text" placeholder="e.g., cpi_jp"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name_g" class="regular-text" placeholder="e.g., Consumer Price Index (Japan)"></td></tr>';
        echo '<tr><th>Country ISO (optional)</th><td><input type="text" name="zci_country_iso_g" class="regular-text" placeholder="e.g., JP"></td></tr>';
        echo '</table>';
        submit_button( 'Import from Google Sheets', 'primary', 'zci_import_gsheet_url' );
        echo '</form>';

        echo '<h2>CSV Upload</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( 'zci_import_upload_nonce', 'zci_import_upload_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>CSV File</th><td><input type="file" name="zci_csv_file" accept=".csv"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug_u" class="regular-text" placeholder="e.g., cpi_eu"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name_u" class="regular-text" placeholder="e.g., Consumer Price Index (EU)"></td></tr>';
        echo '<tr><th>Country ISO (optional)</th><td><input type="text" name="zci_country_iso_u" class="regular-text" placeholder="e.g., EU"></td></tr>';
        echo '</table>';
        submit_button( 'Import from Upload', 'secondary', 'zci_import_csv_upload' );
        echo '</form>';

        echo '<p>Tip: Your CSV should have headers: Date, Value, and optionally Country.</p>';
        echo '</div>';
    }

    public function render_dashboard(): void {
        echo '<div class="wrap"><h1>Zestra Capital Economic Insights</h1>';
        echo '<p>Welcome. Use the menu to configure sources, indicators, and settings.</p>';
        echo '</div>';
    }

    public function render_sources(): void {
        // Handle form submissions
        if ( isset( $_POST['zci_fred_save'] ) && check_admin_referer( 'zci_fred_nonce', 'zci_fred_nonce' ) ) {
            $api_key = sanitize_text_field( $_POST['zci_fred_api_key'] ?? '' );
            if ( $api_key ) {
                \ZCI\ZCI_Repository::upsert_source([
                    'source_key'  => 'fred',
                    'source_type' => 'api',
                    'name'        => 'FRED',
                    'credentials' => wp_json_encode( [ 'api_key' => $api_key ] ),
                ]);
                echo '<div class="updated"><p>FRED API key saved.</p></div>';
            }
        }

        if ( isset( $_POST['zci_fred_delete'] ) && check_admin_referer( 'zci_fred_del_nonce', 'zci_fred_del_nonce' ) ) {
            global $wpdb; $t = $wpdb->prefix . 'zci_sources';
            $wpdb->update( $t, [ 'credentials' => null, 'updated_at' => current_time( 'mysql' ) ], [ 'source_key' => 'fred' ] );
            echo '<div class="updated"><p>FRED API key deleted.</p></div>';
        }

        if ( isset( $_POST['zci_fred_add_series'] ) && check_admin_referer( 'zci_fred_series_nonce', 'zci_fred_series_nonce' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'zci_sources';
            $api_key = $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='fred' LIMIT 1" );
            $api_key = $api_key ? ( json_decode( $api_key, true )['api_key'] ?? '' ) : '';
            $series_id = sanitize_text_field( $_POST['zci_fred_series_id'] ?? '' );
            $slug = sanitize_title( $_POST['zci_indicator_slug'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name'] ?? '' );
            if ( $api_key && $series_id && $slug && $name ) {
                $source_id = \ZCI\ZCI_Source_FRED::get_or_create_source( $api_key );
                $points = \ZCI\ZCI_Source_FRED::fetch_series( $api_key, $series_id );
                if ( $points ) {
                    \ZCI\ZCI_Repository::upsert_indicator([
                        'slug'         => $slug,
                        'display_name' => $name,
                        'source_id'    => $source_id,
                        'external_code'=> $series_id,
                    ]);
                    \ZCI\ZCI_Repository::upsert_series( $slug, $points );
                    echo '<div class="updated"><p>Added ' . esc_html( $name ) . ' (' . esc_html( $slug ) . ') with ' . count( $points ) . ' data points.</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to fetch data for series ' . esc_html( $series_id ) . '.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill all fields.</p></div>';
            }
        }

        // Check if keys are present
        global $wpdb;
        $table = $wpdb->prefix . 'zci_sources';
        $fred_key_present = (bool) $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='fred' AND credentials IS NOT NULL LIMIT 1" );
        $bls_key_present = (bool) $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='bls' AND credentials IS NOT NULL LIMIT 1" );
        $wb_key_present = (bool) $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='worldbank' AND credentials IS NOT NULL LIMIT 1" );

        echo '<div class="wrap"><h1>Data Sources</h1>';

        echo '<h2>FRED (Federal Reserve Economic Data) ' . ( $fred_key_present ? '<span style="color:#059669;">(Saved)</span>' : '<span style="color:#b91c1c;">(Not set)</span>' ) . '</h2>';
        if ( isset( $_POST['zci_fred_save'] ) && check_admin_referer( 'zci_fred_nonce', 'zci_fred_nonce' ) ) {
            $api_key = sanitize_text_field( $_POST['zci_fred_api_key'] ?? '' );
            if ( $api_key ) {
                \ZCI\ZCI_Repository::upsert_source([
                    'source_key'  => 'fred',
                    'source_type' => 'api',
                    'name'        => 'FRED',
                    'credentials' => wp_json_encode( [ 'api_key' => $api_key ] ),
                ]);
                echo '<div class="updated"><p>FRED API key saved.</p></div>';
            }
        }

        if ( isset( $_POST['zci_fred_delete'] ) && check_admin_referer( 'zci_fred_del_nonce', 'zci_fred_del_nonce' ) ) {
            global $wpdb; $t = $wpdb->prefix . 'zci_sources';
            $wpdb->update( $t, [ 'credentials' => null, 'updated_at' => current_time( 'mysql' ) ], [ 'source_key' => 'fred' ] );
            echo '<div class="updated"><p>FRED API key deleted.</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field( 'zci_fred_nonce', 'zci_fred_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>API Key</th><td><input type="password" name="zci_fred_api_key" class="regular-text" placeholder="Enter FRED API key"></td></tr>';
        echo '</table>';
        submit_button( 'Save FRED Key', 'primary', 'zci_fred_save' );
        echo '</form>';

        if ( $fred_key_present ) {
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field( 'zci_fred_del_nonce', 'zci_fred_del_nonce' );
            submit_button( 'Delete FRED Key', 'secondary', 'zci_fred_delete' );
            echo '</form>';
        }

        echo '<h3>Add FRED Series</h3>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_fred_series_nonce', 'zci_fred_series_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Series ID</th><td><input type="text" name="zci_fred_series_id" class="regular-text" placeholder="e.g., CPIAUCSL"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug" class="regular-text" placeholder="e.g., cpi_us"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name" class="regular-text" placeholder="e.g., Consumer Price Index (US)"></td></tr>';
        echo '</table>';
        submit_button( 'Add & Fetch Series', 'secondary', 'zci_fred_add_series' );
        echo '</form>';

        echo '<hr /><h2>BLS (US Bureau of Labor Statistics) ' . ( $bls_key_present ? '<span style="color:#059669;">(Saved)</span>' : '<span style="color:#b91c1c;">(Not set)</span>' ) . '</h2>';
        if ( isset( $_POST['zci_bls_save'] ) && check_admin_referer( 'zci_bls_nonce', 'zci_bls_nonce' ) ) {
            $api_key = sanitize_text_field( $_POST['zci_bls_api_key'] ?? '' );
            if ( $api_key ) {
                \ZCI\ZCI_Repository::upsert_source([
                    'source_key'  => 'bls',
                    'source_type' => 'api',
                    'name'        => 'US BLS',
                    'credentials' => wp_json_encode( [ 'api_key' => $api_key ] ),
                ]);
                echo '<div class="updated"><p>BLS API key saved.</p></div>';
            }
        }

        if ( isset( $_POST['zci_bls_delete'] ) && check_admin_referer( 'zci_bls_del_nonce', 'zci_bls_del_nonce' ) ) {
            global $wpdb; $t = $wpdb->prefix . 'zci_sources';
            $wpdb->update( $t, [ 'credentials' => null, 'updated_at' => current_time( 'mysql' ) ], [ 'source_key' => 'bls' ] );
            echo '<div class="updated"><p>BLS API key deleted.</p></div>';
        }

        if ( isset( $_POST['zci_bls_add_series'] ) && check_admin_referer( 'zci_bls_series_nonce', 'zci_bls_series_nonce' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'zci_sources';
            $api_key = $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='bls' LIMIT 1" );
            $api_key = $api_key ? ( json_decode( $api_key, true )['api_key'] ?? '' ) : '';
            $series_id = sanitize_text_field( $_POST['zci_bls_series_id'] ?? '' );
            $slug = sanitize_title( $_POST['zci_indicator_slug_bls'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name_bls'] ?? '' );
            if ( $api_key && $series_id && $slug && $name ) {
                $source_id = \ZCI\ZCI_Source_BLS::get_or_create_source( $api_key );
                $points = \ZCI\ZCI_Source_BLS::fetch_series( $api_key, $series_id );
                if ( $points ) {
                    \ZCI\ZCI_Repository::upsert_indicator([
                        'slug'         => $slug,
                        'display_name' => $name,
                        'source_id'    => $source_id,
                        'external_code'=> $series_id,
                    ]);
                    \ZCI\ZCI_Repository::upsert_series( $slug, $points );
                    echo '<div class="updated"><p>Added ' . esc_html( $name ) . ' (' . esc_html( $slug ) . ') with ' . count( $points ) . ' data points.</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to fetch data for series ' . esc_html( $series_id ) . '.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill all fields.</p></div>';
            }
        }

        echo '<form method="post">';
        wp_nonce_field( 'zci_bls_nonce', 'zci_bls_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>API Key</th><td><input type="password" name="zci_bls_api_key" class="regular-text" placeholder="Enter BLS API key"></td></tr>';
        echo '</table>';
        submit_button( 'Save BLS Key', 'primary', 'zci_bls_save' );
        echo '</form>';

        if ( $bls_key_present ) {
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field( 'zci_bls_del_nonce', 'zci_bls_del_nonce' );
            submit_button( 'Delete BLS Key', 'secondary', 'zci_bls_delete' );
            echo '</form>';
        }

        echo '<h3>Add BLS Series</h3>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_bls_series_nonce', 'zci_bls_series_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Series ID</th><td><input type="text" name="zci_bls_series_id" class="regular-text" placeholder="e.g., CUUR0000SA0"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug_bls" class="regular-text" placeholder="e.g., cpi_us_bls"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name_bls" class="regular-text" placeholder="e.g., Consumer Price Index (US BLS)"></td></tr>';
        echo '</table>';
        submit_button( 'Add & Fetch Series', 'secondary', 'zci_bls_add_series' );
        echo '</form>';

        echo '<hr /><h2>World Bank ' . ( $wb_key_present ? '<span style="color:#059669;">(Saved)</span>' : '<span style="color:#b91c1c;">(Not set)</span>' ) . '</h2>';
        if ( isset( $_POST['zci_wb_save'] ) && check_admin_referer( 'zci_wb_nonce', 'zci_wb_nonce' ) ) {
            $api_key = sanitize_text_field( $_POST['zci_wb_api_key'] ?? '' );
            if ( $api_key ) {
                \ZCI\ZCI_Repository::upsert_source([
                    'source_key'  => 'worldbank',
                    'source_type' => 'api',
                    'name'        => 'World Bank',
                    'credentials' => wp_json_encode( [ 'api_key' => $api_key ] ),
                ]);
                echo '<div class="updated"><p>World Bank API key saved.</p></div>';
            }
        }

        if ( isset( $_POST['zci_wb_delete'] ) && check_admin_referer( 'zci_wb_del_nonce', 'zci_wb_del_nonce' ) ) {
            global $wpdb; $t = $wpdb->prefix . 'zci_sources';
            $wpdb->update( $t, [ 'credentials' => null, 'updated_at' => current_time( 'mysql' ) ], [ 'source_key' => 'worldbank' ] );
            echo '<div class="updated"><p>World Bank API key deleted.</p></div>';
        }

        if ( isset( $_POST['zci_wb_add_series'] ) && check_admin_referer( 'zci_wb_series_nonce', 'zci_wb_series_nonce' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'zci_sources';
            $api_key = $wpdb->get_var( "SELECT credentials FROM {$table} WHERE source_key='worldbank' LIMIT 1" );
            $api_key = $api_key ? ( json_decode( $api_key, true )['api_key'] ?? '' ) : '';
            $series_id = sanitize_text_field( $_POST['zci_wb_series_id'] ?? '' );
            $slug = sanitize_title( $_POST['zci_indicator_slug_wb'] ?? '' );
            $name = sanitize_text_field( $_POST['zci_indicator_name_wb'] ?? '' );
            if ( $api_key && $series_id && $slug && $name ) {
                $source_id = \ZCI\ZCI_Source_WorldBank::get_or_create_source( $api_key );
                $points = \ZCI\ZCI_Source_WorldBank::fetch_series( $api_key, $series_id );
                if ( $points ) {
                    \ZCI\ZCI_Repository::upsert_indicator([
                        'slug'         => $slug,
                        'display_name' => $name,
                        'source_id'    => $source_id,
                        'external_code'=> $series_id,
                    ]);
                    \ZCI\ZCI_Repository::upsert_series( $slug, $points );
                    echo '<div class="updated"><p>Added ' . esc_html( $name ) . ' (' . esc_html( $slug ) . ') with ' . count( $points ) . ' data points.</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to fetch data for series ' . esc_html( $series_id ) . '.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill all fields.</p></div>';
            }
        }

        echo '<form method="post">';
        wp_nonce_field( 'zci_wb_nonce', 'zci_wb_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>API Key</th><td><input type="password" name="zci_wb_api_key" class="regular-text" placeholder="Enter World Bank API key"></td></tr>';
        echo '</table>';
        submit_button( 'Save World Bank Key', 'primary', 'zci_wb_save' );
        echo '</form>';

        if ( $wb_key_present ) {
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field( 'zci_wb_del_nonce', 'zci_wb_del_nonce' );
            submit_button( 'Delete World Bank Key', 'secondary', 'zci_wb_delete' );
            echo '</form>';
        }

        echo '<h3>Add World Bank Series</h3>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_wb_series_nonce', 'zci_wb_series_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Series ID</th><td><input type="text" name="zci_wb_series_id" class="regular-text" placeholder="e.g., NY.GDP.MKTP.CD"></td></tr>';
        echo '<tr><th>Indicator Slug</th><td><input type="text" name="zci_indicator_slug_wb" class="regular-text" placeholder="e.g., gdp_world"></td></tr>';
        echo '<tr><th>Indicator Name</th><td><input type="text" name="zci_indicator_name_wb" class="regular-text" placeholder="e.g., GDP (World)"></td></tr>';
        echo '</table>';
        submit_button( 'Add & Fetch Series', 'secondary', 'zci_wb_add_series' );
        echo '</form>';

        echo '</div>';
    }

    public function render_indicators(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'zci_indicators';
        $indicators = $wpdb->get_results( "SELECT slug, display_name, updated_at FROM {$table} ORDER BY updated_at DESC", ARRAY_A ) ?: [];

        echo '<div class="wrap"><h1>Indicators</h1>';
        if ( empty( $indicators ) ) {
            echo '<p>No indicators found. Add some from the Data Sources page.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Slug</th><th>Display Name</th><th>Shortcode</th><th>Updated</th></tr></thead>';
            echo '<tbody>';
            foreach ( $indicators as $ind ) {
                $shortcode = '[zci_chart indicator="' . esc_attr( $ind['slug'] ) . '"]';
                echo '<tr>';
                echo '<td>' . esc_html( $ind['slug'] ) . '</td>';
                echo '<td>' . esc_html( $ind['display_name'] ) . '</td>';
                echo '<td><input type="text" value="' . esc_attr( $shortcode ) . '" readonly class="regular-text" onclick="this.select()" /></td>';
                echo '<td>' . esc_html( $ind['updated_at'] ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    public function render_derived(): void {
        if ( isset( $_POST['zci_create_derived'] ) && check_admin_referer( 'zci_derived_nonce', 'zci_derived_nonce' ) ) {
            $base_slug = sanitize_title( $_POST['zci_base_slug'] ?? '' );
            $derived_slug = sanitize_title( $_POST['zci_derived_slug'] ?? '' );
            $derived_name = sanitize_text_field( $_POST['zci_derived_name'] ?? '' );
            $transform_type = sanitize_text_field( $_POST['zci_transform_type'] ?? '' );
            
            if ( $base_slug && $derived_slug && $derived_name && $transform_type ) {
                $result = \ZCI\ZCI_Transforms::create_transform( $base_slug, $derived_slug, $derived_name, $transform_type );
                if ( $result ) {
                    echo '<div class="updated"><p>Created derived indicator: ' . esc_html( $derived_name ) . ' (' . esc_html( $derived_slug ) . ')</p></div>';
                } else {
                    echo '<div class="error"><p>Failed to create derived indicator. Check base indicator exists.</p></div>';
                }
            } else {
                echo '<div class="error"><p>Please fill all fields.</p></div>';
            }
        }

        echo '<div class="wrap"><h1>Derived Indicators</h1>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_derived_nonce', 'zci_derived_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Base Indicator</th><td>';
        echo '<input type="text" id="zci-base-search" placeholder="Search indicators..." class="regular-text" />';
        echo '<input type="hidden" id="zci-base-slug" name="zci_base_slug" />';
        echo '<ul id="zci-base-results" style="border:1px solid #ddd;max-height:200px;overflow-y:auto;display:none;"></ul>';
        echo '</td></tr>';
        echo '<tr><th>Derived Slug</th><td><input type="text" name="zci_derived_slug" class="regular-text" placeholder="e.g., cpi_us_mom" /></td></tr>';
        echo '<tr><th>Derived Name</th><td><input type="text" name="zci_derived_name" class="regular-text" placeholder="e.g., CPI US (MoM%)" /></td></tr>';
        echo '<tr><th>Transform Type</th><td>';
        echo '<select name="zci_transform_type">';
        echo '<option value="mom">Month-over-Month %</option>';
        echo '<option value="qoq">Quarter-over-Quarter %</option>';
        echo '<option value="yoy">Year-over-Year %</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '</table>';
        submit_button( 'Create Derived Indicator', 'primary', 'zci_create_derived' );
        echo '</form>';
        echo '</div>';
    }

    public function render_settings(): void {
        if ( isset( $_POST['zci_save_settings'] ) && check_admin_referer( 'zci_settings_nonce', 'zci_settings_nonce' ) ) {
            $settings = [
                'legend_position' => sanitize_text_field( $_POST['zci_legend_position'] ?? 'top' ),
                'line_tension'    => (float) ( $_POST['zci_line_tension'] ?? 0.2 ),
                'line_width'      => (int) ( $_POST['zci_line_width'] ?? 2 ),
                'show_grid'       => isset( $_POST['zci_show_grid'] ) ? 1 : 0,
                'show_last_updated' => isset( $_POST['zci_show_last_updated'] ) ? 1 : 0,
            ];
            update_option( 'zci_settings', $settings );
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }

        $settings = get_option( 'zci_settings', [] );
        $legend_position = $settings['legend_position'] ?? 'top';
        $line_tension = $settings['line_tension'] ?? 0.2;
        $line_width = $settings['line_width'] ?? 2;
        $show_grid = $settings['show_grid'] ?? 1;
        $show_last_updated = $settings['show_last_updated'] ?? 1;

        echo '<div class="wrap"><h1>Settings</h1>';
        echo '<form method="post">';
        wp_nonce_field( 'zci_settings_nonce', 'zci_settings_nonce' );
        echo '<table class="form-table">';
        echo '<tr><th>Legend Position</th><td>';
        echo '<select name="zci_legend_position">';
        echo '<option value="top"' . selected( $legend_position, 'top', false ) . '>Top</option>';
        echo '<option value="bottom"' . selected( $legend_position, 'bottom', false ) . '>Bottom</option>';
        echo '<option value="left"' . selected( $legend_position, 'left', false ) . '>Left</option>';
        echo '<option value="right"' . selected( $legend_position, 'right', false ) . '>Right</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '<tr><th>Line Smoothing</th><td><input type="number" name="zci_line_tension" value="' . esc_attr( $line_tension ) . '" step="0.1" min="0" max="1" /></td></tr>';
        echo '<tr><th>Line Width</th><td><input type="number" name="zci_line_width" value="' . esc_attr( $line_width ) . '" min="1" max="10" /></td></tr>';
        echo '<tr><th>Show Grid</th><td><input type="checkbox" name="zci_show_grid" value="1"' . checked( $show_grid, 1, false ) . ' /></td></tr>';
        echo '<tr><th>Show Last Updated</th><td><input type="checkbox" name="zci_show_last_updated" value="1"' . checked( $show_last_updated, 1, false ) . ' /></td></tr>';
        echo '</table>';
        submit_button( 'Save Settings', 'primary', 'zci_save_settings' );
        echo '</form>';
        echo '</div>';
    }
}


