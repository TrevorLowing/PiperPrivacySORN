<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use WP_Error;

/**
 * Handles interaction with the GPT Trainer API for AI-powered features
 */
class AiService {
    private const API_BASE_URL = 'https://api.gpttrainer.ai/v1';
    private string $api_key;
    private array $default_headers;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('piper_privacy_sorn_gpt_api_key', '');
        $this->default_headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key
        ];
    }

    /**
     * Generate SORN draft based on provided parameters
     *
     * @param array $params Draft generation parameters
     * @return array|WP_Error Generated draft or error
     */
    public function generate_draft(array $params) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'GPT Trainer API key is not configured'
            );
        }

        $endpoint = self::API_BASE_URL . '/generate/sorn';
        $response = wp_remote_post($endpoint, [
            'headers' => $this->default_headers,
            'body' => json_encode($params),
            'timeout' => 60, // Longer timeout for generation
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'generation_failed',
                $body['error'] ?? 'Draft generation failed',
                ['status' => $status_code]
            );
        }

        return $body;
    }

    /**
     * Analyze SORN content for compliance and completeness
     *
     * @param string $content SORN content to analyze
     * @param array $requirements Specific requirements to check
     * @return array|WP_Error Analysis results or error
     */
    public function analyze_sorn(string $content, array $requirements = []) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'GPT Trainer API key is not configured'
            );
        }

        $endpoint = self::API_BASE_URL . '/analyze/sorn';
        $response = wp_remote_post($endpoint, [
            'headers' => $this->default_headers,
            'body' => json_encode([
                'content' => $content,
                'requirements' => $requirements
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'analysis_failed',
                $body['error'] ?? 'SORN analysis failed',
                ['status' => $status_code]
            );
        }

        return $body;
    }

    /**
     * Get suggestions for improving SORN content
     *
     * @param string $content Current SORN content
     * @return array|WP_Error Improvement suggestions or error
     */
    public function get_suggestions(string $content) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'GPT Trainer API key is not configured'
            );
        }

        $endpoint = self::API_BASE_URL . '/suggest/sorn';
        $response = wp_remote_post($endpoint, [
            'headers' => $this->default_headers,
            'body' => json_encode(['content' => $content]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'suggestions_failed',
                $body['error'] ?? 'Failed to get suggestions',
                ['status' => $status_code]
            );
        }

        return $body;
    }

    /**
     * Search through SORNs using natural language
     *
     * @param string $query Search query
     * @param array $filters Optional search filters
     * @return array|WP_Error Search results or error
     */
    public function semantic_search(string $query, array $filters = []) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'GPT Trainer API key is not configured'
            );
        }

        $endpoint = self::API_BASE_URL . '/search/sorn';
        $response = wp_remote_post($endpoint, [
            'headers' => $this->default_headers,
            'body' => json_encode([
                'query' => $query,
                'filters' => $filters
            ]),
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
                $body['error'] ?? 'Search failed',
                ['status' => $status_code]
            );
        }

        return $body;
    }
}
