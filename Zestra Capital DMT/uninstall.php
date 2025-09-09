<?php
// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$tables = [
    $wpdb->prefix . 'zci_sources',
    $wpdb->prefix . 'zci_indicators',
    $wpdb->prefix . 'zci_countries',
    $wpdb->prefix . 'zci_series',
    $wpdb->prefix . 'zci_user_data',
    $wpdb->prefix . 'zci_meta',
];
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
delete_option( 'zci_db_version' );


