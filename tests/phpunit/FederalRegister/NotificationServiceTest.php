<?php
namespace PiperPrivacySorn\Tests\FederalRegister;

use PiperPrivacySorn\Services\FederalRegisterNotificationService;

/**
 * Test Federal Register notification service
 */
class NotificationServiceTest extends TestCase {
    /**
     * @var FederalRegisterNotificationService
     */
    private FederalRegisterNotificationService $service;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->service = new FederalRegisterNotificationService();

        // Reset notification settings
        delete_option('piper_privacy_sorn_fr_notifications');

        // Set up test user
        $this->test_user = $this->factory->user->create([
            'role' => 'administrator',
            'user_email' => 'test@example.com'
        ]);
    }

    /**
     * Test sending notification for new submission
     */
    public function testSendSubmissionNotification(): void {
        // Create test submission
        $submission = $this->createTestSubmission();
        $sorn = get_post($submission->get_sorn_id());

        // Create test event
        $event = [
            'event_type' => 'submitted',
            'created_at' => current_time('mysql'),
            'event_data' => json_encode([])
        ];

        // Set up email tracking
        $emails = [];
        add_action('wp_mail_failed', function($error) {
            $this->fail('Email failed to send: ' . $error->get_error_message());
        });
        add_filter('wp_mail', function($args) use (&$emails) {
            $emails[] = $args;
            return $args;
        });

        // Send notification
        $sent = $this->service->send_notification($submission, $event);

        // Assert notification was sent
        $this->assertTrue($sent);
        $this->assertCount(1, $emails);

        // Assert email content
        $email = $emails[0];
        $this->assertStringContainsString($sorn->post_title, $email['subject']);
        $this->assertStringContainsString($submission->get_submission_id(), $email['message']);
        $this->assertStringContainsString('submitted', $email['message']);
    }

    /**
     * Test sending notification for published submission
     */
    public function testSendPublishedNotification(): void {
        // Create test submission
        $submission = $this->createTestSubmission([
            'status' => 'published',
            'document_number' => 'FR-2025-12345',
            'published_at' => current_time('mysql')
        ]);

        // Create test event
        $event = [
            'event_type' => 'published',
            'created_at' => current_time('mysql'),
            'event_data' => json_encode([
                'document_number' => 'FR-2025-12345'
            ])
        ];

        // Track emails
        $emails = [];
        add_filter('wp_mail', function($args) use (&$emails) {
            $emails[] = $args;
            return $args;
        });

        // Send notification
        $sent = $this->service->send_notification($submission, $event);

        // Assert notification was sent
        $this->assertTrue($sent);
        $this->assertCount(1, $emails);

        // Assert email content
        $email = $emails[0];
        $this->assertStringContainsString('FR-2025-12345', $email['message']);
        $this->assertStringContainsString('federalregister.gov', $email['message']);
    }

    /**
     * Test sending notification for error
     */
    public function testSendErrorNotification(): void {
        // Create test submission
        $submission = $this->createTestSubmission([
            'status' => 'error'
        ]);

        // Create test event
        $event = [
            'event_type' => 'error',
            'created_at' => current_time('mysql'),
            'event_data' => json_encode([
                'message' => 'API connection timeout'
            ])
        ];

        // Track emails
        $emails = [];
        add_filter('wp_mail', function($args) use (&$emails) {
            $emails[] = $args;
            return $args;
        });

        // Send notification
        $sent = $this->service->send_notification($submission, $event);

        // Assert notification was sent
        $this->assertTrue($sent);
        $this->assertCount(1, $emails);

        // Assert email content
        $email = $emails[0];
        $this->assertStringContainsString('Error', $email['subject']);
        $this->assertStringContainsString('API connection timeout', $email['message']);
    }

    /**
     * Test notification settings
     */
    public function testNotificationSettings(): void {
        // Update settings
        $settings = [
            'enabled' => true,
            'events' => ['submitted', 'published'],
            'recipients' => [
                'admin' => true,
                'author' => false,
                'custom' => ['custom@example.com']
            ]
        ];
        $this->service->update_notification_settings($settings);

        // Get settings
        $saved = $this->service->get_notification_settings();

        // Assert settings were saved
        $this->assertEquals($settings['enabled'], $saved['enabled']);
        $this->assertEquals($settings['events'], $saved['events']);
        $this->assertEquals($settings['recipients'], $saved['recipients']);
    }

    /**
     * Test notification disabled
     */
    public function testNotificationDisabled(): void {
        // Disable notifications
        $this->service->update_notification_settings([
            'enabled' => false
        ]);

        // Create test submission and event
        $submission = $this->createTestSubmission();
        $event = [
            'event_type' => 'submitted',
            'created_at' => current_time('mysql'),
            'event_data' => json_encode([])
        ];

        // Track emails
        $emails = [];
        add_filter('wp_mail', function($args) use (&$emails) {
            $emails[] = $args;
            return $args;
        });

        // Attempt to send notification
        $sent = $this->service->send_notification($submission, $event);

        // Assert notification was not sent
        $this->assertFalse($sent);
        $this->assertEmpty($emails);
    }

    /**
     * Test notification templates
     */
    public function testNotificationTemplates(): void {
        // Set custom template
        $settings = $this->service->get_notification_settings();
        $settings['templates']['submitted'] = [
            'subject' => 'Custom Subject: {sorn_title}',
            'message' => 'Custom Message for {submission_id}'
        ];
        $this->service->update_notification_settings($settings);

        // Create test submission
        $submission = $this->createTestSubmission();
        $sorn = get_post($submission->get_sorn_id());

        // Create test event
        $event = [
            'event_type' => 'submitted',
            'created_at' => current_time('mysql'),
            'event_data' => json_encode([])
        ];

        // Track emails
        $emails = [];
        add_filter('wp_mail', function($args) use (&$emails) {
            $emails[] = $args;
            return $args;
        });

        // Send notification
        $sent = $this->service->send_notification($submission, $event);

        // Assert notification was sent with custom template
        $this->assertTrue($sent);
        $this->assertCount(1, $emails);

        $email = $emails[0];
        $this->assertEquals(
            'Custom Subject: ' . $sorn->post_title,
            $email['subject']
        );
        $this->assertEquals(
            'Custom Message for ' . $submission->get_submission_id(),
            $email['message']
        );
    }
}
