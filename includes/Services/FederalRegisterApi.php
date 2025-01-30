<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

/**
 * Federal Register API Integration Service
 * 
 * Handles all interactions with the Federal Register API
 * API Documentation: https://www.federalregister.gov/developers/api/v1
 */
class FederalRegisterApi {
    /**
     * Base URL for the Federal Register API
     *
     * @var string
     */
    private string $base_url = 'https://www.federalregister.gov/api/v1';

    /**
     * API key for authentication
     *
     * @var string|null
     */
    private ?string $api_key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('federal_register_api_key');
    }

    /**
     * Search for SORNs in the Federal Register
     *
     * @param array $params Search parameters
     * @return array Search results
     * @throws \Exception If API request fails
     */
    public function search_sorns(array $params = []): array {
        $default_params = [
            'conditions[type][]' => 'NOTICE',
            'conditions[agencies][]' => '',
            'conditions[term]' => '',
            'conditions[publication_date][gte]' => '',
            'conditions[publication_date][lte]' => '',
            'per_page' => 20,
            'page' => 1,
            'order' => 'newest'
        ];

        $params = array_merge($default_params, $params);
        
        return $this->make_request('GET', '/documents', $params);
    }

    /**
     * Get a specific document from the Federal Register
     *
     * @param string $document_number Federal Register document number
     * @return array Document details
     * @throws \Exception If API request fails
     */
    public function get_document(string $document_number): array {
        return $this->make_request('GET', "/documents/{$document_number}");
    }

    /**
     * Submit a new SORN to the Federal Register
     *
     * @param array $data SORN data
     * @return array Submission response
     * @throws \Exception If submission fails
     */
    public function submit_sorn(array $data): array {
        $required_fields = [
            'title',
            'agency_id',
            'document_type',
            'abstract',
            'dates',
            'addresses',
            'contact',
            'supplementary_info'
        ];

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException(
                    sprintf(__('Missing required field: %s', 'piper-privacy-sorn'), $field)
                );
            }
        }

        return $this->make_request('POST', '/documents', $data);
    }

    /**
     * Get the status of a submitted document
     *
     * @param string $submission_id Submission ID
     * @return array Status details
     * @throws \Exception If status check fails
     */
    public function get_submission_status(string $submission_id): array {
        return $this->make_request('GET', "/documents/submissions/{$submission_id}");
    }

    /**
     * Get list of agencies
     *
     * @return array List of agencies
     * @throws \Exception If API request fails
     */
    public function get_agencies(): array {
        return $this->make_request('GET', '/agencies');
    }

    /**
     * Make an HTTP request to the Federal Register API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array  $params Request parameters
     * @return array Response data
     * @throws \Exception If request fails
     */
    private function make_request(string $method, string $endpoint, array $params = []): array {
        $url = $this->base_url . $endpoint;
        
        // Add API key if available
        if ($this->api_key) {
            $params['api_key'] = $this->api_key;
        }

        // Build request arguments
        $args = [
            'method' => $method,
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'SORN Manager WordPress Plugin/' . PIPER_PRIVACY_SORN_VERSION
            ],
            'cookies' => []
        ];

        // Add parameters based on method
        if ($method === 'GET') {
            $url = add_query_arg($params, $url);
        } else {
            $args['body'] = wp_json_encode($params);
        }

        // Make request
        $response = wp_remote_request($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new \Exception(
                sprintf(
                    __('Federal Register API request failed with status %d: %s', 'piper-privacy-sorn'),
                    $response_code,
                    wp_remote_retrieve_response_message($response)
                )
            );
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(__('Invalid JSON response from Federal Register API', 'piper-privacy-sorn'));
        }

        return $data;
    }

    /**
     * Format SORN data for Federal Register submission
     *
     * @param array $sorn_data SORN data from database
     * @return array Formatted data for Federal Register
     */
    public function format_sorn_for_submission(array $sorn_data): array {
        return [
            'title' => $sorn_data['title'],
            'type' => 'NOTICE',
            'agency_id' => $sorn_data['agency'],
            'document_type' => 'Privacy Act System of Records Notice',
            'abstract' => $this->generate_abstract($sorn_data),
            'dates' => [
                'comments' => [
                    'deadline' => date('Y-m-d', strtotime('+30 days'))
                ],
                'effective' => date('Y-m-d', strtotime('+40 days'))
            ],
            'addresses' => $this->format_addresses($sorn_data),
            'contact' => $this->format_contact($sorn_data),
            'supplementary_info' => $this->format_supplementary_info($sorn_data),
            'action' => 'Notice of a New System of Records.',
            'matter' => $this->format_matter($sorn_data)
        ];
    }

    /**
     * Generate abstract from SORN data
     *
     * @param array $sorn_data SORN data
     * @return string Formatted abstract
     */
    private function generate_abstract(array $sorn_data): string {
        return sprintf(
            'In accordance with the Privacy Act of 1974, as amended, %s proposes to establish a new system of records titled, "%s." This system of records maintains information %s.',
            esc_html($sorn_data['agency']),
            esc_html($sorn_data['title']),
            wp_strip_all_tags($sorn_data['purpose'])
        );
    }

    /**
     * Format addresses section
     *
     * @param array $sorn_data SORN data
     * @return array Formatted addresses
     */
    private function format_addresses(array $sorn_data): array {
        return [
            'comments' => [
                'instructions' => 'You may submit comments, identified by docket number [AGENCY-YEAR-####], by any of the following methods:',
                'methods' => [
                    [
                        'type' => 'web',
                        'url' => 'https://www.regulations.gov'
                    ],
                    [
                        'type' => 'mail',
                        'address' => $sorn_data['agency_address'] ?? ''
                    ]
                ]
            ]
        ];
    }

    /**
     * Format contact information
     *
     * @param array $sorn_data SORN data
     * @return array Formatted contact info
     */
    private function format_contact(array $sorn_data): array {
        return [
            'name' => $sorn_data['contact_name'] ?? '',
            'title' => $sorn_data['contact_title'] ?? '',
            'phone' => $sorn_data['contact_phone'] ?? '',
            'email' => $sorn_data['contact_email'] ?? '',
            'fax' => $sorn_data['contact_fax'] ?? ''
        ];
    }

    /**
     * Format supplementary information
     *
     * @param array $sorn_data SORN data
     * @return string Formatted supplementary info
     */
    private function format_supplementary_info(array $sorn_data): string {
        $sections = [
            'I. Background' => $sorn_data['background'] ?? '',
            'II. Privacy Act' => 'This notice is given pursuant to the Privacy Act of 1974, as amended (5 U.S.C. 552a).',
            'III. System Name and Number' => sprintf(
                '%s: %s',
                esc_html($sorn_data['system_name']),
                esc_html($sorn_data['identifier'])
            ),
            'IV. Security Classification' => $sorn_data['security_classification'] ?? 'Unclassified',
            'V. System Location' => $sorn_data['system_location'] ?? '',
            'VI. Categories of Individuals' => $sorn_data['categories'] ?? '',
            'VII. Categories of Records' => $sorn_data['record_categories'] ?? '',
            'VIII. Record Source Categories' => $sorn_data['record_sources'] ?? '',
            'IX. Routine Uses' => $sorn_data['routine_uses'] ?? '',
            'X. Storage' => $sorn_data['storage'] ?? '',
            'XI. Retrievability' => $sorn_data['retrievability'] ?? '',
            'XII. Safeguards' => $sorn_data['safeguards'] ?? '',
            'XIII. Retention and Disposal' => $sorn_data['retention'] ?? '',
            'XIV. System Manager' => $sorn_data['system_manager'] ?? '',
            'XV. Notification Procedure' => $sorn_data['notification_procedures'] ?? '',
            'XVI. Record Access Procedures' => $sorn_data['access_procedures'] ?? '',
            'XVII. Contesting Record Procedures' => $sorn_data['contesting_procedures'] ?? '',
            'XVIII. Exemptions Promulgated' => $sorn_data['exemptions'] ?? ''
        ];

        $output = '';
        foreach ($sections as $title => $content) {
            if (!empty($content)) {
                $output .= "\n\n" . $title . "\n\n" . wp_strip_all_tags($content);
            }
        }

        return trim($output);
    }

    /**
     * Format matter section
     *
     * @param array $sorn_data SORN data
     * @return string Formatted matter
     */
    private function format_matter(array $sorn_data): string {
        return sprintf(
            "SYSTEM NAME AND NUMBER:\n\n%s: %s\n\nSECURITY CLASSIFICATION:\n\n%s\n\n%s",
            esc_html($sorn_data['system_name']),
            esc_html($sorn_data['identifier']),
            esc_html($sorn_data['security_classification'] ?? 'Unclassified'),
            wp_strip_all_tags($this->format_supplementary_info($sorn_data))
        );
    }
}
