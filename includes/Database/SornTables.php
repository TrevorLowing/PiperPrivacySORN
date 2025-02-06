<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Database;

/**
 * Handles SORN and FedRAMP related database tables
 */
class SornTables {
    /**
     * Initialize database tables
     */
    public function init(): void {
        $this->create_sorns_table();
        $this->create_sorn_systems_table();
        $this->create_fedramp_systems_table();
        $this->create_ai_analysis_table();
        $this->create_compliance_checks_table();
    }

    /**
     * Create the main SORN records table
     */
    private function create_sorns_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_sorns';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            system_number varchar(100) NOT NULL,
            agency varchar(100) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'draft',
            content longtext NOT NULL,
            metadata json DEFAULT NULL,
            last_published_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY agency (agency),
            KEY system_number (system_number),
            KEY status (status)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the SORN systems table
     */
    private function create_sorn_systems_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_sorn_systems';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sorn_id bigint(20) unsigned NOT NULL,
            system_name varchar(255) NOT NULL,
            system_id varchar(100) NOT NULL,
            fedramp_system_id bigint(20) unsigned DEFAULT NULL,
            description text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sorn_id (sorn_id),
            KEY system_id (system_id),
            KEY fedramp_system_id (fedramp_system_id)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the FedRAMP authorized systems table
     */
    private function create_fedramp_systems_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_fedramp_systems';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            system_name varchar(255) NOT NULL,
            provider varchar(255) NOT NULL,
            impact_level varchar(50) NOT NULL,
            authorization_date date NOT NULL,
            authorization_type varchar(50) NOT NULL,
            services json DEFAULT NULL,
            metadata json DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY provider (provider),
            KEY impact_level (impact_level),
            KEY authorization_type (authorization_type)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the AI analysis results table
     */
    private function create_ai_analysis_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_ai_analysis';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sorn_id bigint(20) unsigned NOT NULL,
            analysis_type varchar(50) NOT NULL,
            results json NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sorn_id (sorn_id),
            KEY analysis_type (analysis_type)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the compliance checks table
     */
    private function create_compliance_checks_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_compliance_checks';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sorn_id bigint(20) unsigned NOT NULL,
            check_type varchar(50) NOT NULL,
            status varchar(50) NOT NULL,
            results json NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sorn_id (sorn_id),
            KEY check_type (check_type),
            KEY status (status)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Drop all tables
     */
    public function drop(): void {
        global $wpdb;
        
        $tables = [
            'piper_privacy_sorns',
            'piper_privacy_sorn_systems',
            'piper_privacy_fedramp_systems',
            'piper_privacy_ai_analysis',
            'piper_privacy_compliance_checks'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
        }
    }
}
