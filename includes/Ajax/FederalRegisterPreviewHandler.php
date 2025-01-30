<?php
namespace PiperPrivacySorn\Ajax;

use PiperPrivacySorn\Services\FederalRegisterApi;

/**
 * Handles AJAX requests for Federal Register preview functionality
 */
class FederalRegisterPreviewHandler {
    /**
     * @var FederalRegisterApi
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct(FederalRegisterApi $api = null) {
        $this->api = $api ?? new FederalRegisterApi();
    }

    /**
     * Initialize the handler
     */
    public function init(): void {
        add_action('wp_ajax_fr_preview_sorn', [$this, 'handle_preview_request']);
        add_action('wp_ajax_fr_validate_sorn', [$this, 'handle_validation_request']);
    }

    /**
     * Handle preview request
     */
    public function handle_preview_request(): void {
        // Verify nonce
        if (!check_ajax_referer('fr_preview_sorn', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token', 'piper-privacy-sorn'));
        }

        // Verify user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to preview SORNs', 'piper-privacy-sorn'));
        }

        $sorn_id = intval($_POST['sorn_id'] ?? 0);
        if (!$sorn_id) {
            wp_send_json_error(__('Invalid SORN ID', 'piper-privacy-sorn'));
        }

        try {
            // Get preview format
            $format = sanitize_text_field($_POST['format'] ?? 'standard');

            // Get SORN data
            $sorn = get_post($sorn_id);
            if (!$sorn || $sorn->post_type !== 'sorn') {
                throw new \Exception(__('Invalid SORN', 'piper-privacy-sorn'));
            }

            // Get preview data
            $preview_data = $this->api->previewSorn([
                'title' => $sorn->post_title,
                'content' => $sorn->post_content,
                'meta' => get_post_meta($sorn_id)
            ]);

            // Format response based on preview type
            if ($format === 'enhanced') {
                wp_send_json_success([
                    'source_html' => $this->format_source_content($sorn),
                    'preview_html' => $this->format_preview_content($preview_data),
                    'sections' => $this->get_section_data($preview_data),
                    'validation' => $preview_data['validation'] ?? null
                ]);
            } else {
                wp_send_json_success([
                    'html' => $this->format_standard_preview($preview_data),
                    'validation' => $preview_data['validation'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle validation request
     */
    public function handle_validation_request(): void {
        // Verify nonce
        if (!check_ajax_referer('fr_validate_sorn', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token', 'piper-privacy-sorn'));
        }

        // Verify user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to validate SORNs', 'piper-privacy-sorn'));
        }

        $sorn_id = intval($_POST['sorn_id'] ?? 0);
        if (!$sorn_id) {
            wp_send_json_error(__('Invalid SORN ID', 'piper-privacy-sorn'));
        }

        try {
            // Get SORN data
            $sorn = get_post($sorn_id);
            if (!$sorn || $sorn->post_type !== 'sorn') {
                throw new \Exception(__('Invalid SORN', 'piper-privacy-sorn'));
            }

            // Validate SORN
            $validation = $this->api->validateSorn([
                'title' => $sorn->post_title,
                'content' => $sorn->post_content,
                'meta' => get_post_meta($sorn_id)
            ]);

            wp_send_json_success([
                'errors' => $validation['errors'] ?? [],
                'warnings' => $validation['warnings'] ?? [],
                'suggestions' => $validation['suggestions'] ?? []
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Format source content for enhanced preview
     */
    private function format_source_content(\WP_Post $sorn): string {
        $meta = get_post_meta($sorn->ID);
        
        ob_start();
        ?>
        <div class="fr-source-content">
            <div class="fr-source-section" id="source-title">
                <h2><?php echo esc_html($sorn->post_title); ?></h2>
            </div>

            <div class="fr-source-section" id="source-meta">
                <dl>
                    <?php foreach ($this->get_meta_fields() as $key => $label): ?>
                        <?php if (!empty($meta[$key][0])): ?>
                            <dt><?php echo esc_html($label); ?></dt>
                            <dd><?php echo esc_html($meta[$key][0]); ?></dd>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </div>

            <div class="fr-source-section" id="source-content">
                <?php echo wp_kses_post($sorn->post_content); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format preview content for enhanced preview
     */
    private function format_preview_content(array $preview_data): string {
        ob_start();
        ?>
        <div class="fr-preview-content">
            <?php foreach ($preview_data['sections'] as $section): ?>
                <div class="fr-preview-section" id="preview-<?php echo esc_attr($section['id']); ?>">
                    <h3><?php echo esc_html($section['title']); ?></h3>
                    <?php echo wp_kses_post($section['content']); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format standard preview
     */
    private function format_standard_preview(array $preview_data): string {
        ob_start();
        ?>
        <div class="fr-preview-container">
            <div class="fr-preview-text">
                <?php foreach ($preview_data['sections'] as $section): ?>
                    <div class="fr-preview-section">
                        <h3><?php echo esc_html($section['title']); ?></h3>
                        <?php echo wp_kses_post($section['content']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="button fr-copy-button">
                <?php _e('Copy to Clipboard', 'piper-privacy-sorn'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get section data for navigation
     */
    private function get_section_data(array $preview_data): array {
        return array_map(function($section) {
            return [
                'id' => sanitize_title($section['title']),
                'title' => $section['title']
            ];
        }, $preview_data['sections']);
    }

    /**
     * Get meta fields
     */
    private function get_meta_fields(): array {
        return [
            'system_name' => __('System Name', 'piper-privacy-sorn'),
            'system_number' => __('System Number', 'piper-privacy-sorn'),
            'agency_name' => __('Agency Name', 'piper-privacy-sorn'),
            'agency_division' => __('Agency Division', 'piper-privacy-sorn'),
            'publication_date' => __('Publication Date', 'piper-privacy-sorn')
        ];
    }
}
