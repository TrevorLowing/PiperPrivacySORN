<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use WP_Error;
use PiperPrivacySorn\Models\FederalRegisterSubmission;

/**
 * Handles interaction with the Federal Register API
 */
class FederalRegisterService {
    private const API_BASE_URL = 'https://www.federalregister.gov/api/v1';
    private string $api_key;
    private array $default_headers;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('piper_privacy_sorn_fr_api_key', '');
        $this->default_headers = [
            'Content-Type' => 'application/json',
            'X-API-Key' => $this->api_key
        ];
    }

    /**
     * Submit a SORN to the Federal Register
     *
     * @param FederalRegisterSubmission $submission Submission data
     * @return array|WP_Error Response data or error
     */
    public function submit_sorn(FederalRegisterSubmission $submission) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'Federal Register API key is not configured'
            );
        }

        $endpoint = self::API_BASE_URL . '/documents';
        $response = wp_remote_post($endpoint, [
            'headers' => $this->default_headers,
            'body' => json_encode($submission->to_api_payload()),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 201) {
            return new WP_Error(
                'submission_failed',
                $body['errors'] ?? 'Submission failed',
                ['status' => $status_code]
            );
        }

        return $body;
    }

    /**
     * Get submission status from Federal Register
     *
     * @param string $submission_id Federal Register submission ID
     * @return array|WP_Error Status data or error
     */
    public function get_submission_status(string $submission_id) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'Federal Register API key is not configured'
            );
        }

        $endpoint = self::API_BASE_URL . '/documents/' . $submission_id;
        $response = wp_remote_get($endpoint, [
            'headers' => $this->default_headers,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'status_check_failed',
                $body['errors'] ?? 'Status check failed',
                ['status' => $status_code]
            );
        }

        return $body;
    }

    /**
     * Search Federal Register documents
     *
     * @param array $params Search parameters
     * @return array|WP_Error Search results or error
     */
    public function search_documents(array $params) {
        $endpoint = self::API_BASE_URL . '/documents';
        $response = wp_remote_get($endpoint, [
            'headers' => $this->default_headers,
            'body' => $params,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'search_failed',
                $body['errors'] ?? 'Search failed',
                ['status' => $status_code]
            );
        }

        return $body;
    }

    /**
     * Retry a failed submission
     *
     * @param FederalRegisterSubmission $submission Failed submission
     * @param int $max_retries Maximum number of retry attempts
     * @param int $delay_seconds Seconds to wait between retries
     * @return array|WP_Error Response data or error
     */
    public function retry_submission(
        FederalRegisterSubmission $submission,
        int $max_retries = 3,
        int $delay_seconds = 60
    ) {
        $attempt = 1;
        $last_error = null;

        while ($attempt <= $max_retries) {
            $result = $this->submit_sorn($submission);

            if (!is_wp_error($result)) {
                return $result;
            }

            $last_error = $result;
            $attempt++;

            if ($attempt <= $max_retries) {
                sleep($delay_seconds);
            }
        }

        return new WP_Error(
            'max_retries_exceeded',
            'Maximum retry attempts exceeded',
            ['last_error' => $last_error]
        );
    }
}
