<?php
/**
 * Provide a admin settings view for the plugin
 *
 * This file is used to markup the admin settings aspects of the plugin.
 *
 * @package PiperPrivacySorn
 * @subpackage Admin\Partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current settings
$api_token = get_option('gpt_trainer_api_token', '');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('piper_privacy_sorn_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('piper_privacy_sorn_settings', 'piper_privacy_sorn_settings_nonce'); ?>
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="gpt_trainer_api_token">
                        <?php _e('API Token', 'piper-privacy-sorn'); ?>
                    </label>
                </th>
                <td>
                    <input type="password" 
                           id="gpt_trainer_api_token" 
                           name="gpt_trainer_api_token" 
                           value="<?php echo esc_attr($api_token); ?>" 
                           class="regular-text"
                           autocomplete="off">
                    <p class="description">
                        <?php _e('Enter your GPT Trainer API token. This is required for the plugin to function.', 'piper-privacy-sorn'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Settings', 'piper-privacy-sorn')); ?>
    </form>
</div>
