<?php
declare(strict_types=1);

namespace PiperPrivacySorn\Services;

/**
 * Service for managing feature toggles and role-based access
 */
class FeatureManagementService {
    /**
     * Default feature configuration
     *
     * @var array
     */
    private const DEFAULT_FEATURES = [
        'federal_register_preview' => [
            'enabled' => true,
            'roles' => ['administrator', 'editor'],
            'description' => 'Preview SORN content in Federal Register format',
            'dependencies' => []
        ],
        'submission_history' => [
            'enabled' => true,
            'roles' => ['administrator'],
            'description' => 'View and manage Federal Register submission history',
            'dependencies' => []
        ],
        'audit_log' => [
            'enabled' => true,
            'roles' => ['administrator'],
            'description' => 'View detailed audit logs for submissions',
            'dependencies' => ['submission_history']
        ],
        'export_functionality' => [
            'enabled' => true,
            'roles' => ['administrator', 'editor'],
            'description' => 'Export submission history and audit logs',
            'dependencies' => ['submission_history']
        ],
        'validation_service' => [
            'enabled' => true,
            'roles' => ['administrator', 'editor'],
            'description' => 'Validate SORN content before submission',
            'dependencies' => []
        ]
    ];

    /**
     * Get all registered features
     *
     * @return array
     */
    public function get_features(): array {
        return array_merge(
            self::DEFAULT_FEATURES,
            $this->get_custom_features()
        );
    }

    /**
     * Get custom features registered through filters
     *
     * @return array
     */
    private function get_custom_features(): array {
        return apply_filters('piper_privacy_sorn_features', []);
    }

    /**
     * Check if a feature is enabled globally
     *
     * @param string $feature_name
     * @return bool
     */
    public function is_feature_enabled(string $feature_name): bool {
        $features = $this->get_features();
        
        if (!isset($features[$feature_name])) {
            return false;
        }

        // Check if feature is enabled globally
        if (!$features[$feature_name]['enabled']) {
            return false;
        }

        // Check dependencies
        foreach ($features[$feature_name]['dependencies'] as $dependency) {
            if (!$this->is_feature_enabled($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if current user has access to a feature
     *
     * @param string $feature_name
     * @return bool
     */
    public function current_user_can_access_feature(string $feature_name): bool {
        // Check if feature is enabled first
        if (!$this->is_feature_enabled($feature_name)) {
            return false;
        }

        $features = $this->get_features();
        
        if (!isset($features[$feature_name])) {
            return false;
        }

        // Super admin always has access
        if (is_super_admin()) {
            return true;
        }

        // Check user roles
        $user = wp_get_current_user();
        $allowed_roles = $features[$feature_name]['roles'];

        foreach ($allowed_roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                return true;
            }
        }

        // Allow custom access checks
        return (bool) apply_filters(
            'piper_privacy_sorn_can_access_feature',
            false,
            $feature_name,
            $user
        );
    }

    /**
     * Update feature configuration
     *
     * @param string $feature_name
     * @param array $config
     * @return bool
     */
    public function update_feature_config(string $feature_name, array $config): bool {
        $features = $this->get_features();
        
        if (!isset($features[$feature_name])) {
            return false;
        }

        $current_config = get_option('piper_privacy_sorn_features', []);
        $current_config[$feature_name] = array_merge(
            $features[$feature_name],
            $config
        );

        return update_option('piper_privacy_sorn_features', $current_config);
    }

    /**
     * Get feature configuration
     *
     * @param string $feature_name
     * @return array|null
     */
    public function get_feature_config(string $feature_name): ?array {
        $features = $this->get_features();
        return $features[$feature_name] ?? null;
    }

    /**
     * Register a new feature
     *
     * @param string $feature_name
     * @param array $config
     * @return bool
     */
    public function register_feature(string $feature_name, array $config): bool {
        add_filter('piper_privacy_sorn_features', function($features) use ($feature_name, $config) {
            $features[$feature_name] = wp_parse_args($config, [
                'enabled' => false,
                'roles' => ['administrator'],
                'description' => '',
                'dependencies' => []
            ]);
            return $features;
        });

        return true;
    }
}
