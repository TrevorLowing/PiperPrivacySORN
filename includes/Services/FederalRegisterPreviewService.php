<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use PiperPrivacySorn\Models\FederalRegisterSubmission;

/**
 * Handles Federal Register preview functionality
 */
class FederalRegisterPreviewService {
    /**
     * @var FederalRegisterApi
     */
    private FederalRegisterApi $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new FederalRegisterApi();

        // Register AJAX handlers
        add_action('wp_ajax_fr_preview_sorn', [$this, 'handle_preview_request']);
        add_action('wp_ajax_fr_validate_sorn', [$this, 'handle_validation_request']);
    }

    /**
     * Handle preview request
     */
    public function handle_preview_request(): void {
        check_ajax_referer('fr_preview_sorn', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'piper-privacy-sorn'));
        }

        $sorn_id = (int) ($_POST['sorn_id'] ?? 0);
        if (!$sorn_id) {
            wp_send_json_error(__('Invalid SORN ID', 'piper-privacy-sorn'));
        }

        try {
            $preview = $this->generate_preview($sorn_id);
            wp_send_json_success([
                'html' => $preview['html'],
                'metadata' => $preview['metadata'],
                'validation' => $preview['validation']
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle validation request
     */
    public function handle_validation_request(): void {
        check_ajax_referer('fr_validate_sorn', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'piper-privacy-sorn'));
        }

        $sorn_id = (int) ($_POST['sorn_id'] ?? 0);
        if (!$sorn_id) {
            wp_send_json_error(__('Invalid SORN ID', 'piper-privacy-sorn'));
        }

        try {
            $validation = $this->validate_sorn($sorn_id);
            wp_send_json_success($validation);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Generate Federal Register preview
     *
     * @param int $sorn_id SORN ID
     * @return array Preview data
     * @throws \Exception If preview generation fails
     */
    public function generate_preview(int $sorn_id): array {
        $sorn = get_post($sorn_id);
        if (!$sorn) {
            throw new \Exception(__('SORN not found', 'piper-privacy-sorn'));
        }

        // Get SORN metadata
        $metadata = $this->get_sorn_metadata($sorn);

        // Format SORN for Federal Register
        $fr_data = $this->api->format_sorn_for_submission([
            'title' => $sorn->post_title,
            'content' => $sorn->post_content,
            'metadata' => $metadata
        ]);

        // Generate preview from Federal Register API
        $preview = $this->api->generate_preview($fr_data);

        // Validate SORN content
        $validation = $this->validate_sorn($sorn_id);

        return [
            'html' => $this->format_preview_html($preview, $validation),
            'metadata' => $metadata,
            'validation' => $validation
        ];
    }

    /**
     * Get SORN metadata
     *
     * @param \WP_Post $sorn SORN post
     * @return array Metadata
     */
    private function get_sorn_metadata(\WP_Post $sorn): array {
        return [
            'agency_id' => get_post_meta($sorn->ID, 'agency_id', true),
            'system_number' => get_post_meta($sorn->ID, 'system_number', true),
            'action_type' => get_post_meta($sorn->ID, 'action_type', true),
            'categories' => wp_get_post_terms($sorn->ID, 'sorn_category', ['fields' => 'names']),
            'effective_date' => get_post_meta($sorn->ID, 'effective_date', true),
            'contact_info' => [
                'name' => get_post_meta($sorn->ID, 'contact_name', true),
                'title' => get_post_meta($sorn->ID, 'contact_title', true),
                'email' => get_post_meta($sorn->ID, 'contact_email', true),
                'phone' => get_post_meta($sorn->ID, 'contact_phone', true),
                'address' => get_post_meta($sorn->ID, 'contact_address', true)
            ]
        ];
    }

    /**
     * Validate SORN content
     *
     * @param int $sorn_id SORN ID
     * @return array Validation results
     */
    public function validate_sorn(int $sorn_id): array {
        $sorn = get_post($sorn_id);
        if (!$sorn) {
            throw new \Exception(__('SORN not found', 'piper-privacy-sorn'));
        }

        $validation = [
            'errors' => [],
            'warnings' => [],
            'suggestions' => []
        ];

        // Required fields
        $required_fields = [
            'agency_id' => __('Agency ID', 'piper-privacy-sorn'),
            'system_number' => __('System Number', 'piper-privacy-sorn'),
            'action_type' => __('Action Type', 'piper-privacy-sorn'),
            'contact_name' => __('Contact Name', 'piper-privacy-sorn'),
            'contact_email' => __('Contact Email', 'piper-privacy-sorn')
        ];

        foreach ($required_fields as $field => $label) {
            if (empty(get_post_meta($sorn_id, $field, true))) {
                $validation['errors'][] = sprintf(
                    __('%s is required', 'piper-privacy-sorn'),
                    $label
                );
            }
        }

        // Content sections
        $required_sections = [
            'SUMMARY:' => __('Summary', 'piper-privacy-sorn'),
            'SYSTEM NAME AND NUMBER:' => __('System Name and Number', 'piper-privacy-sorn'),
            'SECURITY CLASSIFICATION:' => __('Security Classification', 'piper-privacy-sorn'),
            'SYSTEM LOCATION:' => __('System Location', 'piper-privacy-sorn'),
            'SYSTEM MANAGER:' => __('System Manager', 'piper-privacy-sorn'),
            'AUTHORITY:' => __('Authority', 'piper-privacy-sorn'),
            'PURPOSE:' => __('Purpose', 'piper-privacy-sorn'),
            'CATEGORIES OF INDIVIDUALS:' => __('Categories of Individuals', 'piper-privacy-sorn'),
            'CATEGORIES OF RECORDS:' => __('Categories of Records', 'piper-privacy-sorn'),
            'RECORD SOURCE CATEGORIES:' => __('Record Source Categories', 'piper-privacy-sorn'),
            'ROUTINE USES:' => __('Routine Uses', 'piper-privacy-sorn'),
            'POLICIES AND PRACTICES:' => __('Policies and Practices', 'piper-privacy-sorn'),
            'NOTIFICATION PROCEDURES:' => __('Notification Procedures', 'piper-privacy-sorn'),
            'EXEMPTIONS CLAIMED:' => __('Exemptions Claimed', 'piper-privacy-sorn'),
            'HISTORY:' => __('History', 'piper-privacy-sorn')
        ];

        foreach ($required_sections as $section => $label) {
            if (strpos($sorn->post_content, $section) === false) {
                $validation['errors'][] = sprintf(
                    __('Missing required section: %s', 'piper-privacy-sorn'),
                    $label
                );
            }
        }

        // Content length
        $min_length = 1000;
        if (strlen($sorn->post_content) < $min_length) {
            $validation['warnings'][] = sprintf(
                __('Content length (%d characters) is below recommended minimum (%d characters)', 'piper-privacy-sorn'),
                strlen($sorn->post_content),
                $min_length
            );
        }

        // Contact information format
        $email = get_post_meta($sorn_id, 'contact_email', true);
        if ($email && !is_email($email)) {
            $validation['errors'][] = __('Invalid contact email format', 'piper-privacy-sorn');
        }

        $phone = get_post_meta($sorn_id, 'contact_phone', true);
        if ($phone && !preg_match('/^\+?1?\d{10,}$/', preg_replace('/[^\d]/', '', $phone))) {
            $validation['warnings'][] = __('Phone number format may not be valid', 'piper-privacy-sorn');
        }

        // Style suggestions
        if (preg_match_all('/\b[A-Z]{2,}\b/', $sorn->post_content, $matches)) {
            $validation['suggestions'][] = __('Consider defining acronyms on first use', 'piper-privacy-sorn');
        }

        return $validation;
    }

    /**
     * Format preview HTML
     *
     * @param array $preview Preview data from API
     * @param array $validation Validation results
     * @return string Formatted HTML
     */
    private function format_preview_html(array $preview, array $validation): string {
        ob_start();
        ?>
        <div class="fr-preview-container">
            <?php if (!empty($validation['errors']) || !empty($validation['warnings'])): ?>
                <div class="fr-preview-validation">
                    <?php if (!empty($validation['errors'])): ?>
                        <div class="fr-preview-errors">
                            <h4><?php _e('Errors', 'piper-privacy-sorn'); ?></h4>
                            <ul>
                                <?php foreach ($validation['errors'] as $error): ?>
                                    <li><?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($validation['warnings'])): ?>
                        <div class="fr-preview-warnings">
                            <h4><?php _e('Warnings', 'piper-privacy-sorn'); ?></h4>
                            <ul>
                                <?php foreach ($validation['warnings'] as $warning): ?>
                                    <li><?php echo esc_html($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($validation['suggestions'])): ?>
                        <div class="fr-preview-suggestions">
                            <h4><?php _e('Suggestions', 'piper-privacy-sorn'); ?></h4>
                            <ul>
                                <?php foreach ($validation['suggestions'] as $suggestion): ?>
                                    <li><?php echo esc_html($suggestion); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="fr-preview-content">
                <div class="fr-preview-header">
                    <h3><?php _e('Federal Register Preview', 'piper-privacy-sorn'); ?></h3>
                    <div class="fr-preview-actions">
                        <button class="button" onclick="window.print()">
                            <?php _e('Print Preview', 'piper-privacy-sorn'); ?>
                        </button>
                        <button class="button" onclick="navigator.clipboard.writeText(document.querySelector('.fr-preview-text').innerText)">
                            <?php _e('Copy Text', 'piper-privacy-sorn'); ?>
                        </button>
                    </div>
                </div>

                <div class="fr-preview-text">
                    <?php echo wp_kses_post($preview['html']); ?>
                </div>

                <?php if (!empty($preview['metadata'])): ?>
                    <div class="fr-preview-metadata">
                        <h4><?php _e('Metadata', 'piper-privacy-sorn'); ?></h4>
                        <table class="widefat">
                            <tbody>
                                <?php foreach ($preview['metadata'] as $key => $value): ?>
                                    <tr>
                                        <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                        <td>
                                            <?php
                                            if (is_array($value)) {
                                                echo esc_html(implode(', ', $value));
                                            } else {
                                                echo esc_html($value);
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
