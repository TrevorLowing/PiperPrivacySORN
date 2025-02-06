<?php
/**
 * Main admin page template
 */

if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('SORN Manager', 'piper-privacy-sorn'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=piper-privacy-sorn-new')); ?>" class="page-title-action">
        <?php echo esc_html__('Add New', 'piper-privacy-sorn'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="piper-privacy-sorn-dashboard">
        <div class="piper-privacy-sorn-stats">
            <div class="stat-box">
                <h3><?php echo esc_html__('Total SORNs', 'piper-privacy-sorn'); ?></h3>
                <div class="stat-value" id="total-sorns">-</div>
            </div>
            <div class="stat-box">
                <h3><?php echo esc_html__('Pending Review', 'piper-privacy-sorn'); ?></h3>
                <div class="stat-value" id="pending-review">-</div>
            </div>
            <div class="stat-box">
                <h3><?php echo esc_html__('Published', 'piper-privacy-sorn'); ?></h3>
                <div class="stat-value" id="published-sorns">-</div>
            </div>
            <div class="stat-box">
                <h3><?php echo esc_html__('Federal Register', 'piper-privacy-sorn'); ?></h3>
                <div class="stat-value" id="fr-submissions">-</div>
            </div>
        </div>

        <div class="piper-privacy-sorn-filters">
            <select id="agency-filter">
                <option value=""><?php echo esc_html__('All Agencies', 'piper-privacy-sorn'); ?></option>
            </select>
            <select id="status-filter">
                <option value=""><?php echo esc_html__('All Statuses', 'piper-privacy-sorn'); ?></option>
                <option value="draft"><?php echo esc_html__('Draft', 'piper-privacy-sorn'); ?></option>
                <option value="review"><?php echo esc_html__('In Review', 'piper-privacy-sorn'); ?></option>
                <option value="published"><?php echo esc_html__('Published', 'piper-privacy-sorn'); ?></option>
            </select>
            <input type="text" id="search-input" placeholder="<?php echo esc_attr__('Search SORNs...', 'piper-privacy-sorn'); ?>">
        </div>

        <div class="piper-privacy-sorn-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-title column-primary sortable desc">
                            <a href="#"><span><?php echo esc_html__('Title', 'piper-privacy-sorn'); ?></span></a>
                        </th>
                        <th scope="col" class="manage-column column-agency sortable desc">
                            <a href="#"><span><?php echo esc_html__('Agency', 'piper-privacy-sorn'); ?></span></a>
                        </th>
                        <th scope="col" class="manage-column column-system-number">
                            <?php echo esc_html__('System Number', 'piper-privacy-sorn'); ?>
                        </th>
                        <th scope="col" class="manage-column column-status">
                            <?php echo esc_html__('Status', 'piper-privacy-sorn'); ?>
                        </th>
                        <th scope="col" class="manage-column column-updated">
                            <?php echo esc_html__('Last Updated', 'piper-privacy-sorn'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody id="sorn-list">
                    <tr>
                        <td colspan="5"><?php echo esc_html__('Loading...', 'piper-privacy-sorn'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="piper-privacy-sorn-pagination">
            <div class="tablenav-pages">
                <span class="displaying-num"></span>
                <span class="pagination-links">
                    <a class="first-page button" href="#"><span>«</span></a>
                    <a class="prev-page button" href="#"><span>‹</span></a>
                    <span class="paging-input">
                        <label for="current-page-selector" class="screen-reader-text">Current Page</label>
                        <input class="current-page" id="current-page-selector" type="text" value="1">
                        <span class="tablenav-paging-text"> of <span class="total-pages">0</span></span>
                    </span>
                    <a class="next-page button" href="#"><span>›</span></a>
                    <a class="last-page button" href="#"><span>»</span></a>
                </span>
            </div>
        </div>
    </div>
</div>
