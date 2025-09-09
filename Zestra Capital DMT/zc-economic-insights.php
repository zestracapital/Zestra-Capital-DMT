<?php
/**
 * Plugin Name: Zestra Capital Data Management Tool (DMT)
 * Plugin URI: https://client.zestracapital.com
 * Description: Pure data management tool - handles data sources, indicators, CSV imports, API integrations. No charting functionality.
 * Version: 2.0.0
 * Author: Zestra Capital
 * Author URI: https://zestracapital.com
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'ZCI_DMT_VERSION', '2.0.0' );
define( 'ZCI_DMT_PLUGIN_FILE', __FILE__ );
define( 'ZCI_DMT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZCI_DMT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Activation hook
register_activation_hook( __FILE__, 'zci_dmt_activate_plugin' );
function zci_dmt_activate_plugin() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Data Sources table
    $table_sources = $wpdb->prefix . 'zci_sources';
    $sql_sources = "CREATE TABLE $table_sources (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_key VARCHAR(64) NOT NULL,
        source_type ENUM('fred_api', 'csv_upload', 'manual_entry', 'external_api', 'google_sheets') NOT NULL,
        name VARCHAR(191) NOT NULL,
        description TEXT NULL,
        credentials LONGTEXT NULL,
        config LONGTEXT NULL,
        last_sync DATETIME NULL,
        auto_sync TINYINT(1) DEFAULT 0,
        sync_frequency VARCHAR(32) DEFAULT 'daily',
        status ENUM('active', 'inactive', 'error') DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY source_key (source_key)
    ) $charset_collate;";
    dbDelta( $sql_sources );

    // Indicators table
    $table_indicators = $wpdb->prefix . 'zci_indicators';
    $sql_indicators = "CREATE TABLE $table_indicators (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(128) NOT NULL,
        display_name VARCHAR(191) NOT NULL,
        category VARCHAR(128) NULL,
        subcategory VARCHAR(128) NULL,
        description TEXT NULL,
        frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NULL,
        units VARCHAR(64) NULL,
        source_id BIGINT UNSIGNED NOT NULL,
        external_code VARCHAR(191) NULL,
        metadata LONGTEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY source_id (source_id),
        KEY category (category),
        KEY is_active (is_active)
    ) $charset_collate;";
    dbDelta( $sql_indicators );

    // Time Series Data table
    $table_series = $wpdb->prefix . 'zci_series';
    $sql_series = "CREATE TABLE $table_series (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        indicator_slug VARCHAR(128) NOT NULL,
        obs_date DATE NOT NULL,
        value DECIMAL(20,6) NULL,
        source_id BIGINT UNSIGNED NOT NULL,
        import_batch VARCHAR(64) NULL,
        last_updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY unique_observation (indicator_slug, obs_date),
        KEY indicator_slug (indicator_slug),
        KEY obs_date (obs_date),
        KEY source_id (source_id)
    ) $charset_collate;";
    dbDelta( $sql_series );

    // Import Log table (for tracking data imports)
    $table_imports = $wpdb->prefix . 'zci_imports';
    $sql_imports = "CREATE TABLE $table_imports (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_id BIGINT UNSIGNED NOT NULL,
        import_type VARCHAR(64) NOT NULL,
        file_name VARCHAR(191) NULL,
        records_imported INT DEFAULT 0,
        records_failed INT DEFAULT 0,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        log_data LONGTEXT NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY source_id (source_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta( $sql_imports );

    // Set database version
    update_option( 'zci_dmt_db_version', '2.0.0' );

    // Insert default FRED source
    $fred_exists = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sources WHERE source_key = 'fred_default'" );
    if ( !$fred_exists ) {
        $wpdb->insert( $table_sources, [
            'source_key' => 'fred_default',
            'source_type' => 'fred_api',
            'name' => 'Federal Reserve Economic Data (FRED)',
            'description' => 'Primary source for US economic indicators',
            'status' => 'inactive',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
    }
}

// Main DMT Class - ONLY DATA MANAGEMENT
class ZCI_Data_Management_Tool {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
        add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
        add_action( 'wp_ajax_zci_import_csv', [ $this, 'handle_csv_import' ] );
        add_action( 'wp_ajax_zci_test_fred_api', [ $this, 'test_fred_connection' ] );
    }

    public function init() {
        // Hook for other plugins to know DMT data is updated
        // Charts plugin will listen to this
        do_action( 'zci_dmt_initialized' );
    }

    public function add_admin_menu() {
        add_menu_page(
            'DMT - Data Management',
            'Economic DMT',
            'manage_options',
            'zci-dmt-dashboard',
            [ $this, 'render_dashboard_page' ],
            'dashicons-database-import',
            30
        );

        add_submenu_page(
            'zci-dmt-dashboard',
            'Data Sources',
            'Data Sources',
            'manage_options',
            'zci-dmt-sources',
            [ $this, 'render_sources_page' ]
        );

        add_submenu_page(
            'zci-dmt-dashboard',
            'Indicators',
            'Indicators',
            'manage_options',
            'zci-dmt-indicators',
            [ $this, 'render_indicators_page' ]
        );

        add_submenu_page(
            'zci-dmt-dashboard',
            'CSV Import',
            'CSV Import',
            'manage_options',
            'zci-dmt-import',
            [ $this, 'render_import_page' ]
        );

        add_submenu_page(
            'zci-dmt-dashboard',
            'Import History',
            'Import History',
            'manage_options',
            'zci-dmt-history',
            [ $this, 'render_history_page' ]
        );
    }

    public function admin_scripts( $hook ) {
        if ( strpos( $hook, 'zci-dmt' ) === false ) return;

        wp_enqueue_media(); // For file uploads
        wp_enqueue_script( 'jquery' );

        wp_add_inline_style( 'wp-admin', '
        .zci-dmt-container { max-width: 1200px; margin: 20px 0; }
        .zci-dmt-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
        .zci-dmt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .zci-source-item { border: 1px solid #ddd; border-radius: 8px; padding: 15px; }
        .zci-source-active { border-color: #00a32a; background: #f0f8f0; }
        .zci-source-inactive { border-color: #dba617; background: #fef8e7; }
        .zci-import-dropzone { border: 2px dashed #ccd0d4; border-radius: 8px; padding: 40px; text-align: center; margin: 20px 0; }
        .zci-import-dropzone:hover { border-color: #0073aa; background: #f0f8ff; }
        .zci-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .zci-stat-card { background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        ' );

        wp_localize_script( 'jquery', 'zciDmt', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'zci_dmt_nonce' ),
            'rest_url' => rest_url( 'zci-dmt/v1/' )
        ]);
    }

    public function register_api_endpoints() {
        // Pure data API - no chart rendering
        register_rest_route( 'zci-dmt/v1', '/sources', [
            'methods' => 'GET',
            'callback' => [ $this, 'api_get_sources' ],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route( 'zci-dmt/v1', '/indicators', [
            'methods' => 'GET',
            'callback' => [ $this, 'api_get_indicators' ],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route( 'zci-dmt/v1', '/data/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'api_get_indicator_data' ],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route( 'zci-dmt/v1', '/search', [
            'methods' => 'GET',
            'callback' => [ $this, 'api_search_indicators' ],
            'permission_callback' => '__return_true'
        ]);
    }

    public function api_get_sources( $request ) {
        global $wpdb;
        $sources = $wpdb->get_results( 
            "SELECT * FROM {$wpdb->prefix}zci_sources ORDER BY created_at DESC",
            ARRAY_A 
        );
        return new WP_REST_Response( $sources, 200 );
    }

    public function api_get_indicators( $request ) {
        global $wpdb;
        $category = $request->get_param( 'category' );
        $active_only = $request->get_param( 'active_only' );

        $where = [];
        if ( $category ) {
            $where[] = $wpdb->prepare( 'category = %s', $category );
        }
        if ( $active_only ) {
            $where[] = 'is_active = 1';
        }

        $sql = "SELECT * FROM {$wpdb->prefix}zci_indicators";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY display_name';

        $indicators = $wpdb->get_results( $sql, ARRAY_A );
        return new WP_REST_Response( $indicators, 200 );
    }

    public function api_get_indicator_data( $request ) {
        global $wpdb;
        $slug = sanitize_text_field( $request['slug'] );
        $from_date = sanitize_text_field( $request->get_param( 'from' ) );
        $to_date = sanitize_text_field( $request->get_param( 'to' ) );

        $where = [ $wpdb->prepare( 'indicator_slug = %s', $slug ) ];

        if ( $from_date ) {
            $where[] = $wpdb->prepare( 'obs_date >= %s', $from_date );
        }
        if ( $to_date ) {
            $where[] = $wpdb->prepare( 'obs_date <= %s', $to_date );
        }

        $sql = "SELECT obs_date, value FROM {$wpdb->prefix}zci_series WHERE " . implode( ' AND ', $where ) . " ORDER BY obs_date ASC";
        $data = $wpdb->get_results( $sql, ARRAY_A );

        return new WP_REST_Response([
            'indicator' => $slug,
            'data' => $data,
            'count' => count( $data )
        ], 200 );
    }

    public function api_search_indicators( $request ) {
        global $wpdb;
        $query = sanitize_text_field( $request->get_param( 'q' ) );

        if ( strlen( $query ) < 2 ) {
            return new WP_REST_Response( [], 200 );
        }

        $like = '%' . $wpdb->esc_like( $query ) . '%';
        $sql = $wpdb->prepare(
            "SELECT slug, display_name, category, units FROM {$wpdb->prefix}zci_indicators 
             WHERE (display_name LIKE %s OR category LIKE %s OR slug LIKE %s) AND is_active = 1 
             ORDER BY display_name LIMIT 20",
            $like, $like, $like
        );

        $results = $wpdb->get_results( $sql, ARRAY_A );
        return new WP_REST_Response( $results, 200 );
    }

    // Admin page renderers will go here...
    public function render_dashboard_page() {
        global $wpdb;

        // Get statistics
        $total_sources = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}zci_sources" );
        $active_sources = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}zci_sources WHERE status = 'active'" );
        $total_indicators = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}zci_indicators" );
        $total_datapoints = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}zci_series" );

        ?>
        <div class="wrap">
            <h1>📊 Economic Data Management Tool</h1>
            <div class="zci-dmt-container">
                <div class="zci-stats-grid">
                    <div class="zci-stat-card">
                        <h3><?php echo $total_sources; ?></h3>
                        <p>Data Sources</p>
                    </div>
                    <div class="zci-stat-card">
                        <h3><?php echo $active_sources; ?></h3>
                        <p>Active Sources</p>
                    </div>
                    <div class="zci-stat-card">
                        <h3><?php echo $total_indicators; ?></h3>
                        <p>Indicators</p>
                    </div>
                    <div class="zci-stat-card">
                        <h3><?php echo number_format($total_datapoints); ?></h3>
                        <p>Data Points</p>
                    </div>
                </div>

                <div class="zci-dmt-card">
                    <h2>🎯 DMT Purpose</h2>
                    <p><strong>This plugin is ONLY for data management:</strong></p>
                    <ul>
                        <li>✅ Add and manage data sources (FRED API, CSV files, manual entry)</li>
                        <li>✅ Import and organize economic indicators</li>
                        <li>✅ Provide clean data APIs for other systems</li>
                        <li>❌ NO chart rendering (handled by separate Charts plugin)</li>
                    </ul>
                </div>

                <div class="zci-dmt-card">
                    <h2>🚀 Quick Actions</h2>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=zci-dmt-sources'); ?>" class="button button-primary">Manage Data Sources</a>
                        <a href="<?php echo admin_url('admin.php?page=zci-dmt-import'); ?>" class="button">Import CSV Data</a>
                        <a href="<?php echo admin_url('admin.php?page=zci-dmt-indicators'); ?>" class="button">View Indicators</a>
                    </p>
                </div>

                <div class="zci-dmt-card">
                    <h2>📡 REST API Endpoints</h2>
                    <p>For the Charts plugin to consume data:</p>
                    <ul>
                        <li><code><?php echo rest_url('zci-dmt/v1/sources'); ?></code> - Get all data sources</li>
                        <li><code><?php echo rest_url('zci-dmt/v1/indicators'); ?></code> - Get all indicators</li>
                        <li><code><?php echo rest_url('zci-dmt/v1/search?q=gdp'); ?></code> - Search indicators</li>
                        <li><code><?php echo rest_url('zci-dmt/v1/data/{slug}'); ?></code> - Get indicator data</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_sources_page() {
        echo '<div class="wrap"><h1>Data Sources Management</h1><p>Coming in next update...</p></div>';
    }

    public function render_indicators_page() {
        echo '<div class="wrap"><h1>Indicators Management</h1><p>Coming in next update...</p></div>';
    }

    public function render_import_page() {
        echo '<div class="wrap"><h1>CSV Data Import</h1><p>Coming in next update...</p></div>';
    }

    public function render_history_page() {
        echo '<div class="wrap"><h1>Import History</h1><p>Coming in next update...</p></div>';
    }
}

// Initialize DMT
ZCI_Data_Management_Tool::instance();

// Fire hook when data is updated (for Charts plugin to listen)
function zci_dmt_data_updated( $indicator_slug, $action = 'updated' ) {
    do_action( 'zci_dmt_data_changed', $indicator_slug, $action );
}
