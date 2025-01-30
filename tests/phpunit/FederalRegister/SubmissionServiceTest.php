<?php
namespace PiperPrivacySorn\Tests\FederalRegister;

use PiperPrivacySorn\Services\FederalRegisterSubmissionService;

/**
 * Test Federal Register submission service
 */
class SubmissionServiceTest extends TestCase {
    /**
     * @var FederalRegisterSubmissionService
     */
    private FederalRegisterSubmissionService $service;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->service = new FederalRegisterSubmissionService($this->mockApi);
    }

    /**
     * Test successful submission
     */
    public function testSubmitSorn(): void {
        // Create test SORN
        $sorn = $this->createTestSorn();

        // Set up mock response
        $this->mockApi->setMockResponse('submitSorn', [
            'submission_id' => 'TEST-123',
            'status' => 'submitted'
        ]);

        // Submit SORN
        $submission = $this->service->submit_sorn($sorn->ID);

        // Assert submission was created
        $this->assertNotNull($submission);
        $this->assertEquals('TEST-123', $submission->get_submission_id());
        $this->assertEquals('submitted', $submission->get_status());
        $this->assertEquals($sorn->ID, $submission->get_sorn_id());

        // Assert event was created
        $this->assertSubmissionHasEvent($submission, 'submitted');

        // Assert API was called
        $this->assertApiCalled('submitSorn');
    }

    /**
     * Test submission failure
     */
    public function testSubmitSornFailure(): void {
        // Create test SORN
        $sorn = $this->createTestSorn();

        // Set up mock response to fail
        $this->mockApi->setMockResponse('submitSorn', [], true);

        // Expect exception
        $this->expectException(\Exception::class);

        // Attempt submission
        $this->service->submit_sorn($sorn->ID);
    }

    /**
     * Test checking submission status
     */
    public function testCheckSubmissionStatus(): void {
        // Create test submission
        $submission = $this->createTestSubmission();

        // Set up mock response
        $this->mockApi->setMockResponse('checkSubmissionStatus', [
            'status' => 'in_review',
            'message' => 'Submission is being reviewed'
        ]);

        // Check status
        $updated = $this->service->check_submission_status($submission);

        // Assert status was updated
        $this->assertTrue($updated);
        $this->assertEquals('in_review', $submission->get_status());

        // Assert event was created
        $this->assertSubmissionHasEvent($submission, 'status_changed', [
            'old_status' => 'submitted',
            'new_status' => 'in_review',
            'message' => 'Submission is being reviewed'
        ]);

        // Assert API was called
        $this->assertApiCalled('checkSubmissionStatus', [
            'submission_id' => $submission->get_submission_id()
        ]);
    }

    /**
     * Test retrying failed submission
     */
    public function testRetrySubmission(): void {
        // Create failed submission
        $submission = $this->createTestSubmission([
            'status' => 'error'
        ]);

        // Create error event
        $this->createTestEvent($submission, 'error', [
            'message' => 'API timeout'
        ]);

        // Set up mock response
        $this->mockApi->setMockResponse('submitSorn', [
            'submission_id' => 'TEST-RETRY-123',
            'status' => 'submitted'
        ]);

        // Retry submission
        $retried = $this->service->retry_submission($submission);

        // Assert retry was successful
        $this->assertTrue($retried);
        $this->assertEquals('submitted', $submission->get_status());
        $this->assertEquals('TEST-RETRY-123', $submission->get_submission_id());

        // Assert events were created
        $this->assertSubmissionHasEvent($submission, 'retry_attempted');
        $this->assertSubmissionHasEvent($submission, 'submitted');

        // Assert API was called
        $this->assertApiCalled('submitSorn');
    }

    /**
     * Test handling published submission
     */
    public function testHandlePublishedSubmission(): void {
        // Create test submission
        $submission = $this->createTestSubmission([
            'status' => 'approved'
        ]);

        // Set up mock response
        $this->mockApi->setMockResponse('checkSubmissionStatus', [
            'status' => 'published',
            'document_number' => 'FR-2025-12345',
            'published_date' => '2025-01-29'
        ]);

        // Check status
        $updated = $this->service->check_submission_status($submission);

        // Assert status was updated
        $this->assertTrue($updated);
        $this->assertEquals('published', $submission->get_status());
        $this->assertEquals('FR-2025-12345', $submission->get_document_number());
        $this->assertEquals('2025-01-29', date('Y-m-d', strtotime($submission->get_published_at())));

        // Assert events were created
        $this->assertSubmissionHasEvent($submission, 'status_changed', [
            'old_status' => 'approved',
            'new_status' => 'published',
            'document_number' => 'FR-2025-12345'
        ]);

        // Assert API was called
        $this->assertApiCalled('checkSubmissionStatus');
    }

    /**
     * Test handling rejected submission
     */
    public function testHandleRejectedSubmission(): void {
        // Create test submission
        $submission = $this->createTestSubmission([
            'status' => 'in_review'
        ]);

        // Set up mock response
        $this->mockApi->setMockResponse('checkSubmissionStatus', [
            'status' => 'rejected',
            'message' => 'Invalid format in section 1'
        ]);

        // Check status
        $updated = $this->service->check_submission_status($submission);

        // Assert status was updated
        $this->assertTrue($updated);
        $this->assertEquals('rejected', $submission->get_status());

        // Assert events were created
        $this->assertSubmissionHasEvent($submission, 'status_changed', [
            'old_status' => 'in_review',
            'new_status' => 'rejected',
            'message' => 'Invalid format in section 1'
        ]);

        // Assert API was called
        $this->assertApiCalled('checkSubmissionStatus');
    }
}
