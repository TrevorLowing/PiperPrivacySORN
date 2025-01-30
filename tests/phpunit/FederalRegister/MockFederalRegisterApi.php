<?php
namespace PiperPrivacySorn\Tests\FederalRegister;

use PiperPrivacySorn\Services\FederalRegisterApi;

/**
 * Mock Federal Register API for testing
 */
class MockFederalRegisterApi extends FederalRegisterApi {
    /**
     * Mock responses for different API calls
     */
    private array $mockResponses = [];

    /**
     * Track API calls for assertions
     */
    private array $apiCalls = [];

    /**
     * Set a mock response for an API method
     *
     * @param string $method API method name
     * @param mixed $response Response data
     * @param bool $shouldFail Whether the call should simulate a failure
     */
    public function setMockResponse(string $method, $response, bool $shouldFail = false): void {
        $this->mockResponses[$method] = [
            'data' => $response,
            'shouldFail' => $shouldFail
        ];
    }

    /**
     * Get tracked API calls
     *
     * @return array
     */
    public function getApiCalls(): array {
        return $this->apiCalls;
    }

    /**
     * Mock submit SORN
     */
    public function submitSorn(array $data): array {
        return $this->mockApiCall('submitSorn', $data);
    }

    /**
     * Mock check submission status
     */
    public function checkSubmissionStatus(string $submissionId): array {
        return $this->mockApiCall('checkSubmissionStatus', ['submission_id' => $submissionId]);
    }

    /**
     * Mock preview SORN
     */
    public function previewSorn(array $data): array {
        return $this->mockApiCall('previewSorn', $data);
    }

    /**
     * Mock validate SORN
     */
    public function validateSorn(array $data): array {
        return $this->mockApiCall('validateSorn', $data);
    }

    /**
     * Handle mock API calls
     */
    private function mockApiCall(string $method, array $params): array {
        // Track the API call
        $this->apiCalls[] = [
            'method' => $method,
            'params' => $params,
            'timestamp' => current_time('mysql')
        ];

        // Get mock response
        $mockResponse = $this->mockResponses[$method] ?? null;
        if (!$mockResponse) {
            throw new \Exception("No mock response set for method: $method");
        }

        // Simulate API failure if configured
        if ($mockResponse['shouldFail']) {
            throw new \Exception("Simulated API failure for method: $method");
        }

        return $mockResponse['data'];
    }
}
