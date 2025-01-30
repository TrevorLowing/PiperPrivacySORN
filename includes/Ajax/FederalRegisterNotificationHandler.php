<?php
namespace PiperPrivacySorn\Ajax;

use PiperPrivacySorn\Services\FederalRegisterNotificationService;
use PiperPrivacySorn\Models\FederalRegisterSubmission;

/**
 * Handles AJAX requests for Federal Register notifications
 */
class FederalRegisterNotificationHandler {
    /**
     * Initialize the handler
     */
    public function init(): void {
        add_action('wp_ajax_fr_test_notification', [$this, 'handle_test_notification']);
    }

    /**
     * Handle test notification request
     */
    public function handle_test_notification(): void {
        // Verify nonce
        if (!check_ajax_referer('fr_test_notification', 'nonce', false)) {
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

        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        if (empty($event_type)) {
            wp_send_json_error([
                'message' => __('Invalid event type', 'piper-privacy-sorn')
            ]);
        }

        try {
            // Create a mock submission for testing
            global $wpdb;
            $submission = new FederalRegisterSubmission([
                'submission_id' => 'TEST-' . wp_generate_password(8, false),
                'sorn_id' => get_option('page_on_front'), // Use front page as test SORN
                'status' => $event_type,
                'document_number' => 'TEST-DOC-123',
                'submitted_at' => current_time('mysql'),
                'published_at' => $event_type === 'published' ? current_time('mysql') : null
            ]);

            // Create a mock event
            $event = [
                'event_type' => $event_type,
                'created_at' => current_time('mysql'),
                'event_data' => json_encode([
                    'message' => __('This is a test notification', 'piper-privacy-sorn')
                ])
            ];

            // Send test notification
            $notification_service = new FederalRegisterNotificationService();
            if ($notification_service->send_notification($submission, $event)) {
                wp_send_json_success([
                    'message' => __('Test notification sent successfully', 'piper-privacy-sorn')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to send test notification', 'piper-privacy-sorn')
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}
