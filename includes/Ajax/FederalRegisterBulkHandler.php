<?php
namespace PiperPrivacySorn\Ajax;

use PiperPrivacySorn\Services\FederalRegisterBulkService;

/**
 * Handles AJAX requests for Federal Register bulk actions
 */
class FederalRegisterBulkHandler {
    /**
     * Initialize the handler
     */
    public function init(): void {
        add_action('wp_ajax_fr_bulk_action', [$this, 'handle_bulk_action']);
    }

    /**
     * Handle bulk action requests
     */
    public function handle_bulk_action(): void {
        // Verify nonce
        if (!check_ajax_referer('fr_bulk_action', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Invalid security token', 'piper-privacy-sorn')
            ]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action', 'piper-privacy-sorn')
            ]);
        }

        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $submission_ids = array_map('intval', $_POST['submission_ids'] ?? []);

        if (empty($action) || empty($submission_ids)) {
            wp_send_json_error([
                'message' => __('Invalid request parameters', 'piper-privacy-sorn')
            ]);
        }

        try {
            $bulk_service = new FederalRegisterBulkService();

            switch ($action) {
                case 'retry':
                    $results = $bulk_service->retry_submissions($submission_ids);
                    wp_send_json_success(['results' => $results]);
                    break;

                case 'archive':
                    $results = $bulk_service->archive_submissions($submission_ids);
                    wp_send_json_success(['results' => $results]);
                    break;

                case 'export':
                    $download_url = $bulk_service->export_to_csv($submission_ids);
                    wp_send_json_success([
                        'download_url' => $download_url,
                        'message' => __('Export completed successfully', 'piper-privacy-sorn')
                    ]);
                    break;

                default:
                    wp_send_json_error([
                        'message' => __('Invalid action', 'piper-privacy-sorn')
                    ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}
