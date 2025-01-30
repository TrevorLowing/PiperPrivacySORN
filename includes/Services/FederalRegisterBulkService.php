<?php
namespace PiperPrivacySorn\Services;

/**
 * Handles bulk operations for Federal Register submissions
 */
class FederalRegisterBulkService {
    /**
     * Retry multiple failed submissions
     *
     * @param array $submission_ids Array of submission IDs to retry
     * @return array Array of results with success/error messages
     */
    public function retry_submissions(array $submission_ids): array {
        $results = [];
        $submission_service = new FederalRegisterSubmissionService();

        foreach ($submission_ids as $submission_id) {
            try {
                $submission = \PiperPrivacySorn\Models\FederalRegisterSubmission::find($submission_id);
                if (!$submission) {
                    $results[$submission_id] = [
                        'success' => false,
                        'message' => __('Submission not found', 'piper-privacy-sorn')
                    ];
                    continue;
                }

                $submission_service->retry_submission($submission);
                $results[$submission_id] = [
                    'success' => true,
                    'message' => __('Submission queued for retry', 'piper-privacy-sorn')
                ];
            } catch (\Exception $e) {
                $results[$submission_id] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Export submissions to CSV
     *
     * @param array $submission_ids Array of submission IDs to export
     * @return string Path to the generated CSV file
     */
    public function export_to_csv(array $submission_ids): string {
        $uploads = wp_upload_dir();
        $filename = sprintf('federal-register-export-%s.csv', date('Y-m-d-His'));
        $filepath = $uploads['path'] . '/' . $filename;

        $fp = fopen($filepath, 'w');
        if ($fp === false) {
            throw new \Exception(__('Unable to create export file', 'piper-privacy-sorn'));
        }

        // Write CSV header
        fputcsv($fp, [
            'Submission ID',
            'SORN Title',
            'Status',
            'Document Number',
            'Submitted Date',
            'Published Date',
            'Last Event',
            'Last Event Date'
        ]);

        foreach ($submission_ids as $submission_id) {
            $submission = \PiperPrivacySorn\Models\FederalRegisterSubmission::find($submission_id);
            if (!$submission) {
                continue;
            }

            $sorn = get_post($submission->get_sorn_id());
            $last_event = $submission->get_latest_event();

            fputcsv($fp, [
                $submission->get_submission_id(),
                $sorn ? $sorn->post_title : 'SORN Deleted',
                $submission->get_status(),
                $submission->get_document_number() ?: 'N/A',
                wp_date(get_option('date_format'), strtotime($submission->get_submitted_at())),
                $submission->get_published_at() ? wp_date(get_option('date_format'), strtotime($submission->get_published_at())) : 'N/A',
                $last_event ? $last_event['event_type'] : 'N/A',
                $last_event ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_event['created_at'])) : 'N/A'
            ]);
        }

        fclose($fp);

        // Return the URL to the exported file
        return $uploads['url'] . '/' . $filename;
    }

    /**
     * Archive old submissions
     *
     * @param array $submission_ids Array of submission IDs to archive
     * @return array Array of results with success/error messages
     */
    public function archive_submissions(array $submission_ids): array {
        $results = [];
        
        foreach ($submission_ids as $submission_id) {
            try {
                $submission = \PiperPrivacySorn\Models\FederalRegisterSubmission::find($submission_id);
                if (!$submission) {
                    $results[$submission_id] = [
                        'success' => false,
                        'message' => __('Submission not found', 'piper-privacy-sorn')
                    ];
                    continue;
                }

                // Only archive completed or failed submissions
                if (!in_array($submission->get_status(), ['published', 'rejected', 'error'])) {
                    $results[$submission_id] = [
                        'success' => false,
                        'message' => __('Can only archive completed or failed submissions', 'piper-privacy-sorn')
                    ];
                    continue;
                }

                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'piper_privacy_sorn_fr_submission_archives',
                    [
                        'submission_id' => $submission->get_submission_id(),
                        'sorn_id' => $submission->get_sorn_id(),
                        'status' => $submission->get_status(),
                        'document_number' => $submission->get_document_number(),
                        'submitted_at' => $submission->get_submitted_at(),
                        'published_at' => $submission->get_published_at(),
                        'archived_at' => current_time('mysql'),
                        'data' => json_encode([
                            'events' => $submission->get_events(),
                            'metadata' => $submission->get_metadata()
                        ])
                    ],
                    ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
                );

                // Delete the original submission and its events
                $submission->delete();

                $results[$submission_id] = [
                    'success' => true,
                    'message' => __('Submission archived successfully', 'piper-privacy-sorn')
                ];
            } catch (\Exception $e) {
                $results[$submission_id] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
