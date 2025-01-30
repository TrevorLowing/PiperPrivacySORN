<?php
/**
 * Federal Register submission dashboard
 */

// Verify user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'piper-privacy-sorn'));
}

// Get submission statistics
global $wpdb;
$stats = [
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions"),
    'pending' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions WHERE status IN (%s, %s, %s)",
        'submitted',
        'in_review',
        'approved'
    )),
    'published' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions WHERE status = %s",
        'published'
    )),
    'errors' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions WHERE status IN (%s, %s)",
        'error',
        'rejected'
    ))
];

// Get recent activity
$recent_activity = $wpdb->get_results(
    "SELECT e.*, s.sorn_id, s.status
    FROM {$wpdb->prefix}piper_privacy_sorn_fr_submission_events e
    JOIN {$wpdb->prefix}piper_privacy_sorn_fr_submissions s ON e.submission_id = s.submission_id
    ORDER BY e.created_at DESC
    LIMIT 10"
);

// Get submissions by status
$status_data = $wpdb->get_results(
    "SELECT status, COUNT(*) as count
    FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions
    GROUP BY status"
);

// Get submissions by month
$monthly_data = $wpdb->get_results(
    "SELECT DATE_FORMAT(submitted_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published
    FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Federal Register Dashboard', 'piper-privacy-sorn'); ?>
    </h1>

    <div class="fr-dashboard">
        <!-- Statistics Cards -->
        <div class="fr-stats-grid">
            <div class="fr-stat-card">
                <div class="fr-stat-icon total"></div>
                <div class="fr-stat-content">
                    <h3><?php _e('Total Submissions', 'piper-privacy-sorn'); ?></h3>
                    <div class="fr-stat-number"><?php echo esc_html($stats['total']); ?></div>
                </div>
            </div>

            <div class="fr-stat-card">
                <div class="fr-stat-icon pending"></div>
                <div class="fr-stat-content">
                    <h3><?php _e('Pending Review', 'piper-privacy-sorn'); ?></h3>
                    <div class="fr-stat-number"><?php echo esc_html($stats['pending']); ?></div>
                </div>
            </div>

            <div class="fr-stat-card">
                <div class="fr-stat-icon published"></div>
                <div class="fr-stat-content">
                    <h3><?php _e('Published', 'piper-privacy-sorn'); ?></h3>
                    <div class="fr-stat-number"><?php echo esc_html($stats['published']); ?></div>
                </div>
            </div>

            <div class="fr-stat-card">
                <div class="fr-stat-icon error"></div>
                <div class="fr-stat-content">
                    <h3><?php _e('Issues', 'piper-privacy-sorn'); ?></h3>
                    <div class="fr-stat-number"><?php echo esc_html($stats['errors']); ?></div>
                </div>
            </div>
        </div>

        <div class="fr-dashboard-grid">
            <!-- Submission Chart -->
            <div class="fr-dashboard-card fr-chart-card">
                <h3><?php _e('Submission Trends', 'piper-privacy-sorn'); ?></h3>
                <canvas id="fr-submissions-chart"></canvas>
                <script>
                    var monthlyData = <?php echo wp_json_encode(array_reverse($monthly_data)); ?>;
                    var labels = monthlyData.map(function(item) {
                        return new Date(item.month + '-01').toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short'
                        });
                    });
                    var totalData = monthlyData.map(function(item) {
                        return parseInt(item.total);
                    });
                    var publishedData = monthlyData.map(function(item) {
                        return parseInt(item.published);
                    });

                    new Chart(document.getElementById('fr-submissions-chart'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '<?php _e('Total Submissions', 'piper-privacy-sorn'); ?>',
                                data: totalData,
                                borderColor: '#0073aa',
                                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                                fill: true
                            }, {
                                label: '<?php _e('Published', 'piper-privacy-sorn'); ?>',
                                data: publishedData,
                                borderColor: '#46b450',
                                backgroundColor: 'rgba(70, 180, 80, 0.1)',
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                </script>
            </div>

            <!-- Status Distribution -->
            <div class="fr-dashboard-card fr-status-card">
                <h3><?php _e('Status Distribution', 'piper-privacy-sorn'); ?></h3>
                <canvas id="fr-status-chart"></canvas>
                <script>
                    var statusData = <?php echo wp_json_encode($status_data); ?>;
                    var statusColors = {
                        submitted: '#0073aa',
                        in_review: '#ffc107',
                        approved: '#46b450',
                        scheduled: '#9c27b0',
                        published: '#46b450',
                        rejected: '#dc3545',
                        error: '#dc3545'
                    };

                    new Chart(document.getElementById('fr-status-chart'), {
                        type: 'doughnut',
                        data: {
                            labels: statusData.map(function(item) {
                                return item.status.charAt(0).toUpperCase() + item.status.slice(1);
                            }),
                            datasets: [{
                                data: statusData.map(function(item) {
                                    return parseInt(item.count);
                                }),
                                backgroundColor: statusData.map(function(item) {
                                    return statusColors[item.status];
                                })
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right'
                                }
                            }
                        }
                    });
                </script>
            </div>

            <!-- Recent Activity -->
            <div class="fr-dashboard-card fr-activity-card">
                <h3><?php _e('Recent Activity', 'piper-privacy-sorn'); ?></h3>
                <div class="fr-activity-list">
                    <?php foreach ($recent_activity as $activity): ?>
                        <?php
                        $sorn = get_post($activity->sorn_id);
                        $event_data = json_decode($activity->event_data, true);
                        ?>
                        <div class="fr-activity-item">
                            <div class="fr-activity-icon <?php echo esc_attr($activity->event_type); ?>"></div>
                            <div class="fr-activity-content">
                                <div class="fr-activity-title">
                                    <?php if ($sorn): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($sorn->ID)); ?>">
                                            <?php echo esc_html($sorn->post_title); ?>
                                        </a>
                                    <?php else: ?>
                                        <em><?php _e('SORN Deleted', 'piper-privacy-sorn'); ?></em>
                                    <?php endif; ?>
                                </div>
                                <div class="fr-activity-details">
                                    <?php
                                    $event_message = '';
                                    switch ($activity->event_type) {
                                        case 'submitted':
                                            $event_message = __('Submitted to Federal Register', 'piper-privacy-sorn');
                                            break;
                                        case 'status_changed':
                                            $event_message = sprintf(
                                                __('Status changed from %s to %s', 'piper-privacy-sorn'),
                                                $event_data['old_status'],
                                                $event_data['new_status']
                                            );
                                            break;
                                        case 'error':
                                            $event_message = sprintf(
                                                __('Error: %s', 'piper-privacy-sorn'),
                                                $event_data['message']
                                            );
                                            break;
                                        default:
                                            $event_message = ucfirst(str_replace('_', ' ', $activity->event_type));
                                    }
                                    echo esc_html($event_message);
                                    ?>
                                </div>
                                <div class="fr-activity-meta">
                                    <?php echo esc_html(
                                        sprintf(
                                            __('%s ago', 'piper-privacy-sorn'),
                                            human_time_diff(
                                                strtotime($activity->created_at),
                                                current_time('timestamp')
                                            )
                                        )
                                    ); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="fr-dashboard-card fr-actions-card">
                <h3><?php _e('Quick Actions', 'piper-privacy-sorn'); ?></h3>
                <div class="fr-quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piper-privacy-sorn-federal-register&action=new')); ?>" class="button button-primary">
                        <?php _e('New Submission', 'piper-privacy-sorn'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piper-privacy-sorn-federal-register&view=history')); ?>" class="button">
                        <?php _e('View History', 'piper-privacy-sorn'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=piper-privacy-sorn-federal-register&view=settings')); ?>" class="button">
                        <?php _e('Settings', 'piper-privacy-sorn'); ?>
                    </a>
                </div>

                <?php if ($stats['errors'] > 0): ?>
                    <div class="fr-action-alert">
                        <p>
                            <?php echo esc_html(sprintf(
                                _n(
                                    'There is %s submission that needs attention.',
                                    'There are %s submissions that need attention.',
                                    $stats['errors'],
                                    'piper-privacy-sorn'
                                ),
                                number_format_i18n($stats['errors'])
                            )); ?>
                        </p>
                        <a href="<?php echo esc_url(add_query_arg(['status' => 'error'])); ?>" class="button button-small">
                            <?php _e('Review Issues', 'piper-privacy-sorn'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
