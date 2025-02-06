<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use WP_Error;

/**
 * Service for downloading and storing SORNs from Federal Register
 */
class SornDownloadService {
    private FederalRegisterService $fr_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->fr_service = new FederalRegisterService();
    }

    /**
     * Download all SORNs from Federal Register
     *
     * @param string $start_date Optional start date in YYYY-MM-DD format
     * @return array|WP_Error Download statistics or error
     */
    public function download_all_sorns(string $start_date = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'piper_privacy_sorns';
        $stats = [
            'total_found' => 0,
            'downloaded' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        $per_page = 50;
        $page = 1;
        
        do {
            // Search Federal Register for SORNs
            $params = [
                'per_page' => $per_page,
                'page' => $page,
                'conditions' => [
                    'type' => 'SORN',
                    'agencies' => []  // Empty array means all agencies
                ]
            ];

            if ($start_date) {
                $params['conditions']['publication_date'] = [
                    'gte' => $start_date
                ];
            }

            $results = $this->fr_service->search_documents($params);
            
            if (is_wp_error($results)) {
                return $results;
            }

            $stats['total_found'] = $results['count'] ?? 0;
            $documents = $results['results'] ?? [];

            foreach ($documents as $doc) {
                // Check if SORN already exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE document_number = %s",
                    $doc['document_number']
                ));

                if ($exists) {
                    $stats['skipped']++;
                    continue;
                }

                // Get full document details
                $details = $this->fr_service->get_document_details($doc['document_number']);
                if (is_wp_error($details)) {
                    $stats['errors']++;
                    continue;
                }

                // Extract SORN data
                $sorn_data = $this->extract_sorn_data($details);
                
                // Insert into database
                $result = $wpdb->insert($table, [
                    'title' => $sorn_data['title'],
                    'agency' => $sorn_data['agency'],
                    'system_number' => $sorn_data['system_number'],
                    'content' => $sorn_data['content'],
                    'metadata' => json_encode($sorn_data['metadata']),
                    'document_number' => $doc['document_number'],
                    'status' => 'published',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'last_published_at' => $doc['publication_date']
                ]);

                if ($result) {
                    $stats['downloaded']++;
                } else {
                    $stats['errors']++;
                }
            }

            $page++;
        } while (count($documents) === $per_page);

        return $stats;
    }

    /**
     * Extract structured SORN data from Federal Register document
     *
     * @param array $document Federal Register document
     * @return array Structured SORN data
     */
    private function extract_sorn_data(array $document): array {
        $content = $document['body'] ?? '';
        
        // Extract system number using regex
        preg_match('/SYSTEM NAME AND NUMBER:\s*([^\.]+)/i', $content, $system_matches);
        $system_number = trim($system_matches[1] ?? '');
        
        // Clean up system number
        $system_number = preg_replace('/^[^A-Za-z0-9]+/', '', $system_number);
        
        return [
            'title' => $document['title'] ?? '',
            'agency' => $document['agency_names'][0] ?? '',
            'system_number' => $system_number,
            'content' => $content,
            'metadata' => [
                'citation' => $document['citation'] ?? '',
                'type' => $document['type'] ?? '',
                'action' => $document['action'] ?? '',
                'dates' => $document['dates'] ?? [],
                'agencies' => $document['agencies'] ?? []
            ]
        ];
    }

    /**
     * Schedule regular SORN downloads
     */
    public function schedule_downloads(): void {
        if (!wp_next_scheduled('piper_privacy_sorn_download')) {
            wp_schedule_event(time(), 'daily', 'piper_privacy_sorn_download');
        }
    }

    /**
     * Unschedule SORN downloads
     */
    public function unschedule_downloads(): void {
        wp_clear_scheduled_hook('piper_privacy_sorn_download');
    }
}
