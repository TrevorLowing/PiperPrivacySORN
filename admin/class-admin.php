<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Admin;

/**
 * The admin-specific functionality of the plugin
 */
class Admin {
    /**
     * Initialize the class
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register the admin menu pages
     */
    public function add_menu_pages(): void {
        add_menu_page(
            'SORN Manager',
            'SORN Manager',
            'manage_options',
            'piper-privacy-sorn',
            [$this, 'render_main_page'],
            'dashicons-shield',
            30
        );

        add_submenu_page(
            'piper-privacy-sorn',
            'All SORNs',
            'All SORNs',
            'manage_options',
            'piper-privacy-sorn',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'piper-privacy-sorn',
            'Add New SORN',
            'Add New',
            'manage_options',
            'piper-privacy-sorn-new',
            [$this, 'render_new_sorn_page']
        );

        add_submenu_page(
            'piper-privacy-sorn',
            'FedRAMP Systems',
            'FedRAMP Systems',
            'manage_options',
            'piper-privacy-sorn-fedramp',
            [$this, 'render_fedramp_page']
        );

        add_submenu_page(
            'piper-privacy-sorn',
            'Settings',
            'Settings',
            'manage_options',
            'piper-privacy-sorn-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets(): void {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'piper-privacy-sorn') === false) {
            return;
        }

        wp_enqueue_style(
            'piper-privacy-sorn-admin',
            PIPER_PRIVACY_SORN_URL . 'admin/css/admin.css',
            [],
            PIPER_PRIVACY_SORN_VERSION
        );

        wp_enqueue_script(
            'piper-privacy-sorn-admin',
            PIPER_PRIVACY_SORN_URL . 'admin/js/admin.js',
            ['jquery', 'wp-api', 'wp-element'],
            PIPER_PRIVACY_SORN_VERSION,
            true
        );

        wp_localize_script('piper-privacy-sorn-admin', 'piperPrivacySorn', [
            'restUrl' => rest_url('piper-privacy-sorn/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'saved' => __('Settings saved successfully.', 'piper-privacy-sorn'),
                'error' => __('An error occurred.', 'piper-privacy-sorn')
            ]
        ]);
    }

    /**
     * Register plugin settings
     */
    public function register_settings(): void {
        register_setting('piper_privacy_sorn_settings', 'piper_privacy_sorn_fr_api_key');
        register_setting('piper_privacy_sorn_settings', 'piper_privacy_sorn_gpt_api_key');
        register_setting('piper_privacy_sorn_settings', 'piper_privacy_sorn_slack_webhook');
        register_setting('piper_privacy_sorn_settings', 'piper_privacy_sorn_teams_webhook');
        register_setting('piper_privacy_sorn_settings', 'piper_privacy_sorn_notification_email');
    }

    /**
     * Render the main admin page
     */
    public function render_main_page(): void {
        require_once PIPER_PRIVACY_SORN_DIR . 'admin/partials/main-page.php';
    }

    /**
     * Render the new SORN page
     */
    public function render_new_sorn_page(): void {
        require_once PIPER_PRIVACY_SORN_DIR . 'admin/partials/new-sorn-page.php';
    }

    /**
     * Render the FedRAMP systems page
     */
    public function render_fedramp_page(): void {
        require_once PIPER_PRIVACY_SORN_DIR . 'admin/partials/fedramp-page.php';
    }

    /**
     * Render the settings page
     */
    public function render_settings_page(): void {
        require_once PIPER_PRIVACY_SORN_DIR . 'admin/partials/settings-page.php';
    }
}
