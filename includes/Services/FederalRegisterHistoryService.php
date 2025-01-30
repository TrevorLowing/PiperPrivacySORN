<?php
declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use PiperPrivacySorn\Models\FederalRegisterSubmission;

/**
 * Service for managing Federal Register submission history and audit logs
 */
class FederalRegisterHistoryService {
    /**
     * Get submission history with optional filters
     *
     * @param array $args Query arguments
     * @return array Array of submissions with their events
     */
    public function get_submission_history(array $args = []): array {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'status' => '',
            'sorn_id' => 0,
            'date_start' => '',
            'date_end' => '',
            'search' => ''
        ];

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build query
        $query = "SELECT s.*, p.post_title as sorn_title
                 FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions s
                 LEFT JOIN {$wpdb->posts} p ON s.sorn_id = p.ID
                 WHERE 1=1";

        $query_args = [];

        // Add filters
        if (!empty($args['status'])) {
            $query .= " AND s.status = %s";
            $query_args[] = $args['status'];
        }

        if (!empty($args['sorn_id'])) {
            $query .= " AND s.sorn_id = %d";
            $query_args[] = $args['sorn_id'];
        }

        if (!empty($args['date_start'])) {
            $query .= " AND s.submitted_at >= %s";
            $query_args[] = $args['date_start'];
        }

        if (!empty($args['date_end'])) {
            $query .= " AND s.submitted_at <= %s";
            $query_args[] = $args['date_end'];
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= " AND (s.submission_id LIKE %s OR p.post_title LIKE %s)";
            $query_args[] = $search;
            $query_args[] = $search;
        }

        // Add ordering
        $valid_orderby = ['submitted_at', 'status', 'sorn_id'];
        $orderby = in_array($args['orderby'], $valid_orderby) ? $args['orderby'] : 'submitted_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query .= " ORDER BY s.$orderby $order";

        // Add pagination
        $query .= " LIMIT %d OFFSET %d";
        $query_args[] = $args['per_page'];
        $query_args[] = $offset;

        // Get submissions
        $submissions = $wpdb->get_results(
            $wpdb->prepare($query, $query_args),
            ARRAY_A
        );

        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions s
                       LEFT JOIN {$wpdb->posts} p ON s.sorn_id = p.ID
                       WHERE 1=1";
        $total = (int) $wpdb->get_var(
            $wpdb->prepare($total_query, array_slice($query_args, 0, -2))
        );

        // Get events for each submission
        $submission_ids = wp_list_pluck($submissions, 'submission_id');
        $events = $this->get_submission_events($submission_ids);

        // Combine submissions with their events
        $results = [];
        foreach ($submissions as $submission) {
            $submission_id = $submission['submission_id'];
            $results[] = [
                'submission' => $submission,
                'events' => $events[$submission_id] ?? []
            ];
        }

        return [
            'submissions' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        ];
    }

    /**
     * Get events for multiple submissions
     *
     * @param array $submission_ids Array of submission IDs
     * @return array Events grouped by submission ID
     */
    public function get_submission_events(array $submission_ids): array {
        if (empty($submission_ids)) {
            return [];
        }

        global $wpdb;
        
        // Prepare placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%s'));
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submission_events
            WHERE submission_id IN ($placeholders)
            ORDER BY created_at ASC",
            $submission_ids
        );

        $events = $wpdb->get_results($query, ARRAY_A);

        // Group events by submission ID
        $grouped = [];
        foreach ($events as $event) {
            $submission_id = $event['submission_id'];
            if (!isset($grouped[$submission_id])) {
                $grouped[$submission_id] = [];
            }
            $event['event_data'] = json_decode($event['event_data'], true);
            $grouped[$submission_id][] = $event;
        }

        return $grouped;
    }

    /**
     * Get submission audit log
     *
     * @param string $submission_id Submission ID
     * @return array Audit log entries
     */
    public function get_audit_log(string $submission_id): array {
        global $wpdb;

        // Get submission details
        $submission = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, p.post_title as sorn_title
                FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions s
                LEFT JOIN {$wpdb->posts} p ON s.sorn_id = p.ID
                WHERE s.submission_id = %s",
                $submission_id
            ),
            ARRAY_A
        );

        if (!$submission) {
            return [];
        }

        // Get all events
        $events = $this->get_submission_events([$submission_id]);
        $events = $events[$submission_id] ?? [];

        // Format audit log entries
        $log = [];
        
        // Add submission creation
        $log[] = [
            'timestamp' => $submission['submitted_at'],
            'type' => 'submission_created',
            'message' => sprintf(
                __('SORN "%s" submitted to Federal Register', 'piper-privacy-sorn'),
                $submission['sorn_title']
            ),
            'data' => [
                'submission_id' => $submission_id,
                'sorn_id' => $submission['sorn_id'],
                'status' => 'submitted'
            ]
        ];

        // Add events
        foreach ($events as $event) {
            $log[] = [
                'timestamp' => $event['created_at'],
                'type' => $event['event_type'],
                'message' => $this->format_event_message($event),
                'data' => $event['event_data']
            ];
        }

        // Sort by timestamp
        usort($log, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        return $log;
    }

    /**
     * Format event message for audit log
     *
     * @param array $event Event data
     * @return string Formatted message
     */
    private function format_event_message(array $event): string {
        $data = $event['event_data'];
        
        switch ($event['event_type']) {
            case 'status_changed':
                return sprintf(
                    __('Status changed from %s to %s', 'piper-privacy-sorn'),
                    $data['old_status'],
                    $data['new_status']
                );

            case 'error':
                return sprintf(
                    __('Error occurred: %s', 'piper-privacy-sorn'),
                    $data['message']
                );

            case 'published':
                return sprintf(
                    __('Published to Federal Register with document number %s', 'piper-privacy-sorn'),
                    $data['document_number']
                );

            case 'rejected':
                return sprintf(
                    __('Rejected by Federal Register: %s', 'piper-privacy-sorn'),
                    $data['message']
                );

            case 'retry_attempted':
                return __('Submission retry attempted', 'piper-privacy-sorn');

            default:
                return $data['message'] ?? __('Event occurred', 'piper-privacy-sorn');
        }
    }

    /**
     * Export submission history
     *
     * @param array $args Filter arguments
     * @return array Export data
     */
    public function export_history(array $args = []): array {
        // Get submission history
        $history = $this->get_submission_history(array_merge(
            $args,
            ['per_page' => -1] // Get all results
        ));

        $export = [];
        
        // Format export data
        foreach ($history['submissions'] as $item) {
            $submission = $item['submission'];
            $events = $item['events'];

            $export[] = [
                'submission_id' => $submission['submission_id'],
                'sorn_title' => $submission['sorn_title'],
                'status' => $submission['status'],
                'submitted_at' => $submission['submitted_at'],
                'published_at' => $submission['published_at'],
                'document_number' => $submission['document_number'],
                'events' => array_map(function($event) {
                    return [
                        'timestamp' => $event['created_at'],
                        'type' => $event['event_type'],
                        'message' => $this->format_event_message($event)
                    ];
                }, $events)
            ];
        }

        return $export;
    }
}
