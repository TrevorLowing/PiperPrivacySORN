<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Database;

/**
 * Handles Federal Register related database tables
 */
class FederalRegisterTables {
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_PUBLISHED = 'published';

    /**
     * Initialize database tables
     */
    public function init(): void {
        $this->create_submissions_table();
        $this->create_submission_events_table();
        $this->create_submission_archives_table();
    }

    /**
     * Create the submissions tracking table
     */
    private function create_submissions_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_sorn_fr_submissions';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sorn_id bigint(20) unsigned NOT NULL,
            submission_id varchar(100) NOT NULL,
            document_number varchar(100) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'submitted',
            submitted_at datetime NOT NULL,
            published_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sorn_id (sorn_id),
            KEY submission_id (submission_id),
            KEY document_number (document_number),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the submission events tracking table
     */
    private function create_submission_events_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_sorn_fr_submission_events';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id varchar(100) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id),
            KEY event_type (event_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the submission archives table
     */
    private function create_submission_archives_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_sorn_fr_submission_archives';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id varchar(100) NOT NULL,
            sorn_id bigint(20) unsigned NOT NULL,
            data longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id),
            KEY sorn_id (sorn_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Drop Federal Register related tables
     */
    public function drop(): void {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'piper_privacy_sorn_fr_submissions',
            $wpdb->prefix . 'piper_privacy_sorn_fr_submission_events',
            $wpdb->prefix . 'piper_privacy_sorn_fr_submission_archives'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
