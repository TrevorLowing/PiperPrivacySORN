<?php

use PiperPrivacySorn\Services\SecurityService;

class Test_Security_Service extends WP_UnitTestCase {
    private $service;
    private $user_id;

    public function set_up() {
        parent::set_up();
        
        $this->service = new SecurityService();
        
        // Create test user
        $this->user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->user_id);
    }

    public function tear_down() {
        wp_delete_user($this->user_id);
        parent::tear_down();
    }

    public function test_register_roles() {
        $this->service->register_roles();
        
        // Check SORN Editor role
        $editor_role = get_role('sorn_editor');
        $this->assertInstanceOf(\WP_Role::class, $editor_role);
        $this->assertTrue($editor_role->has_cap('edit_sorns'));
        $this->assertTrue($editor_role->has_cap('publish_sorns'));
        
        // Check SORN Reviewer role
        $reviewer_role = get_role('sorn_reviewer');
        $this->assertInstanceOf(\WP_Role::class, $reviewer_role);
        $this->assertTrue($reviewer_role->has_cap('edit_sorns'));
        $this->assertTrue($reviewer_role->has_cap('review_sorns'));
        
        // Check admin capabilities
        $admin_role = get_role('administrator');
        $this->assertTrue($admin_role->has_cap('manage_sorn_settings'));
    }

    public function test_check_sorn_capabilities() {
        $user = wp_get_current_user();
        
        // Test with non-SORN capability
        $allcaps = $this->service->check_sorn_capabilities(
            ['edit_posts' => true],
            ['edit_posts'],
            ['edit_posts'],
            $user
        );
        $this->assertArrayHasKey('edit_posts', $allcaps);
        
        // Test with SORN capability
        update_user_meta($user->ID, 'piper_privacy_sorn_agency', 'TEST_AGENCY');
        
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorns',
            [
                'agency' => 'TEST_AGENCY',
                'title' => 'Test SORN',
                'system_number' => 'TEST-001',
                'content' => 'Test content',
                'status' => 'draft'
            ]
        );
        $sorn_id = $wpdb->insert_id;
        
        $allcaps = $this->service->check_sorn_capabilities(
            [],
            ['edit_sorns'],
            ['sorn_edit', null, $sorn_id],
            $user
        );
        
        $this->assertTrue($allcaps['sorn_edit'] ?? false);
        
        $wpdb->delete($wpdb->prefix . 'piper_privacy_sorns', ['id' => $sorn_id]);
    }

    public function test_encrypt_decrypt_data() {
        $test_data = 'Sensitive information';
        
        // Test encryption
        $encrypted = $this->service->encrypt_data($test_data);
        $this->assertNotEquals($test_data, $encrypted);
        
        // Test decryption
        $decrypted = $this->service->decrypt_data($encrypted);
        $this->assertEquals($test_data, $decrypted);
    }

    public function test_log_audit_event() {
        global $wpdb;
        $table = $wpdb->prefix . 'piper_privacy_audit_log';
        
        // Create test event
        $this->service->log_audit_event('test_action', ['test' => 'data']);
        
        // Verify log entry
        $log = $wpdb->get_row("SELECT * FROM $table ORDER BY id DESC LIMIT 1");
        
        $this->assertNotNull($log);
        $this->assertEquals('test_action', $log->action);
        $this->assertEquals($this->user_id, $log->user_id);
        
        $data = json_decode($log->data, true);
        $this->assertEquals('data', $data['test']);
    }

    public function test_enforce_secure_connection() {
        // Mock HTTPS
        $_SERVER['HTTPS'] = 'on';
        
        // This should not throw an exception
        $this->service->enforce_secure_connection();
        
        // Remove HTTPS
        unset($_SERVER['HTTPS']);
        
        // Expect wp_die to be called
        $this->expectException('WPDieException');
        $this->service->enforce_secure_connection();
    }
}
