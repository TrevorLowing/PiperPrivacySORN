<?php
/**
 * Admin interface for SORN management
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
    <h1 class="wp-heading-inline"><?php _e('SORN Manager', 'piper-privacy-sorn'); ?></h1>
    <a href="#" class="page-title-action" id="create-sorn"><?php _e('Add New SORN', 'piper-privacy-sorn'); ?></a>
    
    <hr class="wp-header-end">

    <!-- Search and Filter Form -->
    <div class="tablenav top">
        <form method="get" id="sorn-search-form">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
            
            <div class="alignleft actions">
                <label for="filter-by-agency" class="screen-reader-text"><?php _e('Filter by agency', 'piper-privacy-sorn'); ?></label>
                <select name="agency" id="filter-by-agency">
                    <option value=""><?php _e('All agencies', 'piper-privacy-sorn'); ?></option>
                    <?php
                    // Add agency options dynamically
                    ?>
                </select>

                <label for="filter-by-status" class="screen-reader-text"><?php _e('Filter by status', 'piper-privacy-sorn'); ?></label>
                <select name="status" id="filter-by-status">
                    <option value=""><?php _e('All statuses', 'piper-privacy-sorn'); ?></option>
                    <option value="draft"><?php _e('Draft', 'piper-privacy-sorn'); ?></option>
                    <option value="review"><?php _e('In Review', 'piper-privacy-sorn'); ?></option>
                    <option value="published"><?php _e('Published', 'piper-privacy-sorn'); ?></option>
                    <option value="archived"><?php _e('Archived', 'piper-privacy-sorn'); ?></option>
                </select>

                <?php submit_button(__('Filter', 'piper-privacy-sorn'), '', 'filter_action', false); ?>
            </div>

            <div class="search-box">
                <label class="screen-reader-text" for="sorn-search-input"><?php _e('Search SORNs', 'piper-privacy-sorn'); ?></label>
                <input type="search" id="sorn-search-input" name="s" value="<?php echo esc_attr(wp_unslash($_REQUEST['s'] ?? '')); ?>">
                <?php submit_button(__('Search SORNs', 'piper-privacy-sorn'), '', 'search', false); ?>
            </div>
        </form>
    </div>

    <!-- SORN List Table -->
    <table class="wp-list-table widefat fixed striped table-view-list sorns">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-title column-primary sortable desc">
                    <a href="#"><span><?php _e('Title', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                </th>
                <th scope="col" class="manage-column column-identifier"><?php _e('Identifier', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-agency"><?php _e('Agency', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-status"><?php _e('Status', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-version"><?php _e('Version', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-date sortable desc">
                    <a href="#"><span><?php _e('Last Updated', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                </th>
            </tr>
        </thead>

        <tbody id="the-list">
            <!-- SORN items will be loaded here via AJAX -->
        </tbody>

        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-title column-primary sortable desc">
                    <a href="#"><span><?php _e('Title', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                </th>
                <th scope="col" class="manage-column column-identifier"><?php _e('Identifier', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-agency"><?php _e('Agency', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-status"><?php _e('Status', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-version"><?php _e('Version', 'piper-privacy-sorn'); ?></th>
                <th scope="col" class="manage-column column-date sortable desc">
                    <a href="#"><span><?php _e('Last Updated', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                </th>
            </tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <!-- Pagination will be added here -->
    </div>
</div>

<!-- SORN Editor Modal -->
<div id="sorn-editor-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="sorn-editor-title"><?php _e('Create New SORN', 'piper-privacy-sorn'); ?></h2>
        
        <form id="sorn-editor-form" method="post">
            <?php wp_nonce_field('piper_privacy_sorn_editor', 'sorn_editor_nonce'); ?>
            <input type="hidden" name="sorn_id" id="sorn-id" value="">
            
            <div class="form-field">
                <label for="sorn-title"><?php _e('Title', 'piper-privacy-sorn'); ?> <span class="required">*</span></label>
                <input type="text" id="sorn-title" name="title" required>
            </div>

            <div class="form-field">
                <label for="sorn-system-name"><?php _e('System Name', 'piper-privacy-sorn'); ?> <span class="required">*</span></label>
                <input type="text" id="sorn-system-name" name="system_name" required>
            </div>

            <div class="form-field">
                <label for="sorn-identifier"><?php _e('Identifier', 'piper-privacy-sorn'); ?> <span class="required">*</span></label>
                <input type="text" id="sorn-identifier" name="identifier" required>
            </div>

            <div class="form-field">
                <label for="sorn-agency"><?php _e('Agency', 'piper-privacy-sorn'); ?> <span class="required">*</span></label>
                <input type="text" id="sorn-agency" name="agency" required>
            </div>

            <div class="form-field">
                <label for="sorn-purpose"><?php _e('Purpose', 'piper-privacy-sorn'); ?></label>
                <?php
                wp_editor('', 'sorn-purpose', [
                    'textarea_name' => 'purpose',
                    'media_buttons' => false,
                    'textarea_rows' => 5,
                    'teeny' => true
                ]);
                ?>
            </div>

            <div class="form-field">
                <label for="sorn-categories"><?php _e('Categories of Records', 'piper-privacy-sorn'); ?></label>
                <?php
                wp_editor('', 'sorn-categories', [
                    'textarea_name' => 'categories',
                    'media_buttons' => false,
                    'textarea_rows' => 5,
                    'teeny' => true
                ]);
                ?>
            </div>

            <div class="form-field">
                <label for="sorn-authority"><?php _e('Authority', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-authority" name="authority" rows="3"></textarea>
            </div>

            <div class="form-field">
                <label for="sorn-routine-uses"><?php _e('Routine Uses', 'piper-privacy-sorn'); ?></label>
                <?php
                wp_editor('', 'sorn-routine-uses', [
                    'textarea_name' => 'routine_uses',
                    'media_buttons' => false,
                    'textarea_rows' => 5,
                    'teeny' => true
                ]);
                ?>
            </div>

            <div class="form-field">
                <label for="sorn-retention"><?php _e('Retention', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-retention" name="retention" rows="3"></textarea>
            </div>

            <div class="form-field">
                <label for="sorn-safeguards"><?php _e('Safeguards', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-safeguards" name="safeguards" rows="3"></textarea>
            </div>

            <div class="form-field">
                <label for="sorn-access"><?php _e('Access Procedures', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-access" name="access_procedures" rows="3"></textarea>
            </div>

            <div class="form-field">
                <label for="sorn-contesting"><?php _e('Contesting Procedures', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-contesting" name="contesting_procedures" rows="3"></textarea>
            </div>

            <div class="form-field">
                <label for="sorn-notification"><?php _e('Notification Procedures', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-notification" name="notification_procedures" rows="3"></textarea>
            </div>

            <div class="form-field">
                <label for="sorn-exemptions"><?php _e('Exemptions Claimed', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-exemptions" name="exemptions" rows="3"></textarea>
            </div>

            <div class="form-field">
                <label for="sorn-history"><?php _e('History', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-history" name="history" rows="3"></textarea>
            </div>

            <div class="form-field version-note" style="display: none;">
                <label for="sorn-change-description"><?php _e('Change Description', 'piper-privacy-sorn'); ?></label>
                <textarea id="sorn-change-description" name="change_description" rows="2"></textarea>
                <p class="description"><?php _e('Briefly describe the changes made in this version.', 'piper-privacy-sorn'); ?></p>
            </div>

            <div class="submit-wrapper">
                <input type="submit" class="button button-primary" value="<?php _e('Save SORN', 'piper-privacy-sorn'); ?>">
                <span class="spinner"></span>
            </div>
        </form>
    </div>
</div>

<!-- Version History Modal -->
<div id="version-history-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('Version History', 'piper-privacy-sorn'); ?></h2>
        <div id="version-history-content">
            <!-- Version history will be loaded here via AJAX -->
        </div>
    </div>
</div>

<!-- Comments Modal -->
<div id="comments-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('Comments', 'piper-privacy-sorn'); ?></h2>
        <div id="comments-content">
            <!-- Comments will be loaded here via AJAX -->
        </div>
        <form id="add-comment-form">
            <?php wp_nonce_field('piper_privacy_sorn_comment', 'comment_nonce'); ?>
            <input type="hidden" name="sorn_id" value="">
            <div class="form-field">
                <label for="comment-text"><?php _e('Add Comment', 'piper-privacy-sorn'); ?></label>
                <textarea id="comment-text" name="comment" rows="3" required></textarea>
            </div>
            <div class="submit-wrapper">
                <input type="submit" class="button button-primary" value="<?php _e('Add Comment', 'piper-privacy-sorn'); ?>">
                <span class="spinner"></span>
            </div>
        </form>
    </div>
</div>
