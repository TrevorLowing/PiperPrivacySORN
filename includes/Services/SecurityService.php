<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use WP_User;

/**
 * Handles security-related functionality
 */
class SecurityService {
    /**
     * Initialize security features
     */
    public function init(): void {
        add_action('init', [$this, 'register_roles']);
        add_filter('user_has_cap', [$this, 'check_sorn_capabilities'], 10, 4);
        add_action('admin_init', [$this, 'enforce_secure_connection']);
    }

    /**
     * Register custom roles and capabilities
     */
    public function register_roles(): void {
        // SORN Editor role
        add_role('sorn_editor', 'SORN Editor', [
            'read' => true,
            'edit_sorns' => true,
            'publish_sorns' => true,
            'delete_sorns' => true,
            'manage_sorn_categories' => true
        ]);

        // SORN Reviewer role
        add_role('sorn_reviewer', 'SORN Reviewer', [
            'read' => true,
            'edit_sorns' => true,
            'review_sorns' => true
        ]);

        // Add capabilities to admin
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('edit_sorns');
            $admin->add_cap('publish_sorns');
            $admin->add_cap('delete_sorns');
            $admin->add_cap('manage_sorn_categories');
            $admin->add_cap('review_sorns');
            $admin->add_cap('manage_sorn_settings');
        }
    }

    /**
     * Check if user has capability for specific SORN
     *
     * @param array $allcaps All capabilities
     * @param array $caps Requested capabilities
     * @param array $args Additional arguments
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    public function check_sorn_capabilities(array $allcaps, array $caps, array $args, WP_User $user): array {
        if (!isset($args[0])) {
            return $allcaps;
        }

        $capability = $args[0];
        if (strpos($capability, 'sorn_') !== 0) {
            return $allcaps;
        }

        // Check if user has direct access to the SORN
        if (isset($args[2])) {
            $sorn_id = $args[2];
            if ($this->user_can_access_sorn($user->ID, $sorn_id)) {
                $allcaps[$capability] = true;
            }
        }

        return $allcaps;
    }

    /**
     * Check if user can access specific SORN
     *
     * @param int $user_id User ID
     * @param int $sorn_id SORN ID
     * @return bool Whether user can access SORN
     */
    private function user_can_access_sorn(int $user_id, int $sorn_id): bool {
        global $wpdb;
        
        // Check if user is assigned to SORN's agency
        $user_agency = get_user_meta($user_id, 'piper_privacy_sorn_agency', true);
        if ($user_agency) {
            $sorn_agency = $wpdb->get_var($wpdb->prepare(
                "SELECT agency FROM {$wpdb->prefix}piper_privacy_sorns WHERE id = %d",
                $sorn_id
            ));
            
            if ($sorn_agency === $user_agency) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Enforce secure connection for admin pages
     */
    public function enforce_secure_connection(): void {
        if (!is_ssl() && !is_local_development()) {
            wp_die(
                'Secure connection required. Please access this page using HTTPS.',
                'Security Error',
                ['response' => 403]
            );
        }
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    public function encrypt_data(string $data): string {
        if (!extension_loaded('openssl')) {
            return base64_encode($data);
        }

        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            0,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     */
    public function decrypt_data(string $encrypted_data): string {
        if (!extension_loaded('openssl')) {
            return base64_decode($encrypted_data);
        }

        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted_data);
        
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            0,
            $iv
        );
    }

    /**
     * Get or generate encryption key
     *
     * @return string Encryption key
     */
    private function get_encryption_key(): string {
        $key = get_option('piper_privacy_sorn_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('piper_privacy_sorn_encryption_key', $key);
        }
        
        return $key;
    }

    /**
     * Log security audit event
     *
     * @param string $action Action performed
     * @param array $data Additional data
     */
    public function log_audit_event(string $action, array $data = []): void {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_audit_log',
            [
                'user_id' => $user_id,
                'action' => $action,
                'ip_address' => $ip,
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }
}
