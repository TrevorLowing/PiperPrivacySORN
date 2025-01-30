<?php
namespace PiperPrivacySorn\Tests\FederalRegister;

use PiperPrivacySorn\Models\FederalRegisterSubmission;

/**
 * Base test case for Federal Register tests
 */
class TestCase extends \WP_UnitTestCase {
    /**
     * Mock Federal Register API
     */
    protected MockFederalRegisterApi $mockApi;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Initialize mock API
        $this->mockApi = new MockFederalRegisterApi();

        // Set up test database tables
        $this->setUpTables();

        // Reset submission status cache
        wp_cache_flush();
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void {
        parent::tearDown();

        // Clean up test submissions
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}piper_privacy_sorn_fr_submissions");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}piper_privacy_sorn_fr_submission_events");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}piper_privacy_sorn_fr_submission_archives");

        // Clean up test posts
        $this->removeTestPosts();
    }

    /**
     * Set up test database tables
     */
    protected function setUpTables(): void {
        global $wpdb;

        // Include dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create tables using the actual schema
        $schema = new \PiperPrivacySorn\Database\FederalRegisterTables();
        dbDelta($schema->get_schema());
    }

    /**
     * Create a test SORN post
     */
    protected function createTestSorn(array $data = []): \WP_Post {
        $defaults = [
            'post_title' => 'Test SORN ' . uniqid(),
            'post_type' => 'sorn',
            'post_status' => 'publish',
            'meta_input' => [
                'system_name' => 'Test System',
                'system_number' => 'TEST-001',
                'agency_name' => 'Test Agency'
            ]
        ];

        $post_data = wp_parse_args($data, $defaults);
        $post_id = wp_insert_post($post_data);

        return get_post($post_id);
    }

    /**
     * Create a test submission
     */
    protected function createTestSubmission(array $data = []): FederalRegisterSubmission {
        $defaults = [
            'submission_id' => 'TEST-' . uniqid(),
            'sorn_id' => $this->createTestSorn()->ID,
            'status' => 'submitted',
            'submitted_at' => current_time('mysql')
        ];

        $submission_data = wp_parse_args($data, $defaults);
        
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorn_fr_submissions',
            $submission_data,
            ['%s', '%d', '%s', '%s']
        );

        return new FederalRegisterSubmission([
            'id' => $wpdb->insert_id,
            ...$submission_data
        ]);
    }

    /**
     * Create a test submission event
     */
    protected function createTestEvent(
        FederalRegisterSubmission $submission,
        string $event_type,
        array $event_data = []
    ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorn_fr_submission_events',
            [
                'submission_id' => $submission->get_submission_id(),
                'event_type' => $event_type,
                'event_data' => json_encode($event_data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Assert submission has an event
     */
    protected function assertSubmissionHasEvent(
        FederalRegisterSubmission $submission,
        string $event_type,
        ?array $event_data = null
    ): void {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submission_events
            WHERE submission_id = %s AND event_type = %s",
            $submission->get_submission_id(),
            $event_type
        );

        if ($event_data !== null) {
            $query .= $wpdb->prepare(
                " AND event_data = %s",
                json_encode($event_data)
            );
        }

        $count = (int) $wpdb->get_var($query);
        $this->assertGreaterThan(
            0,
            $count,
            "Expected submission to have event of type '$event_type'"
        );
    }

    /**
     * Assert submission status
     */
    protected function assertSubmissionStatus(
        FederalRegisterSubmission $submission,
        string $expected_status
    ): void {
        $this->assertEquals(
            $expected_status,
            $submission->get_status(),
            "Expected submission status to be '$expected_status'"
        );
    }

    /**
     * Assert API was called
     */
    protected function assertApiCalled(string $method, ?array $params = null): void {
        $calls = $this->mockApi->getApiCalls();
        $method_calls = array_filter($calls, fn($call) => $call['method'] === $method);

        $this->assertNotEmpty(
            $method_calls,
            "Expected API method '$method' to be called"
        );

        if ($params !== null) {
            $matching_calls = array_filter(
                $method_calls,
                fn($call) => $this->arrayContains($call['params'], $params)
            );

            $this->assertNotEmpty(
                $matching_calls,
                "Expected API method '$method' to be called with specified parameters"
            );
        }
    }

    /**
     * Check if array contains all key/value pairs from another array
     */
    private function arrayContains(array $haystack, array $needle): bool {
        foreach ($needle as $key => $value) {
            if (!isset($haystack[$key]) || $haystack[$key] !== $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove test posts
     */
    private function removeTestPosts(): void {
        $posts = get_posts([
            'post_type' => 'sorn',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }
}
