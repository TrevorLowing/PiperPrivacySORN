<?php

use PiperPrivacySorn\Services\FederalRegisterService;
use PiperPrivacySorn\Models\FederalRegisterSubmission;

class Test_Federal_Register_Service extends WP_UnitTestCase {
    private $service;
    private $submission;

    public function set_up() {
        parent::set_up();
        
        // Set up test API key
        update_option('piper_privacy_sorn_fr_api_key', 'test_api_key');
        
        $this->service = new FederalRegisterService();
        
        // Create test submission
        $this->submission = new FederalRegisterSubmission();
        $this->submission->set_sorn_id(1);
    }

    public function tear_down() {
        delete_option('piper_privacy_sorn_fr_api_key');
        parent::tear_down();
    }

    public function test_submit_sorn_without_api_key() {
        delete_option('piper_privacy_sorn_fr_api_key');
        $result = $this->service->submit_sorn($this->submission);
        
        $this->assertWPError($result);
        $this->assertEquals('missing_api_key', $result->get_error_code());
    }

    public function test_submit_sorn_success() {
        add_filter('pre_http_request', [$this, 'mock_submit_success'], 10, 3);
        
        $result = $this->service->submit_sorn($this->submission);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('submission_id', $result);
        
        remove_filter('pre_http_request', [$this, 'mock_submit_success']);
    }

    public function test_submit_sorn_failure() {
        add_filter('pre_http_request', [$this, 'mock_submit_failure'], 10, 3);
        
        $result = $this->service->submit_sorn($this->submission);
        
        $this->assertWPError($result);
        $this->assertEquals('submission_failed', $result->get_error_code());
        
        remove_filter('pre_http_request', [$this, 'mock_submit_failure']);
    }

    public function test_get_submission_status_success() {
        add_filter('pre_http_request', [$this, 'mock_status_success'], 10, 3);
        
        $result = $this->service->get_submission_status('test_submission_id');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        
        remove_filter('pre_http_request', [$this, 'mock_status_success']);
    }

    public function test_retry_submission_success_after_failure() {
        // First attempt fails, second succeeds
        add_filter('pre_http_request', [$this, 'mock_retry_sequence'], 10, 3);
        
        $result = $this->service->retry_submission($this->submission, 2, 1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('submission_id', $result);
        
        remove_filter('pre_http_request', [$this, 'mock_retry_sequence']);
    }

    // Mock response callbacks
    public function mock_submit_success($pre, $args, $url) {
        return [
            'response' => ['code' => 201],
            'body' => json_encode([
                'submission_id' => 'test_submission_id',
                'status' => 'pending'
            ])
        ];
    }

    public function mock_submit_failure($pre, $args, $url) {
        return [
            'response' => ['code' => 400],
            'body' => json_encode([
                'errors' => ['Invalid submission data']
            ])
        ];
    }

    public function mock_status_success($pre, $args, $url) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'submission_id' => 'test_submission_id',
                'status' => 'published'
            ])
        ];
    }

    private $retry_attempt = 0;
    public function mock_retry_sequence($pre, $args, $url) {
        $this->retry_attempt++;
        if ($this->retry_attempt === 1) {
            return $this->mock_submit_failure($pre, $args, $url);
        }
        return $this->mock_submit_success($pre, $args, $url);
    }
}
