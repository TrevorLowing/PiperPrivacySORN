<?php
/**
 * Federal Register integration admin page
 *
 * @package PiperPrivacySorn
 * @subpackage Admin\Partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get settings
$api_key = get_option('federal_register_api_key', '');
$agency_id = get_option('federal_register_agency_id', '');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Federal Register Integration', 'piper-privacy-sorn'); ?></h1>
    <a href="#" class="page-title-action" id="import-from-fr"><?php _e('Import from Federal Register', 'piper-privacy-sorn'); ?></a>
    
    <hr class="wp-header-end">

    <!-- Settings Section -->
    <div class="fr-settings-section">
        <h2><?php _e('Settings', 'piper-privacy-sorn'); ?></h2>
        <form method="post" action="options.php" id="fr-settings-form">
            <?php settings_fields('piper_privacy_sorn_fr_settings'); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="federal_register_api_key"><?php _e('API Key', 'piper-privacy-sorn'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="federal_register_api_key" 
                               id="federal_register_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php _e('Your Federal Register API key. Get one at', 'piper-privacy-sorn'); ?>
                            <a href="https://www.federalregister.gov/developers/api_key" target="_blank">federalregister.gov</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="federal_register_agency_id"><?php _e('Agency ID', 'piper-privacy-sorn'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="federal_register_agency_id" 
                               id="federal_register_agency_id" 
                               value="<?php echo esc_attr($agency_id); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php _e('Your agency\'s Federal Register ID.', 'piper-privacy-sorn'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>

    <!-- Submissions Section -->
    <div class="fr-submissions-section">
        <h2><?php _e('Recent Submissions', 'piper-privacy-sorn'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped table-view-list submissions">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-title column-primary sortable desc">
                        <a href="#"><span><?php _e('Title', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-document-number">
                        <?php _e('Document Number', 'piper-privacy-sorn'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'piper-privacy-sorn'); ?>
                    </th>
                    <th scope="col" class="manage-column column-submitted-date sortable desc">
                        <a href="#"><span><?php _e('Submitted', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-published-date">
                        <?php _e('Published', 'piper-privacy-sorn'); ?>
                    </th>
                </tr>
            </thead>

            <tbody id="the-submissions-list">
                <!-- Submissions will be loaded here via AJAX -->
            </tbody>

            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-title column-primary sortable desc">
                        <a href="#"><span><?php _e('Title', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-document-number">
                        <?php _e('Document Number', 'piper-privacy-sorn'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'piper-privacy-sorn'); ?>
                    </th>
                    <th scope="col" class="manage-column column-submitted-date sortable desc">
                        <a href="#"><span><?php _e('Submitted', 'piper-privacy-sorn'); ?></span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" class="manage-column column-published-date">
                        <?php _e('Published', 'piper-privacy-sorn'); ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Import Modal -->
<div id="fr-import-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('Import from Federal Register', 'piper-privacy-sorn'); ?></h2>
        
        <form id="fr-search-form">
            <?php wp_nonce_field('piper_privacy_sorn_fr_search', 'fr_search_nonce'); ?>
            
            <div class="fr-search-fields">
                <div class="fr-search-field">
                    <label for="fr-search-term"><?php _e('Search Term', 'piper-privacy-sorn'); ?></label>
                    <input type="text" id="fr-search-term" name="term">
                </div>

                <div class="fr-search-field">
                    <label for="fr-search-agency"><?php _e('Agency', 'piper-privacy-sorn'); ?></label>
                    <select id="fr-search-agency" name="agency">
                        <option value=""><?php _e('All Agencies', 'piper-privacy-sorn'); ?></option>
                        <!-- Agencies will be loaded via AJAX -->
                    </select>
                </div>

                <div class="fr-search-field">
                    <label for="fr-search-date-start"><?php _e('Start Date', 'piper-privacy-sorn'); ?></label>
                    <input type="date" id="fr-search-date-start" name="date_start">
                </div>

                <div class="fr-search-field">
                    <label for="fr-search-date-end"><?php _e('End Date', 'piper-privacy-sorn'); ?></label>
                    <input type="date" id="fr-search-date-end" name="date_end">
                </div>
            </div>

            <div class="fr-search-actions">
                <input type="hidden" name="page" value="1">
                <?php submit_button(__('Search', 'piper-privacy-sorn'), 'primary', 'submit', false); ?>
                <span class="spinner"></span>
            </div>
        </form>

        <div id="fr-search-results">
            <!-- Search results will be loaded here -->
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="fr-preview-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="fr-preview-content">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>

<!-- Submission Details Modal -->
<div id="fr-submission-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('Submission Details', 'piper-privacy-sorn'); ?></h2>
        <div id="fr-submission-content">
            <!-- Submission details will be loaded here -->
        </div>
    </div>
</div>

<!-- Help Section -->
<div class="fr-help-section" style="display: none;">
    <h3><?php _e('Federal Register Integration Help', 'piper-privacy-sorn'); ?></h3>
    
    <div class="fr-help-content">
        <h4><?php _e('Getting Started', 'piper-privacy-sorn'); ?></h4>
        <ol>
            <li><?php _e('Obtain an API key from', 'piper-privacy-sorn'); ?> <a href="https://www.federalregister.gov/developers/api_key" target="_blank">federalregister.gov</a></li>
            <li><?php _e('Enter your API key in the settings section above', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Enter your agency\'s Federal Register ID', 'piper-privacy-sorn'); ?></li>
        </ol>

        <h4><?php _e('Importing SORNs', 'piper-privacy-sorn'); ?></h4>
        <ol>
            <li><?php _e('Click "Import from Federal Register" to open the search interface', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Use the search form to find existing SORNs', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Preview SORNs before importing them', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Click "Import" to add the SORN to your system', 'piper-privacy-sorn'); ?></li>
        </ol>

        <h4><?php _e('Submitting SORNs', 'piper-privacy-sorn'); ?></h4>
        <ol>
            <li><?php _e('Create or edit a SORN in the SORN Manager', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Click "Submit to Federal Register" when ready', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Review the submission preview', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Confirm submission', 'piper-privacy-sorn'); ?></li>
        </ol>

        <h4><?php _e('Tracking Submissions', 'piper-privacy-sorn'); ?></h4>
        <ol>
            <li><?php _e('View all submissions in the "Recent Submissions" table', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Click on a submission to view its details', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('Track the status of your submissions', 'piper-privacy-sorn'); ?></li>
            <li><?php _e('View published SORNs on federalregister.gov', 'piper-privacy-sorn'); ?></li>
        </ol>
    </div>

    <p class="fr-help-footer">
        <?php _e('For more information, visit the', 'piper-privacy-sorn'); ?> 
        <a href="https://www.federalregister.gov/developers" target="_blank"><?php _e('Federal Register API documentation', 'piper-privacy-sorn'); ?></a>
    </p>
</div>
