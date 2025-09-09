<?php
/**
 * Plugin Name: Zestra Capital Economic Insights
 * Plugin URI: https://client.zestracapital.com
 * Description: Complete economic data analysis platform with modern charts and comparisons.
 * Version: 1.0.0
 * Author: Zestra Capital
 * Author URI: https://zestracapital.com
 * Text Domain: zc-economic-insights
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) exit;

class ZC_Economic_Insights_Plugin {
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
        add_shortcode('zci_compare', [$this, 'render_compare_shortcode']);
        add_action('rest_api_init', [$this, 'register_api_endpoints']);
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
    }
    
    public function activate_plugin() {
        $this->create_database_tables();
        $this->insert_sample_data();
    }
    
    private function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sources table
        $sql_sources = "CREATE TABLE {$wpdb->prefix}zci_sources (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_key VARCHAR(64) NOT NULL,
            source_type VARCHAR(32) NOT NULL,
            name VARCHAR(191) NOT NULL,
            credentials LONGTEXT NULL,
            config LONGTEXT NULL,
            last_refreshed DATETIME NULL,
            status VARCHAR(32) DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_key (source_key)
        ) $charset_collate;";
        
        // Indicators table
        $sql_indicators = "CREATE TABLE {$wpdb->prefix}zci_indicators (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(128) NOT NULL,
            display_name VARCHAR(191) NOT NULL,
            category VARCHAR(128) NULL,
            frequency VARCHAR(32) NULL,
            units VARCHAR(64) NULL,
            source_id BIGINT UNSIGNED NULL,
            external_code VARCHAR(191) NULL,
            metadata LONGTEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY source_id (source_id)
        ) $charset_collate;";
        
        // Series table
        $sql_series = "CREATE TABLE {$wpdb->prefix}zci_series (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            indicator_slug VARCHAR(128) NOT NULL,
            obs_date DATE NOT NULL,
            value DECIMAL(20,6) NULL,
            source_id BIGINT UNSIGNED DEFAULT 1,
            last_updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY indicator_slug (indicator_slug),
            KEY obs_date (obs_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sources);
        dbDelta($sql_indicators);
        dbDelta($sql_series);
    }
    
    private function insert_sample_data() {
        global $wpdb;
        $now = current_time('mysql');
        
        $indicators = [
            ['slug' => 'cpi_us', 'display_name' => 'Consumer Price Index (US)', 'category' => 'Inflation', 'frequency' => 'monthly', 'units' => 'Index'],
            ['slug' => 'gdp_us', 'display_name' => 'GDP (US)', 'category' => 'GDP', 'frequency' => 'quarterly', 'units' => 'Billions USD'],
            ['slug' => 'unemployment_us', 'display_name' => 'Unemployment Rate (US)', 'category' => 'Employment', 'frequency' => 'monthly', 'units' => 'Percent'],
            ['slug' => 'cpi_uk', 'display_name' => 'Consumer Price Index (UK)', 'category' => 'Inflation', 'frequency' => 'monthly', 'units' => 'Index'],
            ['slug' => 'gdp_uk', 'display_name' => 'GDP (UK)', 'category' => 'GDP', 'frequency' => 'quarterly', 'units' => 'Millions GBP'],
            ['slug' => 'unemployment_uk', 'display_name' => 'Unemployment Rate (UK)', 'category' => 'Employment', 'frequency' => 'monthly', 'units' => 'Percent'],
            ['slug' => 'cpi_ca', 'display_name' => 'Consumer Price Index (Canada)', 'category' => 'Inflation', 'frequency' => 'monthly', 'units' => 'Index'],
            ['slug' => 'gdp_ca', 'display_name' => 'GDP (Canada)', 'category' => 'GDP', 'frequency' => 'quarterly', 'units' => 'Millions CAD'],
            ['slug' => 'unemployment_ca', 'display_name' => 'Unemployment Rate (Canada)', 'category' => 'Employment', 'frequency' => 'monthly', 'units' => 'Percent'],
            ['slug' => 'cpi_eu', 'display_name' => 'Consumer Price Index (Euro Area)', 'category' => 'Inflation', 'frequency' => 'monthly', 'units' => 'Index'],
        ];
        
        foreach ($indicators as $ind) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}zci_indicators WHERE slug = %s", $ind['slug']));
            if (!$exists) {
                $wpdb->insert($wpdb->prefix . 'zci_indicators', [
                    'slug' => $ind['slug'],
                    'display_name' => $ind['display_name'],
                    'category' => $ind['category'],
                    'frequency' => $ind['frequency'],
                    'units' => $ind['units'],
                    'source_id' => 1,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
                
                // Insert 24 months of sample data
                for ($i = 23; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i months"));
                    $base_value = 100 + rand(-10, 20);
                    $value = $base_value + ($i * 0.2) + (rand(-5, 5));
                    
                    $wpdb->insert($wpdb->prefix . 'zci_series', [
                        'indicator_slug' => $ind['slug'],
                        'obs_date' => $date,
                        'value' => $value,
                        'source_id' => 1,
                        'last_updated_at' => $now
                    ]);
                }
            }
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Zestra Capital Economic Insights',
            'Economic Insights',
            'manage_options',
            'zci-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'zci-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'zci-dashboard',
            [$this, 'render_dashboard_page']
        );
        
        add_submenu_page(
            'zci-dashboard',
            'Data Sources',
            'Data Sources',
            'manage_options',
            'zci-sources',
            [$this, 'render_sources_page']
        );
        
        add_submenu_page(
            'zci-dashboard',
            'Indicators',
            'Indicators',
            'manage_options',
            'zci-indicators',
            [$this, 'render_indicators_page']
        );
        
        add_submenu_page(
            'zci-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'zci-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1>Zestra Capital Economic Insights</h1>
            <div class="zci-dashboard">
                <div class="zci-card">
                    <h2>Welcome to Economic Insights</h2>
                    <p>Use the menu to configure data sources, manage indicators, and create comparisons.</p>
                    
                    <div class="zci-quick-links">
                        <a href="<?php echo admin_url('admin.php?page=zci-sources'); ?>" class="button button-primary">Setup Data Sources</a>
                        <a href="<?php echo admin_url('admin.php?page=zci-indicators'); ?>" class="button button-secondary">Manage Indicators</a>
                        <a href="<?php echo admin_url('admin.php?page=zci-settings'); ?>" class="button button-secondary">Settings</a>
                    </div>
                    
                    <div class="zci-shortcodes">
                        <h3>Available Shortcodes</h3>
                        <code>[zci_compare title="Economic Data Comparison" range="1y" height="500px"]</code>
                        <p>Use this shortcode on any page to add the comparison tool.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .zci-dashboard { display: grid; gap: 20px; }
        .zci-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .zci-quick-links { margin: 20px 0; }
        .zci-quick-links a { margin-right: 10px; }
        .zci-shortcodes { margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        .zci-shortcodes code { background: #e5e7eb; padding: 5px 8px; border-radius: 4px; display: block; margin: 10px 0; }
        </style>
        <?php
    }
    
    public function render_sources_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'zci_sources';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'add_fred') {
                $api_key = sanitize_text_field($_POST['fred_api_key']);
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE source_key = %s", 'fred'));
                
                if (!$exists) {
                    $wpdb->insert($table_name, [
                        'source_key' => 'fred',
                        'source_type' => 'api',
                        'name' => 'Federal Reserve Economic Data (FRED)',
                        'credentials' => json_encode(['api_key' => $api_key]),
                        'config' => json_encode(['base_url' => 'https://api.stlouisfed.org/fred/']),
                        'status' => 'active',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ]);
                    echo '<div class="notice notice-success"><p>FRED API key saved successfully!</p></div>';
                } else {
                    $wpdb->update($table_name, [
                        'credentials' => json_encode(['api_key' => $api_key]),
                        'updated_at' => current_time('mysql')
                    ], ['source_key' => 'fred']);
                    echo '<div class="notice notice-success"><p>FRED API key updated successfully!</p></div>';
                }
            }
        }
        
        $fred_source = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE source_key = %s", 'fred'));
        $fred_key = '';
        if ($fred_source && $fred_source->credentials) {
            $creds = json_decode($fred_source->credentials, true);
            $fred_key = $creds['api_key'] ?? '';
        }
        
        ?>
        <div class="wrap">
            <h1>Data Sources</h1>
            
            <div class="zci-sources-grid">
                <div class="zci-source-card">
                    <h3>Federal Reserve Economic Data (FRED)</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_fred">
                        <label for="fred_api_key">API Key:</label>
                        <input type="password" id="fred_api_key" name="fred_api_key" value="<?php echo esc_attr($fred_key); ?>" style="width: 300px;" />
                        <button type="submit" class="button button-primary">Save FRED Key</button>
                    </form>
                    <p>Get your free API key from <a href="https://fred.stlouisfed.org/docs/api/fred/" target="_blank">fred.stlouisfed.org</a></p>
                </div>
            </div>
        </div>
        
        <style>
        .zci-sources-grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); }
        .zci-source-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .zci-source-card h3 { margin-top: 0; }
        .zci-source-card form { margin: 15px 0; }
        .zci-source-card label { display: block; margin-bottom: 5px; font-weight: bold; }
        .zci-source-card input { margin-bottom: 10px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        </style>
        <?php
    }
    
    public function render_indicators_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'zci_indicators';
        $indicators = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Indicators</h1>
            
            <div class="zci-indicators-list">
                <?php if ($indicators): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Indicator</th>
                                <th>Category</th>
                                <th>Frequency</th>
                                <th>Units</th>
                                <th>Shortcode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indicators as $indicator): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($indicator->display_name); ?></strong></td>
                                    <td><?php echo esc_html($indicator->category ?: 'N/A'); ?></td>
                                    <td><?php echo esc_html($indicator->frequency ?: 'N/A'); ?></td>
                                    <td><?php echo esc_html($indicator->units ?: 'N/A'); ?></td>
                                    <td><code>[zci_compare indicators="<?php echo esc_attr($indicator->slug); ?>"]</code></td>
                                    <td><?php echo $indicator->is_active ? '<span style="color:green;">Active</span>' : '<span style="color:red;">Inactive</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No indicators found. Sample data will be added automatically when you activate the plugin.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <div class="zci-settings">
                <div class="zci-card">
                    <h3>Chart Settings</h3>
                    <p>Chart appearance settings are built into the shortcode. Use these parameters:</p>
                    <ul>
                        <li><code>range="6m|1y|2y|5y|10y|all"</code> - Time range</li>
                        <li><code>type="line|bar"</code> - Chart type</li>
                        <li><code>height="500px"</code> - Chart height</li>
                        <li><code>title="Your Title"</code> - Chart title</li>
                    </ul>
                    
                    <h3>Example Shortcode</h3>
                    <code>[zci_compare title="Economic Data Comparison" range="1y" height="500px" type="line"]</code>
                </div>
            </div>
        </div>
        
        <style>
        .zci-settings .zci-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .zci-settings code { background: #f0f0f0; padding: 5px 8px; border-radius: 4px; display: block; margin: 10px 0; }
        .zci-settings ul { margin: 15px 0; padding-left: 20px; }
        </style>
        <?php
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'zci-') === false) return;
        
        wp_enqueue_style('zci-admin', plugins_url('admin/css/zci-admin.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('zci-admin', plugins_url('admin/js/zci-admin.js', __FILE__), ['jquery'], '1.0.0', true);
    }
    
    public function frontend_scripts() {
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
        
        wp_add_inline_style('wp-block-library', '
            .zci-compare-builder { border: 1px solid #e5e7eb; border-radius: 10px; padding: 15px; background: #fff; margin: 20px 0; }
            .zci-chart-title { font-weight: 700; margin: 0 0 10px 0; font-size: 18px; color: #111827; }
            .zci-compare-controls { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
            .zci-compare-search { flex: 1; min-width: 250px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; }
            .zci-compare-results { list-style: none; margin: 6px 0 0; padding: 0; max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; position: absolute; background: white; width: calc(100% - 16px); z-index: 1000; }
            .zci-compare-results li { padding: 8px; border-bottom: 1px solid #f3f4f6; cursor: pointer; background: #f9fafb; }
            .zci-compare-results li:hover { background: #f3f4f6; }
            .zci-compare-results li:last-child { border-bottom: 0; }
            .zci-compare-selected { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
            .zci-compare-selected span { padding: 4px 8px; background: #ef4444; color: #fff; border-radius: 4px; cursor: pointer; font-size: 12px; }
            .zci-chart-updated { margin-top: 10px; color: #6b7280; font-size: 12px; }
            .zci-compare-results-container { position: relative; }
        ');
        
        wp_localize_script('chartjs', 'zciAjax', [
            'rest_url' => rest_url('zci/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
        
        wp_add_inline_script('chartjs', '
            function initZCICompare() {
                var builders = document.querySelectorAll(".zci-compare-builder");
                builders.forEach(function(root) {
                    var searchContainer = root.querySelector(".zci-compare-results-container");
                    var search = root.querySelector(".zci-compare-search");
                    var results = root.querySelector(".zci-compare-results");
                    var selected = root.querySelector(".zci-compare-selected");
                    var canvas = root.querySelector("canvas");
                    var type = root.getAttribute("data-type") || "line";
                    var range = root.getAttribute("data-range") || "1y";
                    
                    var chosen = [];
                    
                    search.addEventListener("input", function() {
                        var q = search.value.trim();
                        if (q.length < 2) { 
                            results.innerHTML = ""; 
                            results.style.display = "none";
                            return; 
                        }
                        
                        setTimeout(function() {
                            var url = zciAjax.rest_url + "search-indicators?q=" + encodeURIComponent(q);
                            fetch(url, {
                                headers: {
                                    "X-WP-Nonce": zciAjax.nonce
                                }
                            })
                            .then(function(r) { return r.json(); })
                            .then(function(j) {
                                results.innerHTML = "";
                                if (j.items && j.items.length > 0) {
                                    j.items.forEach(function(it) {
                                        var li = document.createElement("li");
                                        li.textContent = it.display_name + " (" + it.slug + ")";
                                        li.onclick = function() {
                                            if (chosen.indexOf(it.slug) === -1) {
                                                chosen.push(it.slug);
                                                renderSelected();
                                                fetchAndRender();
                                            }
                                            results.innerHTML = "";
                                            results.style.display = "none";
                                            search.value = "";
                                        };
                                        results.appendChild(li);
                                    });
                                    results.style.display = "block";
                                } else {
                                    results.innerHTML = "<li>No indicators found</li>";
                                    results.style.display = "block";
                                }
                            })
                            .catch(function(e) {
                                results.innerHTML = "<li style=\'color:red\'>Search error</li>";
                                results.style.display = "block";
                                console.log("Search error:", e);
                            });
                        }, 300);
                    });
                    
                    function renderSelected() {
                        selected.innerHTML = "";
                        chosen.forEach(function(slug, i) {
                            var tag = document.createElement("span");
                            tag.textContent = slug + " ✕";
                            tag.onclick = function() {
                                chosen.splice(i, 1);
                                renderSelected();
                                fetchAndRender();
                            };
                            selected.appendChild(tag);
                        });
                    }
                    
                    function fetchAndRender() {
                        if (chosen.length === 0) return;
                        var url = zciAjax.rest_url + "chart?indicators=" + encodeURIComponent(chosen.join(",")) + "&range=" + encodeURIComponent(range);
                        fetch(url, {
                            headers: {
                                "X-WP-Nonce": zciAjax.nonce
                            }
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(json) {
                            if (!json.labels || json.labels.length === 0) return;
                            
                            var ctx = canvas.getContext("2d");
                            if (root._chart) root._chart.destroy();
                            
                            var colors = ["#3b82f6", "#ef4444", "#10b981", "#f59e0b", "#8b5cf6", "#ec4899", "#6366f1"];
                            root._chart = new Chart(ctx, {
                                type: type,
                                data: {
                                    labels: json.labels,
                                    datasets: json.datasets.map(function(ds, i) {
                                        return {
                                            label: ds.label,
                                            data: ds.data,
                                            borderColor: colors[i % colors.length],
                                            backgroundColor: colors[i % colors.length] + "20",
                                            tension: 0.2,
                                            pointRadius: 3
                                        };
                                    })
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { 
                                            position: "top",
                                            onClick: function(e, legendItem, legend) {
                                                var index = legendItem.datasetIndex;
                                                var ci = legend.chart;
                                                var meta = ci.getDatasetMeta(index);
                                                meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                                                ci.update();
                                            }
                                        }
                                    },
                                    scales: { 
                                        x: { grid: { display: false } }, 
                                        y: { grid: { display: false } } 
                                    }
                                }
                            });
                            
                            var updatedEl = root.querySelector(".zci-chart-updated");
                            if (updatedEl) {
                                updatedEl.textContent = "Last updated: " + new Date().toLocaleString();
                            }
                        })
                        .catch(function(e) {
                            console.log("Chart error:", e);
                            var updatedEl = root.querySelector(".zci-chart-updated");
                            if (updatedEl) {
                                updatedEl.textContent = "Error loading chart data";
                            }
                        });
                    }
                    
                    // Close results when clicking outside
                    document.addEventListener("click", function(e) {
                        if (!searchContainer.contains(e.target)) {
                            results.style.display = "none";
                        }
                    });
                });
            }
            document.addEventListener("DOMContentLoaded", initZCICompare);
        ');
    }
    
    public function register_api_endpoints() {
        register_rest_route('zci/v1', '/search-indicators', [
            'methods' => 'GET',
            'callback' => [$this, 'api_search_indicators'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('zci/v1', '/chart', [
            'methods' => 'GET', 
            'callback' => [$this, 'api_get_chart_data'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    public function api_search_indicators($request) {
        global $wpdb;
        $q = '%' . $wpdb->esc_like(sanitize_text_field($request->get_param('q'))) . '%';
        $table = $wpdb->prefix . 'zci_indicators';
        
        $sql = $wpdb->prepare(
            "SELECT slug, display_name FROM {$table} WHERE display_name LIKE %s OR slug LIKE %s ORDER BY updated_at DESC LIMIT 20", 
            $q, $q 
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        
        return new WP_REST_Response(['items' => $rows], 200);
    }
    
    public function api_get_chart_data($request) {
        global $wpdb;
        $indicators = sanitize_text_field($request->get_param('indicators'));
        $range = sanitize_text_field($request->get_param('range')) ?: '1y';
        
        if (empty($indicators)) {
            return new WP_REST_Response(['labels' => [], 'datasets' => []], 200);
        }
        
        $indicator_slugs = array_filter(array_map('trim', explode(',', $indicators)));
        $labels = [];
        $datasets = [];
        
        // Calculate date range
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-1 year'));
        
        switch ($range) {
            case '6m': $start_date = date('Y-m-d', strtotime('-6 months')); break;
            case '2y': $start_date = date('Y-m-d', strtotime('-2 years')); break;
            case '5y': $start_date = date('Y-m-d', strtotime('-5 years')); break;
            case '10y': $start_date = date('Y-m-d', strtotime('-10 years')); break;
            case 'all': $start_date = '1900-01-01'; break;
        }
        
        $table = $wpdb->prefix . 'zci_series';
        
        foreach ($indicator_slugs as $slug) {
            $sql = $wpdb->prepare(
                "SELECT obs_date, value FROM {$table} WHERE indicator_slug = %s AND obs_date >= %s AND obs_date <= %s ORDER BY obs_date ASC", 
                $slug, $start_date, $end_date
            );
            $rows = $wpdb->get_results($sql, ARRAY_A);
            
            if ($rows) {
                $data = [];
                foreach ($rows as $row) {
                    $labels[$row['obs_date']] = true;
                    $data[] = $row['value'] ? (float)$row['value'] : null;
                }
                $datasets[] = [
                    'label' => $slug,
                    'data' => $data
                ];
            }
        }
        
        $labels = array_keys($labels);
        sort($labels);
        
        return new WP_REST_Response([
            'labels' => $labels,
            'datasets' => $datasets,
            'last_updated' => current_time('mysql')
        ], 200);
    }
    
    public function render_compare_shortcode($atts) {
        $atts = shortcode_atts([
            'title' => 'Compare Economic Indicators',
            'range' => '1y',
            'type'  => 'line',
            'height' => '500px',
            'indicators' => ''
        ], $atts, 'zci_compare');
        
        if (!empty($atts['indicators'])) {
            $atts['range'] = '1y'; // Override for pre-selected indicators
        }
        
        return '
            <div class="zci-compare-builder" data-range="' . esc_attr($atts['range']) . '" data-type="' . esc_attr($atts['type']) . '">
                <div class="zci-chart-title">' . esc_html($atts['title']) . '</div>
                <div class="zci-compare-controls">
                    <div class="zci-compare-results-container">
                        <input type="text" class="zci-compare-search" placeholder="Search indicators..." />
                        <ul class="zci-compare-results" style="display: none;"></ul>
                    </div>
                    <div class="zci-compare-selected"></div>
                </div>
                <div style="height:' . esc_attr($atts['height']) . ';"><canvas></canvas></div>
                <div class="zci-chart-updated">Ready to load data</div>
            </div>
        ';
    }
}

ZC_Economic_Insights_Plugin::instance();