<?php
declare(strict_types=1);

namespace PiperPrivacySorn\Admin;

use PiperPrivacySorn\Services\FeatureManagementService;

/**
 * Feature Management Admin Page
 */
class PiperPrivacySornFeatureManagement {
    /**
     * @var FeatureManagementService
     */
    private FeatureManagementService $feature_service;

    /**
     * Initialize the class
     */
    public function __construct(FeatureManagementService $feature_service = null) {
        $this->feature_service = $feature_service ?? new FeatureManagementService();
    }

    /**
     * Register hooks and filters
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_feature_management_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_update_feature_config', [$this, 'handle_feature_update']);
    }

    /**
     * Add feature management page
     */
    public function add_feature_management_page(): void {
        add_submenu_page(
            'piper-privacy-sorn',
            __('Feature Management', 'piper-privacy-sorn'),
            __('Features', 'piper-privacy-sorn'),
            'manage_options',
            'piper-privacy-sorn-features',
            [$this, 'render_feature_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting(
            'piper_privacy_sorn_features',
            'piper_privacy_sorn_features',
            [
                'type' => 'array',
                'description' => 'Feature configuration settings',
                'sanitize_callback' => [$this, 'sanitize_feature_settings']
            ]
        );
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets(string $hook): void {
        if ($hook !== 'piper-privacy-sorn_page_piper-privacy-sorn-features') {
            return;
        }

        wp_enqueue_style(
            'piper-privacy-sorn-feature-management',
            plugin_dir_url(PIPER_PRIVACY_SORN_FILE) . 'admin/css/piper-privacy-sorn-feature-management.css',
            [],
            PIPER_PRIVACY_SORN_VERSION
        );

        wp_enqueue_script(
            'piper-privacy-sorn-feature-management',
            plugin_dir_url(PIPER_PRIVACY_SORN_FILE) . 'admin/js/piper-privacy-sorn-feature-management.js',
            ['jquery'],
            PIPER_PRIVACY_SORN_VERSION,
            true
        );

        wp_localize_script('piper-privacy-sorn-feature-management', 'wp_feature_management', [
            'nonce' => wp_create_nonce('feature_management'),
            'strings' => [
                'save_success' => __('Feature settings saved successfully.', 'piper-privacy-sorn'),
                'save_error' => __('Error saving feature settings.', 'piper-privacy-sorn')
            ]
        ]);
    }

    /**
     * Render feature management page
     */
    public function render_feature_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $features = $this->feature_service->get_features();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <?php _e('Enable or disable features and manage role-based access. Some features may depend on others to function properly.', 'piper-privacy-sorn'); ?>
                </p>
            </div>

            <div class="feature-management-container">
                <form method="post" action="options.php" id="feature-management-form">
                    <?php settings_fields('piper_privacy_sorn_features'); ?>
                    
                    <div class="feature-grid">
                        <?php foreach ($features as $feature_name => $config): ?>
                            <div class="feature-card" data-feature="<?php echo esc_attr($feature_name); ?>">
                                <div class="feature-header">
                                    <h3><?php echo esc_html($this->get_feature_display_name($feature_name)); ?></h3>
                                    <label class="feature-toggle">
                                        <input type="checkbox" 
                                               name="features[<?php echo esc_attr($feature_name); ?>][enabled]" 
                                               value="1"
                                               <?php checked($config['enabled']); ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="feature-description">
                                    <?php echo esc_html($config['description']); ?>
                                </div>

                                <?php if (!empty($config['dependencies'])): ?>
                                    <div class="feature-dependencies">
                                        <strong><?php _e('Dependencies:', 'piper-privacy-sorn'); ?></strong>
                                        <ul>
                                            <?php foreach ($config['dependencies'] as $dependency): ?>
                                                <li><?php echo esc_html($this->get_feature_display_name($dependency)); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="feature-roles">
                                    <strong><?php _e('Allowed Roles:', 'piper-privacy-sorn'); ?></strong>
                                    <?php
                                    $roles = get_editable_roles();
                                    foreach ($roles as $role_name => $role_info):
                                        $checked = in_array($role_name, $config['roles']);
                                        ?>
                                        <label>
                                            <input type="checkbox" 
                                                   name="features[<?php echo esc_attr($feature_name); ?>][roles][]" 
                                                   value="<?php echo esc_attr($role_name); ?>"
                                                   <?php checked($checked); ?>>
                                            <?php echo esc_html(translate_user_role($role_info['name'])); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php submit_button(__('Save Feature Settings', 'piper-privacy-sorn')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle feature update AJAX request
     */
    public function handle_feature_update(): void {
        check_ajax_referer('feature_management', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'piper-privacy-sorn'));
        }

        $feature_name = sanitize_text_field($_POST['feature'] ?? '');
        $config = $this->sanitize_feature_settings($_POST['config'] ?? []);

        if (empty($feature_name) || empty($config)) {
            wp_send_json_error(__('Invalid feature configuration.', 'piper-privacy-sorn'));
        }

        $success = $this->feature_service->update_feature_config($feature_name, $config);

        if ($success) {
            wp_send_json_success(__('Feature settings updated successfully.', 'piper-privacy-sorn'));
        } else {
            wp_send_json_error(__('Error updating feature settings.', 'piper-privacy-sorn'));
        }
    }

    /**
     * Sanitize feature settings
     *
     * @param array $settings
     * @return array
     */
    public function sanitize_feature_settings(array $settings): array {
        $sanitized = [];

        foreach ($settings as $feature => $config) {
            $sanitized[$feature] = [
                'enabled' => (bool) ($config['enabled'] ?? false),
                'roles' => array_map('sanitize_text_field', $config['roles'] ?? []),
            ];
        }

        return $sanitized;
    }

    /**
     * Get feature display name
     *
     * @param string $feature_name
     * @return string
     */
    private function get_feature_display_name(string $feature_name): string {
        $display_names = [
            'federal_register_preview' => __('Federal Register Preview', 'piper-privacy-sorn'),
            'submission_history' => __('Submission History', 'piper-privacy-sorn'),
            'audit_log' => __('Audit Log', 'piper-privacy-sorn'),
            'export_functionality' => __('Export Functionality', 'piper-privacy-sorn'),
            'validation_service' => __('Validation Service', 'piper-privacy-sorn')
        ];

        return $display_names[$feature_name] ?? ucwords(str_replace('_', ' ', $feature_name));
    }
}
