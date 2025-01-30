<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Cron;

use PiperPrivacySorn\Models\FederalRegisterSubmission;
use PiperPrivacySorn\Services\FederalRegisterApi;
use PiperPrivacySorn\Services\FederalRegisterSubmissionService;

/**
 * Handles Federal Register cron jobs
 */
class FederalRegisterCron {
    /**
     * @var FederalRegisterApi
     */
    private FederalRegisterApi $api;

    /**
     * @var FederalRegisterSubmissionService
     */
    private FederalRegisterSubmissionService $submission_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new FederalRegisterApi();
        $this->submission_service = new FederalRegisterSubmissionService();

        // Register cron schedules and hooks
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
        add_action('init', [$this, 'register_cron_jobs']);
        
        // Register cron handlers
        add_action('piper_privacy_sorn_fr_status_check', [$this, 'check_submission_statuses']);
        add_action('piper_privacy_sorn_fr_daily_cleanup', [$this, 'cleanup_old_events']);
        add_action('piper_privacy_sorn_fr_retry_failed', [$this, 'retry_failed_submissions']);
    }

    /**
     * Add custom cron intervals
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_intervals(array $schedules): array {
        // Every 15 minutes for status checks
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 minutes', 'piper-privacy-sorn')
        ];

        // Every 4 hours for retrying failed submissions
        $schedules['every_4_hours'] = [
            'interval' => 4 * HOUR_IN_SECONDS,
            'display' => __('Every 4 hours', 'piper-privacy-sorn')
        ];

        return $schedules;
    }

    /**
     * Register cron jobs
     */
    public function register_cron_jobs(): void {
        // Status check every 15 minutes
        if (!wp_next_scheduled('piper_privacy_sorn_fr_status_check')) {
            wp_schedule_event(
                time(),
                'every_15_minutes',
                'piper_privacy_sorn_fr_status_check'
            );
        }

        // Daily cleanup at midnight
        if (!wp_next_scheduled('piper_privacy_sorn_fr_daily_cleanup')) {
            wp_schedule_event(
                strtotime('tomorrow midnight'),
                'daily',
                'piper_privacy_sorn_fr_daily_cleanup'
            );
        }

        // Retry failed submissions every 4 hours
        if (!wp_next_scheduled('piper_privacy_sorn_fr_retry_failed')) {
            wp_schedule_event(
                time(),
                'every_4_hours',
                'piper_privacy_sorn_fr_retry_failed'
            );
        }
    }

    /**
     * Check status of pending submissions
     */
    public function check_submission_statuses(): void {
        global $wpdb;

        // Get pending submissions
        $pending_statuses = [
            FederalRegisterSubmissionService::STATUSES['SUBMITTED'],
            FederalRegisterSubmissionService::STATUSES['IN_REVIEW'],
            FederalRegisterSubmissionService::STATUSES['APPROVED'],
            FederalRegisterSubmissionService::STATUSES['SCHEDULED']
        ];

        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions 
                WHERE status IN (" . implode(',', array_fill(0, count($pending_statuses), '%s')) . ")
                AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                LIMIT 50",
                $pending_statuses
            ),
            ARRAY_A
        );

        if (!$submissions) {
            return;
        }

        foreach ($submissions as $data) {
            try {
                $submission = FederalRegisterSubmission::from_array($data);
                $this->submission_service->check_submission_status($submission);
            } catch (\Exception $e) {
                // Log error but continue with other submissions
                error_log(sprintf(
                    'Failed to check Federal Register submission status for ID %s: %s',
                    $data['submission_id'],
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Clean up old events and completed submissions
     */
    public function cleanup_old_events(): void {
        global $wpdb;

        // Delete events older than 90 days for completed submissions
        $completed_statuses = [
            FederalRegisterSubmissionService::STATUSES['PUBLISHED'],
            FederalRegisterSubmissionService::STATUSES['REJECTED']
        ];

        $wpdb->query($wpdb->prepare(
            "DELETE e FROM {$wpdb->prefix}piper_privacy_sorn_fr_submission_events e
            INNER JOIN {$wpdb->prefix}piper_privacy_sorn_fr_submissions s 
                ON e.submission_id = s.submission_id
            WHERE s.status IN (" . implode(',', array_fill(0, count($completed_statuses), '%s')) . ")
            AND e.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
            $completed_statuses
        ));

        // Delete error events older than 30 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}piper_privacy_sorn_fr_submission_events
            WHERE event_type = 'error'
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Archive old completed submissions
        if ($this->should_archive_submissions()) {
            $this->archive_old_submissions();
        }
    }

    /**
     * Retry failed submissions
     */
    public function retry_failed_submissions(): void {
        global $wpdb;

        // Get failed submissions that haven't been retried too many times
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, COUNT(e.id) as retry_count
                FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions s
                LEFT JOIN {$wpdb->prefix}piper_privacy_sorn_fr_submission_events e
                    ON s.submission_id = e.submission_id
                    AND e.event_type = 'retry'
                WHERE s.status = %s
                GROUP BY s.id
                HAVING retry_count < 3
                LIMIT 10",
                FederalRegisterSubmissionService::STATUSES['ERROR']
            ),
            ARRAY_A
        );

        if (!$submissions) {
            return;
        }

        foreach ($submissions as $data) {
            try {
                $submission = FederalRegisterSubmission::from_array($data);
                
                // Log retry attempt
                $submission->add_event('retry', [
                    'attempt' => ($data['retry_count'] + 1)
                ]);

                // Resubmit to Federal Register
                $this->submission_service->resubmit($submission);
            } catch (\Exception $e) {
                // Log error but continue with other submissions
                error_log(sprintf(
                    'Failed to retry Federal Register submission ID %s: %s',
                    $data['submission_id'],
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Check if we should archive old submissions
     *
     * @return bool True if archiving is needed
     */
    private function should_archive_submissions(): bool {
        global $wpdb;

        // Check if we have more than 1000 completed submissions
        $completed_statuses = [
            FederalRegisterSubmissionService::STATUSES['PUBLISHED'],
            FederalRegisterSubmissionService::STATUSES['REJECTED']
        ];

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions
            WHERE status IN (" . implode(',', array_fill(0, count($completed_statuses), '%s')) . ")",
            $completed_statuses
        ));

        return (int) $count > 1000;
    }

    /**
     * Archive old completed submissions
     */
    private function archive_old_submissions(): void {
        global $wpdb;

        // Get submissions older than 90 days that are completed
        $completed_statuses = [
            FederalRegisterSubmissionService::STATUSES['PUBLISHED'],
            FederalRegisterSubmissionService::STATUSES['REJECTED']
        ];

        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions
            WHERE status IN (" . implode(',', array_fill(0, count($completed_statuses), '%s')) . ")
            AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            LIMIT 100",
            $completed_statuses
        ), ARRAY_A);

        if (!$submissions) {
            return;
        }

        // Create archive records
        foreach ($submissions as $submission) {
            // Get all events for this submission
            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submission_events
                WHERE submission_id = %s
                ORDER BY created_at ASC",
                $submission['submission_id']
            ), ARRAY_A);

            // Create archive record
            $archive_data = [
                'submission' => $submission,
                'events' => $events,
                'archived_at' => current_time('mysql')
            ];

            $wpdb->insert(
                $wpdb->prefix . 'piper_privacy_sorn_fr_submission_archives',
                [
                    'submission_id' => $submission['submission_id'],
                    'sorn_id' => $submission['sorn_id'],
                    'data' => wp_json_encode($archive_data),
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%d', '%s', '%s']
            );

            // Delete original records
            $wpdb->delete(
                $wpdb->prefix . 'piper_privacy_sorn_fr_submission_events',
                ['submission_id' => $submission['submission_id']],
                ['%s']
            );

            $wpdb->delete(
                $wpdb->prefix . 'piper_privacy_sorn_fr_submissions',
                ['id' => $submission['id']],
                ['%d']
            );
        }
    }
}
