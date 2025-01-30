<?php
/**
 * Federal Register submission history view
 */

// Verify user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'piper-privacy-sorn'));
}

// Get submission ID from query string
$submission_id = isset($_GET['submission']) ? (int) $_GET['submission'] : 0;
$submission = $submission_id ? \PiperPrivacySorn\Models\FederalRegisterSubmission::find($submission_id) : null;

// Get all submissions if no specific submission is selected
if (!$submission) {
    global $wpdb;
    $submissions = \PiperPrivacySorn\Models\FederalRegisterSubmission::get_recent(50);
}

// Get statuses for filtering
$statuses = \PiperPrivacySorn\Services\FederalRegisterSubmissionService::STATUSES;
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Federal Register Submissions', 'piper-privacy-sorn'); ?>
    </h1>

    <?php if ($submission): ?>
        <?php
        $sorn = get_post($submission->get_sorn_id());
        $events = $submission->get_events();
        ?>
        <div class="fr-submission-details">
            <div class="fr-submission-header">
                <h2><?php echo esc_html($sorn->post_title); ?></h2>
                <div class="fr-submission-meta">
                    <span class="fr-submission-id">
                        <?php _e('Submission ID:', 'piper-privacy-sorn'); ?> 
                        <?php echo esc_html($submission->get_submission_id()); ?>
                    </span>
                    <span class="fr-submission-status status-<?php echo esc_attr($submission->get_status()); ?>">
                        <?php echo esc_html(ucfirst($submission->get_status())); ?>
                    </span>
                </div>
            </div>

            <div class="fr-submission-timeline">
                <h3><?php _e('Submission Timeline', 'piper-privacy-sorn'); ?></h3>
                <div class="fr-timeline">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $event_data = json_decode($event['event_data'], true);
                        $event_class = 'event-' . $event['event_type'];
                        if ($event['event_type'] === 'error') {
                            $event_class .= ' event-error';
                        }
                        ?>
                        <div class="fr-timeline-item <?php echo esc_attr($event_class); ?>">
                            <div class="fr-timeline-marker"></div>
                            <div class="fr-timeline-content">
                                <div class="fr-timeline-date">
                                    <?php echo esc_html(
                                        wp_date(
                                            get_option('date_format') . ' ' . get_option('time_format'),
                                            strtotime($event['created_at'])
                                        )
                                    ); ?>
                                </div>
                                <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $event['event_type']))); ?></h4>
                                <?php if ($event_data): ?>
                                    <div class="fr-timeline-details">
                                        <?php foreach ($event_data as $key => $value): ?>
                                            <?php if (is_string($value) || is_numeric($value)): ?>
                                                <div class="fr-timeline-detail">
                                                    <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong>
                                                    <?php echo esc_html($value); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($submission->get_document_number()): ?>
                <div class="fr-document-details">
                    <h3><?php _e('Federal Register Document', 'piper-privacy-sorn'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Document Number', 'piper-privacy-sorn'); ?></th>
                            <td><?php echo esc_html($submission->get_document_number()); ?></td>
                        </tr>
                        <?php if ($submission->get_published_at()): ?>
                            <tr>
                                <th><?php _e('Publication Date', 'piper-privacy-sorn'); ?></th>
                                <td><?php echo esc_html(wp_date(get_option('date_format'), strtotime($submission->get_published_at()))); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>

            <div class="fr-submission-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=piper-privacy-sorn-federal-register')); ?>" class="button">
                    <?php _e('Back to List', 'piper-privacy-sorn'); ?>
                </a>
                <?php if ($submission->get_status() === $statuses['ERROR']): ?>
                    <button class="button button-primary" id="fr-retry-submission" data-submission-id="<?php echo esc_attr($submission->get_id()); ?>">
                        <?php _e('Retry Submission', 'piper-privacy-sorn'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="fr-submissions-list">
            <div class="fr-submissions-filters">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="piper-privacy-sorn-federal-register">
                    
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'piper-privacy-sorn'); ?></option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($current_status, $status); ?>>
                                <?php echo esc_html(ucfirst($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'piper-privacy-sorn'); ?>">

                    <div class="fr-bulk-actions">
                        <select name="bulk_action">
                            <option value=""><?php _e('Bulk Actions', 'piper-privacy-sorn'); ?></option>
                            <option value="retry"><?php _e('Retry Failed', 'piper-privacy-sorn'); ?></option>
                            <option value="archive"><?php _e('Archive', 'piper-privacy-sorn'); ?></option>
                            <option value="export"><?php _e('Export to CSV', 'piper-privacy-sorn'); ?></option>
                        </select>
                        <button type="button" class="button" id="fr-bulk-apply">
                            <?php _e('Apply', 'piper-privacy-sorn'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" id="fr-select-all">
                        </th>
                        <th><?php _e('SORN', 'piper-privacy-sorn'); ?></th>
                        <th><?php _e('Submission ID', 'piper-privacy-sorn'); ?></th>
                        <th><?php _e('Status', 'piper-privacy-sorn'); ?></th>
                        <th><?php _e('Document #', 'piper-privacy-sorn'); ?></th>
                        <th><?php _e('Submitted', 'piper-privacy-sorn'); ?></th>
                        <th><?php _e('Published', 'piper-privacy-sorn'); ?></th>
                        <th><?php _e('Actions', 'piper-privacy-sorn'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions): ?>
                        <?php foreach ($submissions as $sub): ?>
                            <?php $sorn = get_post($sub->get_sorn_id()); ?>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" name="submission_ids[]" value="<?php echo esc_attr($sub->get_id()); ?>">
                                </td>
                                <td>
                                    <?php if ($sorn): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($sorn->ID)); ?>">
                                            <?php echo esc_html($sorn->post_title); ?>
                                        </a>
                                    <?php else: ?>
                                        <em><?php _e('SORN Deleted', 'piper-privacy-sorn'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($sub->get_submission_id()); ?></td>
                                <td>
                                    <span class="fr-status status-<?php echo esc_attr($sub->get_status()); ?>">
                                        <?php echo esc_html(ucfirst($sub->get_status())); ?>
                                    </span>
                                </td>
                                <td><?php echo $sub->get_document_number() ? esc_html($sub->get_document_number()) : '—'; ?></td>
                                <td>
                                    <?php echo esc_html(
                                        wp_date(
                                            get_option('date_format'),
                                            strtotime($sub->get_submitted_at())
                                        )
                                    ); ?>
                                </td>
                                <td>
                                    <?php if ($sub->get_published_at()): ?>
                                        <?php echo esc_html(
                                            wp_date(
                                                get_option('date_format'),
                                                strtotime($sub->get_published_at())
                                            )
                                        ); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['submission' => $sub->get_id()])); ?>" class="button button-small">
                                        <?php _e('View Details', 'piper-privacy-sorn'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <?php _e('No submissions found.', 'piper-privacy-sorn'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
