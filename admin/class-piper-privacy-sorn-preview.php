<?php
declare(strict_types=1);

namespace PiperPrivacySorn\Admin;

use PiperPrivacySorn\Ajax\FederalRegisterPreviewHandler;

/**
 * Federal Register Preview Admin Class
 */
class PiperPrivacySornPreview {
    /**
     * @var FederalRegisterPreviewHandler
     */
    private FederalRegisterPreviewHandler $preview_handler;

    /**
     * Initialize the class
     */
    public function __construct(FederalRegisterPreviewHandler $preview_handler = null) {
        $this->preview_handler = $preview_handler ?? new FederalRegisterPreviewHandler();
    }

    /**
     * Register hooks and filters
     */
    public function init(): void {
        // Register assets
        add_action('admin_enqueue_scripts', [$this, 'register_assets']);

        // Initialize AJAX handler
        $this->preview_handler->init();

        // Add meta box
        add_action('add_meta_boxes', [$this, 'add_preview_meta_box']);
    }

    /**
     * Register and enqueue assets
     */
    public function register_assets(string $hook): void {
        // Only load on SORN edit screen
        if (!$this->is_sorn_edit_screen($hook)) {
            return;
        }

        // Register styles
        wp_register_style(
            'piper-privacy-sorn-preview',
            plugin_dir_url(PIPER_PRIVACY_SORN_FILE) . 'admin/css/piper-privacy-sorn-federal-register-preview-enhanced.css',
            [],
            PIPER_PRIVACY_SORN_VERSION
        );

        // Register scripts
        wp_register_script(
            'jsdiff',
            'https://cdnjs.cloudflare.com/ajax/libs/jsdiff/5.1.0/diff.min.js',
            [],
            '5.1.0',
            true
        );

        wp_register_script(
            'piper-privacy-sorn-preview',
            plugin_dir_url(PIPER_PRIVACY_SORN_FILE) . 'admin/js/piper-privacy-sorn-federal-register-preview.js',
            ['jquery', 'jsdiff'],
            PIPER_PRIVACY_SORN_VERSION,
            true
        );

        // Localize script
        wp_localize_script('piper-privacy-sorn-preview', 'wp_fr_preview', [
            'nonce' => wp_create_nonce('fr_preview_sorn'),
            'validate_nonce' => wp_create_nonce('fr_validate_sorn'),
            'error_message' => __('An error occurred while processing your request.', 'piper-privacy-sorn'),
            'copy_success' => __('Content copied to clipboard', 'piper-privacy-sorn'),
            'copy_error' => __('Failed to copy content', 'piper-privacy-sorn')
        ]);

        // Enqueue assets
        wp_enqueue_style('piper-privacy-sorn-preview');
        wp_enqueue_script('piper-privacy-sorn-preview');
    }

    /**
     * Add preview meta box
     */
    public function add_preview_meta_box(): void {
        add_meta_box(
            'fr-preview-meta-box',
            __('Federal Register Preview', 'piper-privacy-sorn'),
            [$this, 'render_preview_meta_box'],
            'sorn',
            'advanced',
            'high'
        );
    }

    /**
     * Render preview meta box
     */
    public function render_preview_meta_box(\WP_Post $post): void {
        // Security nonce is already added by wp_localize_script
        ?>
        <div class="fr-preview-wrapper">
            <!-- Preview Controls -->
            <div class="fr-preview-controls">
                <button type="button" class="button button-primary" id="fr-preview-button">
                    <?php _e('Generate Preview', 'piper-privacy-sorn'); ?>
                </button>
                <button type="button" class="button" id="fr-validate-button">
                    <?php _e('Validate SORN', 'piper-privacy-sorn'); ?>
                </button>
                <button type="button" class="button" id="fr-refresh-preview">
                    <?php _e('Refresh', 'piper-privacy-sorn'); ?>
                </button>
            </div>

            <!-- Section Navigation -->
            <div class="fr-section-nav">
                <select id="fr-section-nav">
                    <option value=""><?php _e('Jump to section...', 'piper-privacy-sorn'); ?></option>
                </select>
            </div>

            <!-- Enhanced Preview Container -->
            <div class="fr-preview-enhanced">
                <!-- Source Panel -->
                <div class="fr-panel fr-panel-source">
                    <div class="fr-panel-header">
                        <h3><?php _e('Source', 'piper-privacy-sorn'); ?></h3>
                        <div class="fr-panel-toolbar">
                            <button type="button" class="button fr-toggle-diff" title="<?php esc_attr_e('Toggle diff view', 'piper-privacy-sorn'); ?>">
                                <?php _e('Show Diff', 'piper-privacy-sorn'); ?>
                            </button>
                            <button type="button" class="button fr-copy-button" title="<?php esc_attr_e('Copy source content', 'piper-privacy-sorn'); ?>">
                                <?php _e('Copy', 'piper-privacy-sorn'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="fr-panel-content">
                        <div class="fr-panel-loading" style="display: none;">
                            <span class="spinner is-active"></span>
                        </div>
                    </div>
                </div>

                <!-- Preview Panel -->
                <div class="fr-panel fr-panel-preview">
                    <div class="fr-panel-header">
                        <h3><?php _e('Preview', 'piper-privacy-sorn'); ?></h3>
                        <div class="fr-panel-toolbar">
                            <button type="button" class="button fr-copy-button" title="<?php esc_attr_e('Copy preview content', 'piper-privacy-sorn'); ?>">
                                <?php _e('Copy', 'piper-privacy-sorn'); ?>
                            </button>
                            <button type="button" class="button fr-print-button" title="<?php esc_attr_e('Print preview', 'piper-privacy-sorn'); ?>">
                                <?php _e('Print', 'piper-privacy-sorn'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="fr-panel-content">
                        <div class="fr-panel-loading" style="display: none;">
                            <span class="spinner is-active"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation Results -->
            <div id="fr-preview-validation"></div>

            <!-- Messages -->
            <div id="fr-preview-message" class="notice notice-success" style="display: none;"></div>
            <div id="fr-preview-error" class="notice notice-error" style="display: none;"></div>
        </div>
        <?php
    }

    /**
     * Check if current screen is SORN edit screen
     */
    private function is_sorn_edit_screen(string $hook): bool {
        global $post_type;
        return $hook === 'post.php' && $post_type === 'sorn';
    }
}
