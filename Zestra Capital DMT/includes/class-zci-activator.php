<?php
namespace ZCI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCI_Activator {
    public static function activate(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE {$wpdb->prefix}zci_sources (
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
            PRIMARY KEY  (id),
            UNIQUE KEY source_key (source_key)
        ) $charset_collate";

        $tables[] = "CREATE TABLE {$wpdb->prefix}zci_indicators (
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
        ) $charset_collate";

        $tables[] = "CREATE TABLE {$wpdb->prefix}zci_countries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            iso_code VARCHAR(3) NOT NULL,
            name VARCHAR(191) NOT NULL,
            region VARCHAR(191) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY iso_code (iso_code)
        ) $charset_collate";

        $tables[] = "CREATE TABLE {$wpdb->prefix}zci_series (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            country_iso VARCHAR(3) NULL,
            indicator_slug VARCHAR(128) NOT NULL,
            obs_date DATE NOT NULL,
            value DECIMAL(20,6) NULL,
            source_id BIGINT UNSIGNED NULL,
            last_updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY indicator_slug (indicator_slug),
            KEY country_iso (country_iso),
            KEY obs_date (obs_date)
        ) $charset_collate";

        $tables[] = "CREATE TABLE {$wpdb->prefix}zci_user_data (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            data_type VARCHAR(32) NOT NULL,
            payload LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY data_type (data_type)
        ) $charset_collate";

        $tables[] = "CREATE TABLE {$wpdb->prefix}zci_meta (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meta_key VARCHAR(191) NOT NULL,
            meta_value LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY meta_key (meta_key)
        ) $charset_collate";

        // Transform definitions (for derived indicators)
        $tables[] = "CREATE TABLE {$wpdb->prefix}zci_transforms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            base_indicator_slug VARCHAR(128) NOT NULL,
            output_indicator_slug VARCHAR(128) NOT NULL,
            output_display_name VARCHAR(191) NOT NULL,
            method VARCHAR(32) NOT NULL,
            params LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY out_slug (output_indicator_slug)
        ) $charset_collate";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        add_option( 'zci_db_version', ZCI_DB_VERSION );
    }

    public static function maybe_upgrade(): void {
        $installed = get_option( 'zci_db_version' );
        if ( $installed === ZCI_DB_VERSION ) { return; }
        // Re-run table creation to add new structures
        self::activate();
        update_option( 'zci_db_version', ZCI_DB_VERSION );
    }
}


