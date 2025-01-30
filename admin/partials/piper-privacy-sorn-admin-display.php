<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package PiperPrivacySorn
 * @subpackage Admin\Partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="notice notice-info">
        <p>
            <?php _e('Welcome to the SORN Manager. Use this interface to manage your System of Records Notices (SORNs) and integrate with the Federal Register.', 'piper-privacy-sorn'); ?>
        </p>
    </div>

    <div class="card">
        <h2><?php _e('Quick Actions', 'piper-privacy-sorn'); ?></h2>
        <p><?php _e('Common tasks and operations:', 'piper-privacy-sorn'); ?></p>
        
        <div class="quick-actions">
            <button type="button" class="button button-primary" id="create-data-source">
                <?php _e('Create Data Source', 'piper-privacy-sorn'); ?>
            </button>
            
            <button type="button" class="button" id="view-data-sources">
                <?php _e('View Data Sources', 'piper-privacy-sorn'); ?>
            </button>
            
            <button type="button" class="button" id="manage-chatbots">
                <?php _e('Manage Chatbots', 'piper-privacy-sorn'); ?>
            </button>
        </div>
    </div>

    <div id="data-source-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><?php _e('Create Data Source', 'piper-privacy-sorn'); ?></h2>
            
            <form id="create-data-source-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('piper_privacy_sorn_admin_nonce', 'nonce'); ?>
                
                <p>
                    <label for="source-name"><?php _e('Name:', 'piper-privacy-sorn'); ?></label>
                    <input type="text" id="source-name" name="name" required>
                </p>
                
                <p>
                    <label for="source-type"><?php _e('Type:', 'piper-privacy-sorn'); ?></label>
                    <select id="source-type" name="type" required>
                        <option value="file"><?php _e('File Upload', 'piper-privacy-sorn'); ?></option>
                        <option value="url"><?php _e('URL', 'piper-privacy-sorn'); ?></option>
                        <option value="qa"><?php _e('Q&A Pairs', 'piper-privacy-sorn'); ?></option>
                    </select>
                </p>
                
                <div id="file-upload-section" class="source-section">
                    <p>
                        <label for="source-file"><?php _e('File:', 'piper-privacy-sorn'); ?></label>
                        <input type="file" id="source-file" name="file" accept=".txt,.pdf,.json">
                    </p>
                </div>
                
                <div id="url-section" class="source-section" style="display: none;">
                    <p>
                        <label for="source-url"><?php _e('URL:', 'piper-privacy-sorn'); ?></label>
                        <input type="url" id="source-url" name="url">
                    </p>
                </div>
                
                <div id="qa-section" class="source-section" style="display: none;">
                    <div id="qa-pairs">
                        <div class="qa-pair">
                            <p>
                                <label><?php _e('Question:', 'piper-privacy-sorn'); ?></label>
                                <input type="text" name="qa_pairs[0][question]">
                            </p>
                            <p>
                                <label><?php _e('Answer:', 'piper-privacy-sorn'); ?></label>
                                <textarea name="qa_pairs[0][answer]"></textarea>
                            </p>
                        </div>
                    </div>
                    <button type="button" class="button" id="add-qa-pair">
                        <?php _e('Add Another Q&A Pair', 'piper-privacy-sorn'); ?>
                    </button>
                </div>
                
                <p>
                    <label for="source-tags"><?php _e('Tags (comma-separated):', 'piper-privacy-sorn'); ?></label>
                    <input type="text" id="source-tags" name="tags">
                </p>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Create Data Source', 'piper-privacy-sorn'); ?>">
                </p>
            </form>
        </div>
    </div>

    <div id="data-sources-container">
        <!-- Data sources will be loaded here via AJAX -->
    </div>
</div>
