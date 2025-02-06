<?php

use PiperPrivacySorn\Services\AiService;

class Test_Ai_Service extends WP_UnitTestCase {
    private $service;

    public function set_up() {
        parent::set_up();
        
        // Set up test API key
        update_option('piper_privacy_sorn_gpt_api_key', 'test_api_key');
        
        $this->service = new AiService();
    }

    public function tear_down() {
        delete_option('piper_privacy_sorn_gpt_api_key');
        parent::tear_down();
    }

    public function test_generate_draft_without_api_key() {
        delete_option('piper_privacy_sorn_gpt_api_key');
        $result = $this->service->generate_draft([]);
        
        $this->assertWPError($result);
        $this->assertEquals('missing_api_key', $result->get_error_code());
    }

    public function test_generate_draft_success() {
        add_filter('pre_http_request', [$this, 'mock_generate_success'], 10, 3);
        
        $result = $this->service->generate_draft([
            'system_name' => 'Test System',
            'purpose' => 'Testing purposes'
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        
        remove_filter('pre_http_request', [$this, 'mock_generate_success']);
    }

    public function test_analyze_sorn_success() {
        add_filter('pre_http_request', [$this, 'mock_analyze_success'], 10, 3);
        
        $result = $this->service->analyze_sorn('Test SORN content');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('analysis', $result);
        
        remove_filter('pre_http_request', [$this, 'mock_analyze_success']);
    }

    public function test_get_suggestions_success() {
        add_filter('pre_http_request', [$this, 'mock_suggestions_success'], 10, 3);
        
        $result = $this->service->get_suggestions('Test SORN content');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('suggestions', $result);
        
        remove_filter('pre_http_request', [$this, 'mock_suggestions_success']);
    }

    public function test_semantic_search_success() {
        add_filter('pre_http_request', [$this, 'mock_search_success'], 10, 3);
        
        $result = $this->service->semantic_search('test query');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        
        remove_filter('pre_http_request', [$this, 'mock_search_success']);
    }

    // Mock response callbacks
    public function mock_generate_success($pre, $args, $url) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'content' => 'Generated SORN content',
                'metadata' => [
                    'tokens_used' => 150,
                    'model' => 'gpt-4'
                ]
            ])
        ];
    }

    public function mock_analyze_success($pre, $args, $url) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'analysis' => [
                    'completeness_score' => 0.85,
                    'issues' => [],
                    'suggestions' => []
                ]
            ])
        ];
    }

    public function mock_suggestions_success($pre, $args, $url) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'suggestions' => [
                    [
                        'section' => 'purpose',
                        'suggestion' => 'Add more detail about system usage'
                    ]
                ]
            ])
        ];
    }

    public function mock_search_success($pre, $args, $url) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'results' => [
                    [
                        'id' => 1,
                        'title' => 'Test SORN',
                        'score' => 0.95
                    ]
                ]
            ])
        ];
    }
}
