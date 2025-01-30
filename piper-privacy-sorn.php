<?php
/**
 * Plugin Name: PiperPrivacy SORN Manager
 * Plugin URI: https://piperprivacy.com/sorn-manager
 * Description: AI-powered SORN management system with Federal Register integration and FedRAMP system catalog
 * Version: 1.0.0
 * Author: PiperPrivacy
 * Author URI: https://piperprivacy.com
 * Text Domain: piper-privacy-sorn
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('PIPER_PRIVACY_SORN_VERSION', '1.0.0');

// Plugin directory
define('PIPER_PRIVACY_SORN_DIR', plugin_dir_path(__FILE__));
define('PIPER_PRIVACY_SORN_URL', plugin_dir_url(__FILE__));

// Composer autoloader
if (file_exists(PIPER_PRIVACY_SORN_DIR . 'vendor/autoload.php')) {
    require_once PIPER_PRIVACY_SORN_DIR . 'vendor/autoload.php';
}

// Initialize the plugin
function piper_privacy_sorn_init() {
    // Load plugin dependencies
    require_once PIPER_PRIVACY_SORN_DIR . 'includes/PiperPrivacySorn.php';
    
    // Initialize main plugin class
    $plugin = new PiperPrivacySorn\PiperPrivacySorn();
    $plugin->run();
}

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once PIPER_PRIVACY_SORN_DIR . 'includes/Activator.php';
    PiperPrivacySorn\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    require_once PIPER_PRIVACY_SORN_DIR . 'includes/Deactivator.php';
    PiperPrivacySorn\Deactivator::deactivate();
});

// Initialize plugin
add_action('plugins_loaded', 'piper_privacy_sorn_init');
