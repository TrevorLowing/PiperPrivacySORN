<?php
namespace PiperPrivacySorn\Tests\FederalRegister;

use PiperPrivacySorn\Ajax\FederalRegisterPreviewHandler;

/**
 * Test Federal Register preview functionality
 */
class PreviewTest extends TestCase {
    /**
     * @var FederalRegisterPreviewHandler
     */
    private $handler;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create test user
        $this->test_user = $this->factory->user->create([
            'role' => 'editor'
        ]);
        wp_set_current_user($this->test_user);

        // Initialize handler with mock API
        $this->handler = new FederalRegisterPreviewHandler($this->mockApi);

        // Set up nonce
        $_REQUEST['_wpnonce'] = wp_create_nonce('fr_preview_sorn');
        $_REQUEST['_ajax_nonce'] = $_REQUEST['_wpnonce'];
    }

    /**
     * Test standard preview request
     */
    public function testStandardPreview(): void {
        // Create test SORN
        $sorn = $this->createTestSorn([
            'post_title' => 'Test SORN Title',
            'post_content' => 'Test SORN Content',
            'meta_input' => [
                'system_name' => 'Test System',
                'system_number' => 'TEST-001',
                'agency_name' => 'Test Agency'
            ]
        ]);

        // Set up mock preview response
        $this->mockApi->setMockResponse('previewSorn', [
            'sections' => [
                [
                    'title' => 'Section 1',
                    'content' => 'Section 1 Content'
                ],
                [
                    'title' => 'Section 2',
                    'content' => 'Section 2 Content'
                ]
            ]
        ]);

        // Set up request
        $_POST['action'] = 'fr_preview_sorn';
        $_POST['sorn_id'] = $sorn->ID;
        $_POST['format'] = 'standard';

        // Capture JSON response
        ob_start();
        $this->handler->handle_preview_request();
        $response = json_decode(ob_get_clean(), true);

        // Assert response structure
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('html', $response['data']);

        // Assert preview content
        $html = $response['data']['html'];
        $this->assertStringContainsString('Section 1', $html);
        $this->assertStringContainsString('Section 2', $html);
        $this->assertStringContainsString('fr-preview-container', $html);
        $this->assertStringContainsString('fr-copy-button', $html);
    }

    /**
     * Test enhanced preview request
     */
    public function testEnhancedPreview(): void {
        // Create test SORN
        $sorn = $this->createTestSorn([
            'post_title' => 'Enhanced Test SORN',
            'post_content' => 'Enhanced Test Content',
            'meta_input' => [
                'system_name' => 'Enhanced System',
                'system_number' => 'TEST-002',
                'agency_name' => 'Enhanced Agency'
            ]
        ]);

        // Set up mock preview response
        $this->mockApi->setMockResponse('previewSorn', [
            'sections' => [
                [
                    'id' => 'section-1',
                    'title' => 'Enhanced Section 1',
                    'content' => 'Enhanced Section 1 Content'
                ],
                [
                    'id' => 'section-2',
                    'title' => 'Enhanced Section 2',
                    'content' => 'Enhanced Section 2 Content'
                ]
            ],
            'validation' => [
                'errors' => [],
                'warnings' => ['Sample warning']
            ]
        ]);

        // Set up request
        $_POST['action'] = 'fr_preview_sorn';
        $_POST['sorn_id'] = $sorn->ID;
        $_POST['format'] = 'enhanced';

        // Capture JSON response
        ob_start();
        $this->handler->handle_preview_request();
        $response = json_decode(ob_get_clean(), true);

        // Assert response structure
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $data = $response['data'];

        // Assert source content
        $this->assertArrayHasKey('source_html', $data);
        $this->assertStringContainsString('Enhanced Test SORN', $data['source_html']);
        $this->assertStringContainsString('Enhanced System', $data['source_html']);
        $this->assertStringContainsString('Enhanced Test Content', $data['source_html']);

        // Assert preview content
        $this->assertArrayHasKey('preview_html', $data);
        $this->assertStringContainsString('Enhanced Section 1', $data['preview_html']);
        $this->assertStringContainsString('Enhanced Section 2', $data['preview_html']);

        // Assert sections data
        $this->assertArrayHasKey('sections', $data);
        $this->assertCount(2, $data['sections']);
        $this->assertEquals('section-1', $data['sections'][0]['id']);
        $this->assertEquals('Enhanced Section 1', $data['sections'][0]['title']);

        // Assert validation data
        $this->assertArrayHasKey('validation', $data);
        $this->assertEmpty($data['validation']['errors']);
        $this->assertCount(1, $data['validation']['warnings']);
    }

    /**
     * Test validation request
     */
    public function testValidation(): void {
        // Create test SORN
        $sorn = $this->createTestSorn();

        // Set up mock validation response
        $this->mockApi->setMockResponse('validateSorn', [
            'errors' => ['Critical error found'],
            'warnings' => ['Warning message'],
            'suggestions' => ['Improvement suggestion']
        ]);

        // Set up request
        $_POST['action'] = 'fr_validate_sorn';
        $_POST['sorn_id'] = $sorn->ID;
        $_POST['nonce'] = wp_create_nonce('fr_validate_sorn');

        // Capture JSON response
        ob_start();
        $this->handler->handle_validation_request();
        $response = json_decode(ob_get_clean(), true);

        // Assert response structure
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $data = $response['data'];

        // Assert validation results
        $this->assertCount(1, $data['errors']);
        $this->assertCount(1, $data['warnings']);
        $this->assertCount(1, $data['suggestions']);
        $this->assertEquals('Critical error found', $data['errors'][0]);
        $this->assertEquals('Warning message', $data['warnings'][0]);
        $this->assertEquals('Improvement suggestion', $data['suggestions'][0]);
    }

    /**
     * Test preview request with invalid SORN
     */
    public function testInvalidSornPreview(): void {
        // Set up request with invalid SORN ID
        $_POST['action'] = 'fr_preview_sorn';
        $_POST['sorn_id'] = 999999;

        // Capture JSON response
        ob_start();
        $this->handler->handle_preview_request();
        $response = json_decode(ob_get_clean(), true);

        // Assert error response
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid SORN', $response['data']);
    }

    /**
     * Test preview request with insufficient permissions
     */
    public function testInsufficientPermissionsPreview(): void {
        // Create test SORN
        $sorn = $this->createTestSorn();

        // Set user role to subscriber (no edit_posts capability)
        $subscriber = $this->factory->user->create([
            'role' => 'subscriber'
        ]);
        wp_set_current_user($subscriber);

        // Set up request
        $_POST['action'] = 'fr_preview_sorn';
        $_POST['sorn_id'] = $sorn->ID;

        // Capture JSON response
        ob_start();
        $this->handler->handle_preview_request();
        $response = json_decode(ob_get_clean(), true);

        // Assert error response
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('permission', $response['data']);
    }

    /**
     * Test preview request with API error
     */
    public function testApiErrorPreview(): void {
        // Create test SORN
        $sorn = $this->createTestSorn();

        // Set up mock API to fail
        $this->mockApi->setMockResponse('previewSorn', [], true);

        // Set up request
        $_POST['action'] = 'fr_preview_sorn';
        $_POST['sorn_id'] = $sorn->ID;

        // Capture JSON response
        ob_start();
        $this->handler->handle_preview_request();
        $response = json_decode(ob_get_clean(), true);

        // Assert error response
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('API failure', $response['data']);
    }

    /**
     * Test preview with all meta fields
     */
    public function testPreviewWithAllMetaFields(): void {
        // Create test SORN with all meta fields
        $sorn = $this->createTestSorn([
            'meta_input' => [
                'system_name' => 'Complete System',
                'system_number' => 'TEST-003',
                'agency_name' => 'Complete Agency',
                'agency_division' => 'Complete Division',
                'publication_date' => '2025-01-29'
            ]
        ]);

        // Set up mock preview response
        $this->mockApi->setMockResponse('previewSorn', [
            'sections' => [
                [
                    'title' => 'Complete Section',
                    'content' => 'Complete Content'
                ]
            ]
        ]);

        // Set up request
        $_POST['action'] = 'fr_preview_sorn';
        $_POST['sorn_id'] = $sorn->ID;
        $_POST['format'] = 'enhanced';

        // Capture JSON response
        ob_start();
        $this->handler->handle_preview_request();
        $response = json_decode(ob_get_clean(), true);

        // Assert all meta fields are present
        $source_html = $response['data']['source_html'];
        $this->assertStringContainsString('Complete System', $source_html);
        $this->assertStringContainsString('Complete Division', $source_html);
        $this->assertStringContainsString('2025-01-29', $source_html);
    }
}
