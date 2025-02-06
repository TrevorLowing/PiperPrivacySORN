<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller for SORN Manager
 */
class RestController extends WP_REST_Controller {
    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = 'piper-privacy-sorn/v1';
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void {
        // Get SORNs with filtering and pagination
        register_rest_route($this->namespace, '/sorns', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_sorns'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
                'agency' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get dashboard stats
        register_rest_route($this->namespace, '/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        // Get list of agencies
        register_rest_route($this->namespace, '/agencies', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_agencies'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        // Submit SORN to Federal Register
        register_rest_route($this->namespace, '/sorns/(?P<id>\d+)/submit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'submit_to_federal_register'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]);
    }

    /**
     * Check if user has required permissions
     *
     * @return bool Whether user has permission
     */
    public function check_permissions(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Get paginated list of SORNs
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_sorns(WP_REST_Request $request) {
        global $wpdb;

        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $agency = $request->get_param('agency');
        $status = $request->get_param('status');
        $search = $request->get_param('search');

        $table = $wpdb->prefix . 'piper_privacy_sorns';
        $where = [];
        $values = [];

        if ($agency) {
            $where[] = 'agency = %s';
            $values[] = $agency;
        }

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        if ($search) {
            $where[] = '(title LIKE %s OR system_number LIKE %s)';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table $where_clause";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_query, $values));

        // Get paginated results
        $offset = ($page - 1) * $per_page;
        $query = "SELECT * FROM $table $where_clause ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($query, $values));

        return rest_ensure_response([
            'items' => $items,
            'total' => $total,
        ]);
    }

    /**
     * Get dashboard statistics
     *
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'piper_privacy_sorns';

        $stats = [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'review'"),
            'published' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'published'"),
            'federal_register' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions"
            ),
        ];

        return rest_ensure_response($stats);
    }

    /**
     * Get list of agencies
     *
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_agencies() {
        global $wpdb;
        $table = $wpdb->prefix . 'piper_privacy_sorns';

        $agencies = $wpdb->get_results(
            "SELECT DISTINCT agency as id, agency as name FROM $table ORDER BY agency"
        );

        return rest_ensure_response($agencies);
    }

    /**
     * Submit SORN to Federal Register
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function submit_to_federal_register(WP_REST_Request $request) {
        $sorn_id = $request->get_param('id');
        
        // Get SORN data
        global $wpdb;
        $table = $wpdb->prefix . 'piper_privacy_sorns';
        $sorn = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $sorn_id
        ));

        if (!$sorn) {
            return new WP_Error(
                'not_found',
                'SORN not found',
                ['status' => 404]
            );
        }

        if ($sorn->status !== 'published') {
            return new WP_Error(
                'invalid_status',
                'SORN must be published before submitting to Federal Register',
                ['status' => 400]
            );
        }

        // Create submission
        $submission = new \PiperPrivacySorn\Models\FederalRegisterSubmission();
        $submission->set_sorn_id($sorn_id);
        
        // Submit to Federal Register
        $fr_service = new \PiperPrivacySorn\Services\FederalRegisterService();
        $result = $fr_service->submit_sorn($submission);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'submission' => $result,
        ]);
    }
}
