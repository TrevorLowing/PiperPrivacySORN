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
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique submission ID',
            sorn_id bigint(20) unsigned NOT NULL COMMENT 'Associated SORN record ID',
            submission_id varchar(100) NOT NULL COMMENT 'Federal Register submission ID',
            document_number varchar(100) DEFAULT NULL COMMENT 'Published document number',
            status varchar(50) NOT NULL DEFAULT '" . self::STATUS_SUBMITTED . "' COMMENT 'Submission status',
            submitted_at datetime NOT NULL COMMENT 'Initial submission timestamp',
            published_at datetime DEFAULT NULL COMMENT 'Publication timestamp',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sorn_id (sorn_id),
            KEY submission_id (submission_id),
            KEY document_number (document_number),
            KEY status (status),
            KEY sorn_status (sorn_id, status)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        if (empty($result)) {
            error_log('Failed to create submissions table: ' . $wpdb->last_error);
        }
    }

    /**
     * Create the submission events tracking table
     */
    private function create_submission_events_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_sorn_fr_submission_events';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Event ID',
            submission_id varchar(100) NOT NULL COMMENT 'Associated submission ID',
            event_type varchar(50) NOT NULL COMMENT 'Event type identifier',
            event_data longtext DEFAULT NULL COMMENT 'Serialized event payload',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id),
            KEY event_type (event_type)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        if (empty($result)) {
            error_log('Failed to create submission events table: ' . $wpdb->last_error);
        }
    }

    /**
     * Create the submission archives table
     */
    private function create_submission_archives_table(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'piper_privacy_sorn_fr_submission_archives';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Archive ID',
            submission_id varchar(100) NOT NULL COMMENT 'Associated submission ID',
            sorn_id bigint(20) unsigned NOT NULL COMMENT 'Related SORN record ID',
            data longtext NOT NULL COMMENT 'Serialized submission data',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id),
            KEY sorn_id (sorn_id)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        if (empty($result)) {
            error_log('Failed to create submission archives table: ' . $wpdb->last_error);
        }
    }

    /**
     * Drop Federal Register related tables
     */
    public function drop(): void {
        if (!current_user_can('delete_plugins')) {
            error_log('Security violation: Unauthorized table drop attempt');
            return;
        }

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
