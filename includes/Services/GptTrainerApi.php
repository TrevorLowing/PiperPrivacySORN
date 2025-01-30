<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

/**
 * GPT Trainer API Integration for WordPress
 *
 * @package PiperPrivacySorn
 * @subpackage Services
 */
class GptTrainerApi {
    /**
     * Base URL for the GPT Trainer API
     *
     * @var string
     */
    protected string $base_url = 'https://app.gpt-trainer.com/api/v1';

    /**
     * API token for authentication
     *
     * @var string
     */
    protected string $api_token;

    /**
     * Whether the API is in test mode
     *
     * @var bool
     */
    public bool $is_test_mode;

    /**
     * Constructor
     *
     * @throws \RuntimeException If API token is not configured.
     */
    public function __construct() {
        $this->api_token = get_option('gpt_trainer_api_token');
        if (empty($this->api_token)) {
            /* translators: %s: Option name for API token */
            $message = sprintf(__('API Token not configured in option: %s', 'piper-privacy-sorn'), 'gpt_trainer_api_token');
            do_action('piper_privacy_sorn_api_error', 'token_missing', $message);
            throw new \RuntimeException($message);
        }
        
        $this->is_test_mode = $this->api_token === 'test_token';
        
        if (WP_DEBUG) {
            $this->log_debug(sprintf(
                'API Token configured - Length: %d, Starts with: %s..., Base URL: %s, Test Mode: %s',
                strlen($this->api_token),
                substr($this->api_token, 0, 4),
                $this->base_url,
                $this->is_test_mode ? 'true' : 'false'
            ));
        }
    }

    /**
     * Get request headers with authentication
     *
     * @return array<string, string> Headers for API requests
     */
    protected function get_request_headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Handle API errors consistently
     *
     * @param \Exception $e        The exception that occurred
     * @param string     $operation The operation being performed
     * @throws \Exception Rethrows the exception with additional context
     */
    protected function handle_api_error(\Exception $e, string $operation): void {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];

        if (is_wp_error($e)) {
            $context['wp_error'] = $e->get_error_messages();
        }

        $this->log_error(sprintf(
            'API %s failed: %s',
            $operation,
            wp_json_encode($context)
        ));

        do_action('piper_privacy_sorn_api_error', $operation, $e->getMessage(), $context);

        throw new \Exception(
            sprintf("API Error (%s): %s", $operation, $e->getMessage()),
            $e->getCode(),
            $e
        );
    }

    /**
     * Make an HTTP request using WordPress HTTP API
     *
     * @param string $method   HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data     Optional request data
     * @return array Response data
     * @throws \Exception On request failure
     */
    protected function make_http_request(string $method, string $endpoint, array $data = null): array {
        $url = $this->base_url . $endpoint;
        $args = [
            'method' => $method,
            'headers' => $this->get_request_headers(),
            'timeout' => 30,
        ];

        if ($data !== null) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            throw new \Exception("HTTP Error: " . $status_code . " - " . $body);
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Log debug message if WP_DEBUG is enabled
     *
     * @param string $message Debug message
     * @param array  $context Optional context data
     */
    protected function log_debug_message(string $message, array $context = []): void {
        if (!WP_DEBUG) {
            return;
        }

        error_log(sprintf(
            '[GPT Trainer API Debug] %s %s',
            $message,
            !empty($context) ? ' - ' . wp_json_encode($context) : ''
        ));
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array  $context Optional context data
     */
    protected function log_error_message(string $message, array $context = []): void {
        error_log(sprintf(
            '[GPT Trainer API Error] %s %s',
            $message,
            !empty($context) ? ' - ' . wp_json_encode($context) : ''
        ));
    }

    /**
     * Create a file-based data source
     *
     * @param string $name File name
     * @param array  $file WordPress uploaded file array
     * @param array  $tags Optional tags
     * @return array Response data
     * @throws \Exception On error
     */
    public function create_file_data_source(string $name, array $file, array $tags = []): array {
        try {
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new \Exception(__('Invalid file upload', 'piper-privacy-sorn'));
            }

            // Validate file type
            $allowed_types = apply_filters('piper_privacy_sorn_allowed_file_types', [
                'text/plain',
                'application/pdf',
                'application/json'
            ]);

            if (!in_array($file['type'], $allowed_types, true)) {
                throw new \Exception(sprintf(
                    /* translators: %s: File MIME type */
                    __('Invalid file type: %s', 'piper-privacy-sorn'),
                    esc_html($file['type'])
                ));
            }

            $file_content = file_get_contents($file['tmp_name']);
            if ($file_content === false) {
                throw new \Exception(__('Failed to read uploaded file', 'piper-privacy-sorn'));
            }

            $data = [
                'name' => sanitize_text_field($name),
                'type' => 'file',
                'content' => base64_encode($file_content),
                'filename' => sanitize_file_name($file['name']),
                'mime_type' => sanitize_text_field($file['type']),
                'tags' => array_map('sanitize_text_field', $tags)
            ];

            return $this->create_data_source($data);
        } catch (\Exception $e) {
            $this->handle_api_error($e, "create_file_data_source");
            return []; // Satisfy return type
        }
    }

    /**
     * Create a URL-based data source
     *
     * @param string $name Source name
     * @param string $url  Source URL
     * @param array  $tags Optional tags
     * @return array Response data
     * @throws \Exception On error
     */
    public function create_url_data_source(string $name, string $url, array $tags = []): array {
        try {
            if (!wp_http_validate_url($url)) {
                throw new \Exception(__('Invalid URL provided', 'piper-privacy-sorn'));
            }

            $data = [
                'name' => sanitize_text_field($name),
                'type' => 'url',
                'url' => esc_url_raw($url),
                'tags' => array_map('sanitize_text_field', $tags)
            ];

            return $this->create_data_source($data);
        } catch (\Exception $e) {
            $this->handle_api_error($e, "create_url_data_source");
            return []; // Satisfy return type
        }
    }

    /**
     * Create a Q&A-based data source
     *
     * @param string $name    Source name
     * @param array  $qaPairs Q&A pairs
     * @param array  $tags    Optional tags
     * @return array Response data
     * @throws \Exception On error
     */
    public function create_qa_data_source(string $name, array $qaPairs, array $tags = []): array {
        try {
            // Validate and sanitize Q&A pairs
            $sanitized_pairs = [];
            foreach ($qaPairs as $pair) {
                if (!isset($pair['question'], $pair['answer'])) {
                    throw new \Exception(__('Invalid Q&A pair format', 'piper-privacy-sorn'));
                }
                $sanitized_pairs[] = [
                    'question' => sanitize_text_field($pair['question']),
                    'answer' => wp_kses_post($pair['answer'])
                ];
            }

            $data = [
                'name' => sanitize_text_field($name),
                'type' => 'qa',
                'qa_pairs' => $sanitized_pairs,
                'tags' => array_map('sanitize_text_field', $tags)
            ];

            return $this->create_data_source($data);
        } catch (\Exception $e) {
            $this->handle_api_error($e, "create_qa_data_source");
            return []; // Satisfy return type
        }
    }

    /**
     * Create a data source
     *
     * @param array $data Source data
     * @return array Response data
     * @throws \Exception On error
     */
    protected function create_data_source(array $data): array {
        try {
            $this->log_debug_message('Creating data source', ['type' => $data['type']]);

            if ($this->is_test_mode) {
                $this->log_debug_message('Creating test data source');
                return [
                    'uuid' => 'test-' . wp_generate_uuid4(),
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'created_at' => current_time('mysql')
                ];
            }

            $response = $this->make_http_request('POST', '/data-sources', $data);
            delete_transient('gpt_trainer_data_sources');
            
            $this->log_debug_message('Data source created successfully');
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "create_data_source");
            return []; // Satisfy return type
        }
    }

    /**
     * Get all data sources
     */
    public function get_all_data_sources() {
        try {
            $this->log_debug_message('Getting all data sources');

            if ($this->is_test_mode) {
                $this->log_debug_message('Getting test data sources');
                return [
                    [
                        'uuid' => 'test-data-source-1',
                        'name' => 'Test Data Source 1',
                        'type' => 'text',
                        'description' => 'A test data source for development',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                        'tags' => ['test', 'development']
                    ],
                    [
                        'uuid' => 'test-data-source-2',
                        'name' => 'Test Data Source 2',
                        'type' => 'file',
                        'description' => 'Another test data source',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                        'tags' => ['test']
                    ]
                ];
            }

            $response = get_transient('gpt_trainer_data_sources');
            if ($response === false) {
                $response = $this->make_http_request('GET', '/data-sources');
                set_transient('gpt_trainer_data_sources', $response, 5 * MINUTE_IN_SECONDS);
            }
            
            $this->log_debug_message('All data sources retrieved successfully');
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "get_all_data_sources");
            return []; // Return empty array instead of null on error
        }
    }

    /**
     * Get a data source by UUID
     */
    public function get_data_source(string $uuid) {
        try {
            $this->log_debug_message('Getting data source', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                $this->log_debug_message('Getting test data source', ['uuid' => $uuid]);
                return [
                    'uuid' => $uuid,
                    'name' => 'Test Data Source',
                    'type' => 'text',
                    'description' => 'A test data source for development',
                    'content' => 'Test content',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                    'tags' => ['test', 'development']
                ];
            }

            $response = $this->make_http_request('GET', "/data-sources/{$uuid}");
            
            $this->log_debug_message('Data source retrieved successfully', ['uuid' => $uuid]);
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "get_data_source");
        }
    }

    /**
     * Update a data source
     */
    public function update_data_source(string $uuid, array $data) {
        try {
            $this->log_debug_message('Updating data source', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                $this->log_debug_message('Updating test data source', ['uuid' => $uuid]);
                return [
                    'uuid' => $uuid,
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'description' => $data['description'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }

            $response = $this->make_http_request('PUT', "/data-sources/{$uuid}", $data);
            delete_transient('gpt_trainer_data_sources');
            
            $this->log_debug_message('Data source updated successfully', ['uuid' => $uuid]);
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "update_data_source");
        }
    }

    /**
     * Delete a data source
     */
    public function delete_data_source(string $uuid) {
        try {
            $this->log_debug_message('Deleting data source', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                $this->log_debug_message('Deleting test data source', ['uuid' => $uuid]);
                return ['success' => true, 'message' => 'Test data source deleted'];
            }

            $response = $this->make_http_request('DELETE', "/data-sources/{$uuid}");
            delete_transient('gpt_trainer_data_sources');
            
            $this->log_debug_message('Data source deleted successfully', ['uuid' => $uuid]);
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "delete_data_source");
        }
    }

    /**
     * Create a new chatbot
     */
    public function create_chatbot(array $data) {
        try {
            $this->log_debug_message('Creating chatbot');

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Creating test chatbot');
                }
                $testChatbot = array_merge($this->get_test_chatbots()[0], [
                    'uuid' => 'test-' . uniqid(),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return $testChatbot;
            }

            $response = $this->make_http_request('POST', '/chatbot/create', $data);
            delete_transient('gpt_trainer_chatbots');
            
            if (WP_DEBUG) {
                error_log('Chatbot created successfully');
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "create_chatbot");
        }
    }

    /**
     * Get all chatbots
     */
    public function get_all_chatbots() {
        try {
            $this->log_debug_message('Getting all chatbots');

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Getting test chatbots');
                }
                return $this->get_test_chatbots();
            }

            // Check transient cache first
            $cached = get_transient('gpt_trainer_chatbots');
            if ($cached !== false) {
                return $cached;
            }

            $response = $this->make_http_request('GET', '/chatbots');
            
            // Validate and normalize response
            if (!is_array($response)) {
                throw new \Exception('Invalid response format from API');
            }

            $chatbots = array_map(function ($chatbot) {
                if (!isset($chatbot['uuid'])) {
                    throw new \Exception('Invalid chatbot data: missing UUID');
                }

                if (!isset($chatbot['meta']) || !is_array($chatbot['meta'])) {
                    $chatbot['meta'] = [];
                }
                
                if (!isset($chatbot['meta']['visibility'])) {
                    $chatbot['meta']['visibility'] = 'private';
                }

                return $chatbot;
            }, $response);

            // Cache for 5 minutes
            set_transient('gpt_trainer_chatbots', $chatbots, 5 * MINUTE_IN_SECONDS);
            
            return $chatbots;
        } catch (\Exception $e) {
            $this->handle_api_error($e, 'get_all_chatbots');
            
            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Error in production API call, falling back to test data: ' . $e->getMessage());
                }
                return $this->get_test_chatbots();
            }
            throw $e;
        }
    }

    /**
     * Get a chatbot by UUID
     */
    public function get_chatbot(string $uuid) {
        try {
            if (WP_DEBUG) {
                error_log('Attempting to get chatbot', [
                    'uuid' => $uuid,
                    'test_mode' => $this->is_test_mode,
                    'base_url' => $this->base_url
                ]);
            }

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Getting test chatbot', ['uuid' => $uuid]);
                }
                $testChatbots = $this->get_test_chatbots();
                $chatbot = array_filter($testChatbots, function($item) use ($uuid) {
                    return $item['uuid'] === $uuid;
                });
                if (empty($chatbot)) {
                    throw new \Exception("Chatbot not found: {$uuid}");
                }
                return $chatbot[0];
            }

            $url = $this->base_url . "/chatbot/{$uuid}";
            $headers = $this->get_request_headers();
            
            if (WP_DEBUG) {
                error_log('Making API request', [
                    'url' => $url,
                    'method' => 'GET',
                    'headers' => $this->sanitize_headers($headers)
                ]);
            }

            $response = $this->make_http_request('GET', "/chatbot/{$uuid}");
            
            // Log the raw response
            if (WP_DEBUG) {
                error_log('Raw API response', [
                    'status' => wp_remote_retrieve_response_code($response),
                    'headers' => $this->sanitize_headers(wp_remote_retrieve_headers($response)),
                    'body' => wp_remote_retrieve_body($response)
                ]);
            }

            // Try to decode the response
            try {
                $data = $response;
                if (WP_DEBUG) {
                    error_log('Successfully decoded JSON response', [
                        'data_type' => gettype($data),
                        'has_uuid' => isset($data['uuid']),
                        'has_meta' => isset($data['meta'])
                    ]);
                }
            } catch (\Exception $e) {
                if (WP_DEBUG) {
                    error_log('Failed to decode JSON response', [
                        'error' => $e->getMessage(),
                        'body' => wp_remote_retrieve_body($response)
                    ]);
                }
                throw new \Exception('Failed to decode API response: ' . $e->getMessage());
            }

            // Ensure the response has the required structure
            if (!is_array($data)) {
                if (WP_DEBUG) {
                    error_log('Invalid response type', [
                        'type' => gettype($data),
                        'response' => $data
                    ]);
                }
                throw new \Exception('Invalid response format from API: expected array, got ' . gettype($data));
            }

            if (!isset($data['uuid'])) {
                if (WP_DEBUG) {
                    error_log('Missing UUID in response', [
                        'response' => $data
                    ]);
                }
                throw new \Exception('Invalid response format from API: missing UUID');
            }

            // Ensure meta exists and has required fields
            if (!isset($data['meta']) || !is_array($data['meta'])) {
                $data['meta'] = [];
            }

            // Ensure visibility exists within meta and is valid
            if (!isset($data['meta']['visibility']) || !in_array($data['meta']['visibility'], ['public', 'private'])) {
                if (WP_DEBUG) {
                    error_log('Invalid or missing visibility, defaulting to private', [
                        'uuid' => $uuid,
                        'current_visibility' => $data['meta']['visibility'] ?? 'undefined'
                    ]);
                }
                $data['meta']['visibility'] = 'private';
            }
            
            if (WP_DEBUG) {
                error_log('Chatbot retrieved successfully', [
                    'uuid' => $uuid,
                    'name' => $data['name'] ?? 'Unknown',
                    'visibility' => $data['meta']['visibility']
                ]);
            }
            
            return $data;
        } catch (\Exception $e) {
            // If we get a 404, return null instead of throwing
            if ($e instanceof \Exception && wp_remote_retrieve_response_code($e->getResponse()) === 404) {
                if (WP_DEBUG) {
                    error_log('Chatbot not found', ['uuid' => $uuid]);
                }
                return null;
            }
            
            $this->handle_api_error($e, 'get_chatbot');
            throw $e;
        }
    }

    /**
     * Update a chatbot
     */
    public function update_chatbot(string $uuid, array $data) {
        try {
            $this->log_debug_message('Updating chatbot', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Updating test chatbot', [
                        'uuid' => $uuid,
                        'data' => '[FILTERED]'
                    ]);
                }
                return array_merge($this->get_test_chatbots()[0], [
                    'uuid' => $uuid,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            $response = $this->make_http_request('POST', "/chatbot/{$uuid}/update", $data);
            delete_transient('gpt_trainer_chatbots');
            
            if (WP_DEBUG) {
                error_log('Chatbot updated successfully', [
                    'uuid' => $uuid,
                    'response' => '[FILTERED]'
                ]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "update_chatbot");
        }
    }

    /**
     * Delete a chatbot
     */
    public function delete_chatbot(string $uuid) {
        try {
            $this->log_debug_message('Deleting chatbot', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Deleting test chatbot', ['uuid' => $uuid]);
                }
                return ['success' => true, 'message' => 'Test chatbot deleted'];
            }

            $response = $this->make_http_request('DELETE', "/chatbot/{$uuid}/delete");
            delete_transient('gpt_trainer_chatbots');
            
            if (WP_DEBUG) {
                error_log('Chatbot deleted successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "delete_chatbot");
        }
    }

    /**
     * Create a new agent
     */
    public function create_agent(string $chatbotUuid, array $data) {
        try {
            $this->log_debug_message('Creating agent', ['chatbotUuid' => $chatbotUuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Creating test agent', ['chatbotUuid' => $chatbotUuid]);
                }
                return [
                    'uuid' => 'test-agent-' . uniqid(),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            $response = $this->make_http_request('POST', "/chatbot/{$chatbotUuid}/agent/create", $data);
            
            if (WP_DEBUG) {
                error_log('Agent created successfully', ['chatbotUuid' => $chatbotUuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "create_agent");
        }
    }

    /**
     * Update an agent
     */
    public function update_agent(string $uuid, array $data) {
        try {
            $this->log_debug_message('Updating agent', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Updating test agent', ['uuid' => $uuid]);
                }
                return [
                    'uuid' => $uuid,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }

            $response = $this->make_http_request('PUT', "/agent/{$uuid}/update", $data);
            
            if (WP_DEBUG) {
                error_log('Agent updated successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "update_agent");
        }
    }

    /**
     * Get an agent by UUID
     */
    public function get_agent(string $uuid) {
        try {
            $this->log_debug_message('Getting agent', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Getting test agent', ['uuid' => $uuid]);
                }
                return [
                    'uuid' => $uuid,
                    'name' => 'Test Agent',
                    'description' => 'A test agent for development',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
                ];
            }

            $response = $this->make_http_request('GET', "/agent/{$uuid}");
            
            if (WP_DEBUG) {
                error_log('Agent retrieved successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "get_agent");
        }
    }

    /**
     * Get all agents for a chatbot
     */
    public function get_all_agents(string $chatbotUuid) {
        try {
            $this->log_debug_message('Getting all agents', ['chatbotUuid' => $chatbotUuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Getting test agents', ['chatbotUuid' => $chatbotUuid]);
                }
                return [
                    [
                        'uuid' => 'test-agent-1',
                        'name' => 'Test Agent 1',
                        'description' => 'A test agent for development',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
                    ],
                    [
                        'uuid' => 'test-agent-2',
                        'name' => 'Test Agent 2',
                        'description' => 'Another test agent for development',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
                    ]
                ];
            }

            $cacheKey = "agents.{$chatbotUuid}";
            $response = get_transient($cacheKey);
            if ($response === false) {
                $response = $this->make_http_request('GET', "/chatbot/{$chatbotUuid}/agents");
                set_transient($cacheKey, $response, 5 * MINUTE_IN_SECONDS);
            }
            
            if (WP_DEBUG) {
                error_log('All agents retrieved successfully', ['chatbotUuid' => $chatbotUuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "get_all_agents");
        }
    }

    /**
     * Delete an agent
     */
    public function delete_agent(string $uuid) {
        try {
            $this->log_debug_message('Deleting agent', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Deleting test agent', ['uuid' => $uuid]);
                }
                return ['success' => true, 'message' => 'Test agent deleted'];
            }

            $response = $this->make_http_request('DELETE', "/agent/{$uuid}/delete");
            
            if (WP_DEBUG) {
                error_log('Agent deleted successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "delete_agent");
        }
    }

    /**
     * Create a new tag
     */
    public function create_tag(array $data) {
        try {
            $this->log_debug_message('Creating tag');

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Creating test tag', ['data' => '[FILTERED]']);
                }
                return [
                    'uuid' => 'test-tag-' . uniqid(),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            $response = $this->make_http_request('POST', '/tag/create', $data);
            delete_transient('gpt_trainer_tags');
            
            if (WP_DEBUG) {
                error_log('Tag created successfully');
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "create_tag");
        }
    }

    /**
     * Update a tag
     */
    public function update_tag(string $uuid, array $data) {
        try {
            $this->log_debug_message('Updating tag', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Updating test tag', ['uuid' => $uuid]);
                }
                return [
                    'uuid' => $uuid,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }

            $response = $this->make_http_request('PUT', "/tag/{$uuid}/update", $data);
            delete_transient('gpt_trainer_tags');
            
            if (WP_DEBUG) {
                error_log('Tag updated successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "update_tag");
        }
    }

    /**
     * Get a tag by UUID
     */
    public function get_tag(string $uuid) {
        try {
            $this->log_debug_message('Getting tag', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Getting test tag', ['uuid' => $uuid]);
                }
                return [
                    'uuid' => $uuid,
                    'name' => 'Test Tag',
                    'description' => 'A test tag for development',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
                ];
            }

            $response = $this->make_http_request('GET', "/tag/{$uuid}");
            
            if (WP_DEBUG) {
                error_log('Tag retrieved successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "get_tag");
        }
    }

    /**
     * Get all tags
     */
    public function get_all_tags() {
        try {
            $this->log_debug_message('Getting all tags');

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Getting test tags');
                }
                return [
                    [
                        'uuid' => 'test-tag-1',
                        'name' => 'Test Tag 1',
                        'description' => 'A test tag for development',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
                    ],
                    [
                        'uuid' => 'test-tag-2',
                        'name' => 'Test Tag 2',
                        'description' => 'Another test tag for development',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
                    ]
                ];
            }

            $response = get_transient('gpt_trainer_tags');
            if ($response === false) {
                $response = $this->make_http_request('GET', '/tag/list');
                set_transient('gpt_trainer_tags', $response, 5 * MINUTE_IN_SECONDS);
            }
            
            if (WP_DEBUG) {
                error_log('All tags retrieved successfully');
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "get_all_tags");
        }
    }

    /**
     * Delete a tag
     */
    public function delete_tag(string $uuid) {
        try {
            $this->log_debug_message('Deleting tag', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Deleting test tag', ['uuid' => $uuid]);
                }
                return ['success' => true, 'message' => 'Test tag deleted'];
            }

            $response = $this->make_http_request('DELETE', "/tag/{$uuid}/delete");
            delete_transient('gpt_trainer_tags');
            
            if (WP_DEBUG) {
                error_log('Tag deleted successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "delete_tag");
        }
    }

    /**
     * Sanitize headers for logging
     */
    protected function sanitize_headers(array $headers): array {
        return array_map(function($value) {
            return is_array($value) && isset($value[0]) && strpos($value[0], 'Bearer ') === 0
                ? '[REDACTED]'
                : $value;
        }, $headers);
    }

    /**
     * Log API calls when debugging is enabled
     */
    protected function log_api_call($method, $endpoint, $data = null) {
        if (!WP_DEBUG) {
            return;
        }

        $context = [
            'method' => $method,
            'endpoint' => $endpoint,
            'test_mode' => $this->is_test_mode
        ];

        if ($data) {
            $context['data'] = array_merge(
                $data,
                ['meta' => isset($data['meta']) ? '[FILTERED]' : null]
            );
        }

        error_log(sprintf(
            'API Call: %s %s - %s',
            $method,
            $endpoint,
            wp_json_encode($context)
        ));
    }

    /**
     * Get test chatbots
     */
    protected function get_test_chatbots() {
        return [
            [
                'uuid' => 'test-1',
                'name' => 'Test Chatbot 1',
                'description' => 'A test chatbot for development',
                'data_sources_count' => 2,
                'meta' => [
                    'visibility' => 'public'
                ],
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'uuid' => 'test-2',
                'name' => 'Test Chatbot 2',
                'description' => 'Another test chatbot',
                'data_sources_count' => 1,
                'meta' => [
                    'visibility' => 'private'
                ],
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];
    }

    /**
     * Retrain a data source
     */
    public function retrain_data_source(string $uuid) {
        try {
            $this->log_debug_message('Retraining data source', ['uuid' => $uuid]);

            if ($this->is_test_mode) {
                if (WP_DEBUG) {
                    error_log('Retraining test data source', ['uuid' => $uuid]);
                }
                return ['success' => true, 'message' => 'Test data source retrained'];
            }

            $response = $this->make_http_request('POST', "/data-sources/{$uuid}/retrain");
            
            if (WP_DEBUG) {
                error_log('Data source retrained successfully', ['uuid' => $uuid]);
            }
            return $response;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "retrain_data_source");
        }
    }

    /**
     * Delete multiple data sources
     */
    public function delete_multiple_data_sources(array $uuids) {
        try {
            $results = [];
            foreach ($uuids as $uuid) {
                try {
                    $results[$uuid] = $this->delete_data_source($uuid);
                } catch (\Exception $e) {
                    $results[$uuid] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            return $results;
        } catch (\Exception $e) {
            $this->handle_api_error($e, "delete_multiple_data_sources");
        }
    }

    /**
     * Check if API is in test mode
     */
    public function is_api_in_test_mode(): bool {
        return $this->is_test_mode;
    }
}
