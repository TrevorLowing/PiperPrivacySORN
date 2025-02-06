<?php

use PiperPrivacySorn\Api\RestController;

class Test_Rest_Controller extends WP_UnitTestCase {
    private $server;
    private $namespaced_route = 'piper-privacy-sorn/v1';
    private $user_id;

    public function set_up() {
        parent::set_up();
        
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
        
        // Create and authenticate admin user
        $this->user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->user_id);
    }

    public function tear_down() {
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_delete_user($this->user_id);
        parent::tear_down();
    }

    public function test_register_routes() {
        $routes = $this->server->get_routes();
        
        $this->assertArrayHasKey("/{$this->namespaced_route}/sorns", $routes);
        $this->assertArrayHasKey("/{$this->namespaced_route}/stats", $routes);
        $this->assertArrayHasKey("/{$this->namespaced_route}/agencies", $routes);
    }

    public function test_get_sorns() {
        global $wpdb;
        
        // Create test SORN
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorns',
            [
                'title' => 'Test SORN',
                'agency' => 'TEST_AGENCY',
                'system_number' => 'TEST-001',
                'content' => 'Test content',
                'status' => 'draft'
            ]
        );
        
        $request = new WP_REST_Request('GET', "/{$this->namespaced_route}/sorns");
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Test SORN', $data['items'][0]->title);
        
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}piper_privacy_sorns");
    }

    public function test_get_stats() {
        global $wpdb;
        
        // Create test SORNs
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorns',
            [
                'title' => 'Draft SORN',
                'agency' => 'TEST_AGENCY',
                'system_number' => 'TEST-001',
                'content' => 'Test content',
                'status' => 'draft'
            ]
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorns',
            [
                'title' => 'Published SORN',
                'agency' => 'TEST_AGENCY',
                'system_number' => 'TEST-002',
                'content' => 'Test content',
                'status' => 'published'
            ]
        );
        
        $request = new WP_REST_Request('GET', "/{$this->namespaced_route}/stats");
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(1, $data['published']);
        
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}piper_privacy_sorns");
    }

    public function test_get_agencies() {
        global $wpdb;
        
        // Create test SORNs with different agencies
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorns',
            [
                'title' => 'SORN 1',
                'agency' => 'AGENCY_1',
                'system_number' => 'TEST-001',
                'content' => 'Test content',
                'status' => 'draft'
            ]
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorns',
            [
                'title' => 'SORN 2',
                'agency' => 'AGENCY_2',
                'system_number' => 'TEST-002',
                'content' => 'Test content',
                'status' => 'draft'
            ]
        );
        
        $request = new WP_REST_Request('GET', "/{$this->namespaced_route}/agencies");
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertCount(2, $data);
        $this->assertEquals('AGENCY_1', $data[0]->id);
        $this->assertEquals('AGENCY_2', $data[1]->id);
        
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}piper_privacy_sorns");
    }

    public function test_submit_to_federal_register() {
        global $wpdb;
        
        // Create published SORN
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorns',
            [
                'title' => 'Published SORN',
                'agency' => 'TEST_AGENCY',
                'system_number' => 'TEST-001',
                'content' => 'Test content',
                'status' => 'published'
            ]
        );
        $sorn_id = $wpdb->insert_id;
        
        $request = new WP_REST_Request(
            'POST',
            "/{$this->namespaced_route}/sorns/{$sorn_id}/submit"
        );
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('submission', $data);
        
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}piper_privacy_sorns");
    }

    public function test_unauthorized_access() {
        // Set user to subscriber role
        $subscriber_id = $this->factory->user->create([
            'role' => 'subscriber'
        ]);
        wp_set_current_user($subscriber_id);
        
        $request = new WP_REST_Request('GET', "/{$this->namespaced_route}/sorns");
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(403, $response->get_status());
        
        wp_delete_user($subscriber_id);
    }
}
