<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Admin;

use PiperPrivacySorn\Services\GptTrainerApi;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package PiperPrivacySorn
 * @subpackage Admin
 */
class Admin {
    /**
     * The ID of this plugin.
     *
     * @var string
     */
    private string $plugin_name;

    /**
     * The version of this plugin.
     *
     * @var string
     */
    private string $version;

    /**
     * The GptTrainerApi instance.
     *
     * @var GptTrainerApi
     */
    private GptTrainerApi $api;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version    The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->api = new GptTrainerApi();
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles(): void {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/piper-privacy-sorn-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts(): void {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/piper-privacy-sorn-admin.js',
            ['jquery'],
            $this->version,
            true
        );

        // Add nonce for AJAX requests
        wp_localize_script($this->plugin_name, 'piperPrivacySornAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('piper_privacy_sorn_admin_nonce'),
            'i18n' => [
                'error' => __('An error occurred. Please try again.', 'piper-privacy-sorn'),
                'success' => __('Operation completed successfully.', 'piper-privacy-sorn'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'piper-privacy-sorn')
            ]
        ]);
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     */
    public function add_plugin_admin_menu(): void {
        add_menu_page(
            __('SORN Manager', 'piper-privacy-sorn'),
            __('SORN Manager', 'piper-privacy-sorn'),
            'manage_options',
            $this->plugin_name,
            [$this, 'display_plugin_admin_page'],
            'dashicons-privacy',
            30
        );

        add_submenu_page(
            $this->plugin_name,
            __('Settings', 'piper-privacy-sorn'),
            __('Settings', 'piper-privacy-sorn'),
            'manage_options',
            $this->plugin_name . '-settings',
            [$this, 'display_plugin_admin_settings']
        );
    }

    /**
     * Render the main admin page for this plugin.
     */
    public function display_plugin_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'piper-privacy-sorn'));
        }

        include_once plugin_dir_path(__FILE__) . 'partials/piper-privacy-sorn-admin-display.php';
    }

    /**
     * Render the settings page for this plugin.
     */
    public function display_plugin_admin_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'piper-privacy-sorn'));
        }

        // Check if this is a settings update
        if (isset($_POST['piper_privacy_sorn_settings_nonce'])) {
            $this->save_settings();
        }

        include_once plugin_dir_path(__FILE__) . 'partials/piper-privacy-sorn-admin-settings.php';
    }

    /**
     * Save plugin settings.
     */
    private function save_settings(): void {
        // Verify nonce
        if (!isset($_POST['piper_privacy_sorn_settings_nonce']) || 
            !wp_verify_nonce($_POST['piper_privacy_sorn_settings_nonce'], 'piper_privacy_sorn_settings')) {
            wp_die(__('Invalid nonce specified', 'piper-privacy-sorn'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'piper-privacy-sorn'));
        }

        // Save API token
        if (isset($_POST['gpt_trainer_api_token'])) {
            $api_token = sanitize_text_field($_POST['gpt_trainer_api_token']);
            update_option('gpt_trainer_api_token', $api_token);
        }

        // Add settings saved message
        add_settings_error(
            'piper_privacy_sorn_messages',
            'piper_privacy_sorn_message',
            __('Settings Saved', 'piper-privacy-sorn'),
            'updated'
        );
    }

    /**
     * Handle AJAX request to create a data source.
     */
    public function ajax_create_data_source(): void {
        // Verify nonce
        if (!check_ajax_referer('piper_privacy_sorn_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token sent.', 'piper-privacy-sorn')]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'piper-privacy-sorn')]);
        }

        // Validate and sanitize input
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', (array)$_POST['tags']) : [];

        if (empty($name) || empty($type)) {
            wp_send_json_error(['message' => __('Name and type are required.', 'piper-privacy-sorn')]);
        }

        try {
            $result = match($type) {
                'file' => $this->handle_file_upload($name, $tags),
                'url' => $this->handle_url_source($name, $_POST['url'] ?? '', $tags),
                'qa' => $this->handle_qa_source($name, $_POST['qa_pairs'] ?? [], $tags),
                default => throw new \InvalidArgumentException(__('Invalid source type.', 'piper-privacy-sorn'))
            };

            wp_send_json_success([
                'message' => __('Data source created successfully.', 'piper-privacy-sorn'),
                'data' => $result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle file upload for data source creation.
     *
     * @param string $name Source name
     * @param array  $tags Optional tags
     * @return array Response data
     * @throws \Exception If file upload fails
     */
    private function handle_file_upload(string $name, array $tags): array {
        if (!isset($_FILES['file'])) {
            throw new \InvalidArgumentException(__('No file was uploaded.', 'piper-privacy-sorn'));
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(
                sprintf(__('File upload failed with error code: %d', 'piper-privacy-sorn'), $file['error'])
            );
        }

        return $this->api->create_file_data_source($name, $file, $tags);
    }

    /**
     * Handle URL source creation.
     *
     * @param string $name Source name
     * @param string $url  Source URL
     * @param array  $tags Optional tags
     * @return array Response data
     * @throws \Exception If URL is invalid
     */
    private function handle_url_source(string $name, string $url, array $tags): array {
        if (!wp_http_validate_url($url)) {
            throw new \InvalidArgumentException(__('Invalid URL provided.', 'piper-privacy-sorn'));
        }

        return $this->api->create_url_data_source($name, $url, $tags);
    }

    /**
     * Handle Q&A source creation.
     *
     * @param string $name    Source name
     * @param array  $qaPairs Q&A pairs
     * @param array  $tags    Optional tags
     * @return array Response data
     * @throws \Exception If Q&A pairs are invalid
     */
    private function handle_qa_source(string $name, array $qaPairs, array $tags): array {
        if (empty($qaPairs)) {
            throw new \InvalidArgumentException(__('No Q&A pairs provided.', 'piper-privacy-sorn'));
        }

        // Validate Q&A pairs
        foreach ($qaPairs as $pair) {
            if (!isset($pair['question'], $pair['answer'])) {
                throw new \InvalidArgumentException(__('Invalid Q&A pair format.', 'piper-privacy-sorn'));
            }
        }

        return $this->api->create_qa_data_source($name, $qaPairs, $tags);
    }
}
