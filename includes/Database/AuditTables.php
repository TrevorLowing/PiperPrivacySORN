<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Database;

/**
 * Handles audit logging tables
 */
class AuditTables {
    /**
     * Initialize database tables
     */
    public function init(): void {
        $this->create_audit_log_table();
    }

    /**
     * Create the audit log table
     */
    private function create_audit_log_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_audit_log';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(100) NOT NULL,
            ip_address varchar(45) NOT NULL,
            data json DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Drop audit tables
     */
    public function drop(): void {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}piper_privacy_audit_log");
    }
}
