<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Ajax;

use PiperPrivacySorn\Services\FederalRegisterApi;
use PiperPrivacySorn\Services\SornManager;

/**
 * Handles AJAX requests for Federal Register integration
 */
class FederalRegisterAjax {
    /**
     * @var FederalRegisterApi
     */
    private FederalRegisterApi $federal_register_api;

    /**
     * @var SornManager
     */
    private SornManager $sorn_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->federal_register_api = new FederalRegisterApi();
        $this->sorn_manager = new SornManager();

        // Register AJAX handlers
        add_action('wp_ajax_search_federal_register', [$this, 'handle_search']);
        add_action('wp_ajax_get_federal_register_document', [$this, 'handle_get_document']);
        add_action('wp_ajax_import_federal_register_document', [$this, 'handle_import_document']);
        add_action('wp_ajax_submit_to_federal_register', [$this, 'handle_submit_sorn']);
        add_action('wp_ajax_get_federal_register_agencies', [$this, 'handle_get_agencies']);
        add_action('wp_ajax_get_submission_status', [$this, 'handle_get_submission_status']);
        add_action('wp_ajax_get_submissions', [$this, 'handle_get_submissions']);
    }

    /**
     * Handle Federal Register search request
     */
    public function handle_search(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('piper_privacy_sorn_fr_search', 'nonce', false)) {
                throw new \Exception(__('Invalid security token', 'piper-privacy-sorn'));
            }

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'piper-privacy-sorn'));
            }

            // Get and validate search parameters
            $params = $this->validate_search_params($_POST['data'] ?? []);

            // Perform search
            $results = $this->federal_register_api->search_sorns($params);

            // Format results
            $formatted_results = $this->format_search_results($results);

            wp_send_json_success($formatted_results);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle get document request
     */
    public function handle_get_document(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('piper_privacy_sorn_fr_search', 'nonce', false)) {
                throw new \Exception(__('Invalid security token', 'piper-privacy-sorn'));
            }

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'piper-privacy-sorn'));
            }

            // Get and validate document number
            $document_number = sanitize_text_field($_POST['document_number'] ?? '');
            if (empty($document_number)) {
                throw new \Exception(__('Document number is required', 'piper-privacy-sorn'));
            }

            // Get document
            $document = $this->federal_register_api->get_document($document_number);

            wp_send_json_success($document);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle import document request
     */
    public function handle_import_document(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('piper_privacy_sorn_fr_search', 'nonce', false)) {
                throw new \Exception(__('Invalid security token', 'piper-privacy-sorn'));
            }

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'piper-privacy-sorn'));
            }

            // Get and validate document number
            $document_number = sanitize_text_field($_POST['document_number'] ?? '');
            if (empty($document_number)) {
                throw new \Exception(__('Document number is required', 'piper-privacy-sorn'));
            }

            // Get document from Federal Register
            $document = $this->federal_register_api->get_document($document_number);

            // Parse document into SORN format
            $sorn_data = $this->parse_fr_document($document);

            // Create SORN
            $sorn_id = $this->sorn_manager->create_sorn($sorn_data);

            wp_send_json_success([
                'sorn_id' => $sorn_id,
                'message' => __('SORN imported successfully', 'piper-privacy-sorn')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle submit SORN request
     */
    public function handle_submit_sorn(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('piper_privacy_sorn_fr_submit', 'nonce', false)) {
                throw new \Exception(__('Invalid security token', 'piper-privacy-sorn'));
            }

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'piper-privacy-sorn'));
            }

            // Get and validate SORN ID
            $sorn_id = intval($_POST['sorn_id'] ?? 0);
            if ($sorn_id <= 0) {
                throw new \Exception(__('Invalid SORN ID', 'piper-privacy-sorn'));
            }

            // Get SORN data
            $sorn_data = $this->sorn_manager->get_sorn($sorn_id);
            if (!$sorn_data) {
                throw new \Exception(__('SORN not found', 'piper-privacy-sorn'));
            }

            // Format SORN for Federal Register
            $fr_data = $this->federal_register_api->format_sorn_for_submission($sorn_data);

            // Submit to Federal Register
            $submission = $this->federal_register_api->submit_sorn($fr_data);

            // Save submission details
            $this->save_submission([
                'sorn_id' => $sorn_id,
                'submission_id' => $submission['id'],
                'document_number' => $submission['document_number'],
                'status' => $submission['status'],
                'submitted_at' => current_time('mysql')
            ]);

            wp_send_json_success([
                'submission_id' => $submission['id'],
                'message' => __('SORN submitted successfully', 'piper-privacy-sorn')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle get agencies request
     */
    public function handle_get_agencies(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('piper_privacy_sorn_fr_search', 'nonce', false)) {
                throw new \Exception(__('Invalid security token', 'piper-privacy-sorn'));
            }

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'piper-privacy-sorn'));
            }

            // Get agencies
            $agencies = $this->federal_register_api->get_agencies();

            wp_send_json_success($agencies);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle get submission status request
     */
    public function handle_get_submission_status(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('piper_privacy_sorn_fr_submit', 'nonce', false)) {
                throw new \Exception(__('Invalid security token', 'piper-privacy-sorn'));
            }

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'piper-privacy-sorn'));
            }

            // Get and validate submission ID
            $submission_id = sanitize_text_field($_POST['submission_id'] ?? '');
            if (empty($submission_id)) {
                throw new \Exception(__('Submission ID is required', 'piper-privacy-sorn'));
            }

            // Get status from Federal Register
            $status = $this->federal_register_api->get_submission_status($submission_id);

            // Update submission status in database
            $this->update_submission_status($submission_id, $status['status']);

            wp_send_json_success($status);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle get submissions request
     */
    public function handle_get_submissions(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('piper_privacy_sorn_fr_submit', 'nonce', false)) {
                throw new \Exception(__('Invalid security token', 'piper-privacy-sorn'));
            }

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Insufficient permissions', 'piper-privacy-sorn'));
            }

            // Get submissions from database
            $submissions = $this->get_submissions();

            wp_send_json_success($submissions);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate search parameters
     *
     * @param array $data Raw search parameters
     * @return array Validated parameters
     */
    private function validate_search_params(array $data): array {
        parse_str($data, $params);

        return [
            'conditions[type][]' => 'NOTICE',
            'conditions[agencies][]' => sanitize_text_field($params['agency'] ?? ''),
            'conditions[term]' => sanitize_text_field($params['term'] ?? ''),
            'conditions[publication_date][gte]' => sanitize_text_field($params['date_start'] ?? ''),
            'conditions[publication_date][lte]' => sanitize_text_field($params['date_end'] ?? ''),
            'per_page' => 20,
            'page' => max(1, intval($params['page'] ?? 1)),
            'order' => 'newest'
        ];
    }

    /**
     * Format search results
     *
     * @param array $results Raw results from API
     * @return array Formatted results
     */
    private function format_search_results(array $results): array {
        return [
            'results' => array_map(function($result) {
                return [
                    'title' => $result['title'],
                    'document_number' => $result['document_number'],
                    'agency_names' => $result['agency_names'],
                    'publication_date' => $result['publication_date'],
                    'abstract' => $result['abstract'],
                    'html_url' => $result['html_url']
                ];
            }, $results['results']),
            'total_pages' => $results['total_pages'],
            'current_page' => $results['current_page'],
            'total_results' => $results['total_results']
        ];
    }

    /**
     * Parse Federal Register document into SORN format
     *
     * @param array $document Federal Register document
     * @return array SORN data
     */
    private function parse_fr_document(array $document): array {
        // Extract SORN data from Federal Register document format
        // This is a placeholder - implement actual parsing logic
        return [
            'title' => $document['title'],
            'agency' => $document['agency_names'][0],
            'identifier' => $document['document_number'],
            'system_name' => $document['system_name'] ?? '',
            'purpose' => $document['abstract'] ?? '',
            'categories' => $document['categories'] ?? '',
            'authority' => $document['authority'] ?? '',
            'routine_uses' => $document['routine_uses'] ?? '',
            'retention' => $document['retention'] ?? '',
            'safeguards' => $document['safeguards'] ?? '',
            'access_procedures' => $document['access_procedures'] ?? '',
            'contesting_procedures' => $document['contesting_procedures'] ?? '',
            'notification_procedures' => $document['notification_procedures'] ?? '',
            'exemptions' => $document['exemptions'] ?? '',
            'history' => $document['history'] ?? '',
            'source' => 'federal_register',
            'source_url' => $document['html_url'],
            'source_id' => $document['document_number'],
            'imported_at' => current_time('mysql')
        ];
    }

    /**
     * Save Federal Register submission
     *
     * @param array $data Submission data
     * @return int Submission ID
     */
    private function save_submission(array $data): int {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorn_fr_submissions',
            [
                'sorn_id' => $data['sorn_id'],
                'submission_id' => $data['submission_id'],
                'document_number' => $data['document_number'],
                'status' => $data['status'],
                'submitted_at' => $data['submitted_at']
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Update submission status
     *
     * @param string $submission_id Federal Register submission ID
     * @param string $status New status
     * @return bool Success
     */
    private function update_submission_status(string $submission_id, string $status): bool {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'piper_privacy_sorn_fr_submissions',
            ['status' => $status],
            ['submission_id' => $submission_id],
            ['%s'],
            ['%s']
        ) !== false;
    }

    /**
     * Get submissions from database
     *
     * @param array $args Query arguments
     * @return array Submissions
     */
    private function get_submissions(array $args = []): array {
        global $wpdb;

        $defaults = [
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);

        $query = "
            SELECT s.*, n.title as sorn_title
            FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions s
            LEFT JOIN {$wpdb->prefix}piper_privacy_sorns n ON s.sorn_id = n.id
            ORDER BY s.{$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, $args['limit'], $args['offset']),
            ARRAY_A
        );

        return $results ?: [];
    }
}
