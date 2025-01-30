<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Notifications;

use PiperPrivacySorn\Models\FederalRegisterSubmission;
use PiperPrivacySorn\Services\FederalRegisterApi;
use PiperPrivacySorn\Services\FederalRegisterSubmissionService;

/**
 * Handles Federal Register notifications
 */
class FederalRegisterNotifications {
    /**
     * @var FederalRegisterApi
     */
    private FederalRegisterApi $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new FederalRegisterApi();

        // Register hooks
        add_action('piper_privacy_sorn_fr_submission_created', [$this, 'notify_submission_created'], 10, 1);
        add_action('piper_privacy_sorn_fr_status_changed', [$this, 'notify_status_changed'], 10, 3);
        add_action('piper_privacy_sorn_fr_submission_published', [$this, 'notify_publication'], 10, 2);
        add_action('piper_privacy_sorn_fr_submission_error', [$this, 'notify_error'], 10, 2);

        // Admin notification preferences
        add_action('admin_init', [$this, 'register_notification_settings']);
        add_action('show_user_profile', [$this, 'add_notification_preferences']);
        add_action('edit_user_profile', [$this, 'add_notification_preferences']);
        add_action('personal_options_update', [$this, 'save_notification_preferences']);
        add_action('edit_user_profile_update', [$this, 'save_notification_preferences']);
    }

    /**
     * Register notification settings
     */
    public function register_notification_settings(): void {
        register_setting(
            'piper_privacy_sorn_options',
            'piper_privacy_sorn_fr_notifications',
            [
                'type' => 'array',
                'description' => __('Federal Register notification settings', 'piper-privacy-sorn'),
                'sanitize_callback' => [$this, 'sanitize_notification_settings'],
                'default' => [
                    'email_notifications' => true,
                    'admin_notifications' => true,
                    'slack_webhook_url' => '',
                    'teams_webhook_url' => ''
                ]
            ]
        );
    }

    /**
     * Add notification preferences to user profile
     *
     * @param \WP_User $user User object
     */
    public function add_notification_preferences(\WP_User $user): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $preferences = get_user_meta($user->ID, 'piper_privacy_sorn_fr_preferences', true) ?: [];
        ?>
        <h3><?php _e('Federal Register Notifications', 'piper-privacy-sorn'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="fr_notify_submissions">
                        <?php _e('New Submissions', 'piper-privacy-sorn'); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" name="fr_preferences[notify_submissions]" id="fr_notify_submissions"
                        <?php checked($preferences['notify_submissions'] ?? true); ?>>
                    <span class="description">
                        <?php _e('Receive notifications for new Federal Register submissions', 'piper-privacy-sorn'); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="fr_notify_status">
                        <?php _e('Status Changes', 'piper-privacy-sorn'); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" name="fr_preferences[notify_status]" id="fr_notify_status"
                        <?php checked($preferences['notify_status'] ?? true); ?>>
                    <span class="description">
                        <?php _e('Receive notifications when submission status changes', 'piper-privacy-sorn'); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="fr_notify_publications">
                        <?php _e('Publications', 'piper-privacy-sorn'); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" name="fr_preferences[notify_publications]" id="fr_notify_publications"
                        <?php checked($preferences['notify_publications'] ?? true); ?>>
                    <span class="description">
                        <?php _e('Receive notifications when SORNs are published', 'piper-privacy-sorn'); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="fr_notify_errors">
                        <?php _e('Errors', 'piper-privacy-sorn'); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" name="fr_preferences[notify_errors]" id="fr_notify_errors"
                        <?php checked($preferences['notify_errors'] ?? true); ?>>
                    <span class="description">
                        <?php _e('Receive notifications for submission errors', 'piper-privacy-sorn'); ?>
                    </span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save notification preferences
     *
     * @param int $user_id User ID
     */
    public function save_notification_preferences(int $user_id): void {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['fr_preferences'])) {
            update_user_meta(
                $user_id,
                'piper_privacy_sorn_fr_preferences',
                $this->sanitize_preferences($_POST['fr_preferences'])
            );
        }
    }

    /**
     * Sanitize notification settings
     *
     * @param array $settings Settings to sanitize
     * @return array Sanitized settings
     */
    public function sanitize_notification_settings(array $settings): array {
        return [
            'email_notifications' => (bool) ($settings['email_notifications'] ?? true),
            'admin_notifications' => (bool) ($settings['admin_notifications'] ?? true),
            'slack_webhook_url' => esc_url_raw($settings['slack_webhook_url'] ?? ''),
            'teams_webhook_url' => esc_url_raw($settings['teams_webhook_url'] ?? '')
        ];
    }

    /**
     * Sanitize user preferences
     *
     * @param array $preferences Preferences to sanitize
     * @return array Sanitized preferences
     */
    private function sanitize_preferences(array $preferences): array {
        return [
            'notify_submissions' => (bool) ($preferences['notify_submissions'] ?? true),
            'notify_status' => (bool) ($preferences['notify_status'] ?? true),
            'notify_publications' => (bool) ($preferences['notify_publications'] ?? true),
            'notify_errors' => (bool) ($preferences['notify_errors'] ?? true)
        ];
    }

    /**
     * Notify about new submission
     *
     * @param FederalRegisterSubmission $submission New submission
     */
    public function notify_submission_created(FederalRegisterSubmission $submission): void {
        $sorn = get_post($submission->get_sorn_id());
        if (!$sorn) {
            return;
        }

        $notification = [
            'title' => sprintf(
                __('New Federal Register Submission: %s', 'piper-privacy-sorn'),
                $sorn->post_title
            ),
            'message' => sprintf(
                __('A new SORN has been submitted to the Federal Register.

Submission Details:
- SORN: %s
- Submission ID: %s
- Status: %s
- Submitted: %s

View the submission status and details in the admin panel.', 'piper-privacy-sorn'),
                $sorn->post_title,
                $submission->get_submission_id(),
                $submission->get_status(),
                $submission->get_submitted_at()
            ),
            'url' => $this->get_submission_url($submission),
            'type' => 'submission'
        ];

        $this->send_notifications($notification, $submission->get_sorn_id());
    }

    /**
     * Notify about status change
     *
     * @param FederalRegisterSubmission $submission Submission that changed
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    public function notify_status_changed(
        FederalRegisterSubmission $submission,
        string $old_status,
        string $new_status
    ): void {
        $sorn = get_post($submission->get_sorn_id());
        if (!$sorn) {
            return;
        }

        $notification = [
            'title' => sprintf(
                __('Federal Register Status Change: %s', 'piper-privacy-sorn'),
                $sorn->post_title
            ),
            'message' => sprintf(
                __('The Federal Register submission status has changed.

Submission Details:
- SORN: %s
- Submission ID: %s
- Old Status: %s
- New Status: %s
- Last Updated: %s

View the submission details in the admin panel.', 'piper-privacy-sorn'),
                $sorn->post_title,
                $submission->get_submission_id(),
                $old_status,
                $new_status,
                $submission->get_updated_at()
            ),
            'url' => $this->get_submission_url($submission),
            'type' => 'status'
        ];

        $this->send_notifications($notification, $submission->get_sorn_id());
    }

    /**
     * Notify about publication
     *
     * @param FederalRegisterSubmission $submission Published submission
     * @param array $document Federal Register document data
     */
    public function notify_publication(
        FederalRegisterSubmission $submission,
        array $document
    ): void {
        $sorn = get_post($submission->get_sorn_id());
        if (!$sorn) {
            return;
        }

        $notification = [
            'title' => sprintf(
                __('SORN Published: %s', 'piper-privacy-sorn'),
                $sorn->post_title
            ),
            'message' => sprintf(
                __('Your SORN has been published in the Federal Register.

Publication Details:
- SORN: %s
- Document Number: %s
- Publication Date: %s
- Effective Date: %s

View Online:
- HTML: %s
- PDF: %s', 'piper-privacy-sorn'),
                $sorn->post_title,
                $document['document_number'],
                $document['publication_date'],
                $document['effective_on'],
                $document['html_url'],
                $document['pdf_url']
            ),
            'url' => $document['html_url'],
            'type' => 'publication'
        ];

        $this->send_notifications($notification, $submission->get_sorn_id());
    }

    /**
     * Notify about error
     *
     * @param FederalRegisterSubmission $submission Failed submission
     * @param string $error_message Error message
     */
    public function notify_error(
        FederalRegisterSubmission $submission,
        string $error_message
    ): void {
        $sorn = get_post($submission->get_sorn_id());
        if (!$sorn) {
            return;
        }

        $notification = [
            'title' => sprintf(
                __('Federal Register Error: %s', 'piper-privacy-sorn'),
                $sorn->post_title
            ),
            'message' => sprintf(
                __('An error occurred with your Federal Register submission.

Error Details:
- SORN: %s
- Submission ID: %s
- Status: %s
- Error: %s

Please review the submission in the admin panel.', 'piper-privacy-sorn'),
                $sorn->post_title,
                $submission->get_submission_id(),
                $submission->get_status(),
                $error_message
            ),
            'url' => $this->get_submission_url($submission),
            'type' => 'error'
        ];

        $this->send_notifications($notification, $submission->get_sorn_id());
    }

    /**
     * Send notifications through configured channels
     *
     * @param array $notification Notification data
     * @param int $sorn_id SORN ID
     */
    private function send_notifications(array $notification, int $sorn_id): void {
        $settings = get_option('piper_privacy_sorn_fr_notifications', []);

        // Get users who should be notified
        $users = $this->get_notification_users($notification['type'], $sorn_id);

        // Send email notifications
        if ($settings['email_notifications'] ?? true) {
            $this->send_email_notifications($notification, $users);
        }

        // Send admin notifications
        if ($settings['admin_notifications'] ?? true) {
            $this->send_admin_notification($notification);
        }

        // Send Slack notifications
        if (!empty($settings['slack_webhook_url'])) {
            $this->send_slack_notification($notification, $settings['slack_webhook_url']);
        }

        // Send Teams notifications
        if (!empty($settings['teams_webhook_url'])) {
            $this->send_teams_notification($notification, $settings['teams_webhook_url']);
        }
    }

    /**
     * Get users who should receive notifications
     *
     * @param string $type Notification type
     * @param int $sorn_id SORN ID
     * @return array User objects
     */
    private function get_notification_users(string $type, int $sorn_id): array {
        // Get users with manage_options capability
        $users = get_users(['role__in' => ['administrator', 'editor']]);

        return array_filter($users, function($user) use ($type, $sorn_id) {
            $preferences = get_user_meta($user->ID, 'piper_privacy_sorn_fr_preferences', true) ?: [];
            
            // Check if user wants this type of notification
            switch ($type) {
                case 'submission':
                    return $preferences['notify_submissions'] ?? true;
                case 'status':
                    return $preferences['notify_status'] ?? true;
                case 'publication':
                    return $preferences['notify_publications'] ?? true;
                case 'error':
                    return $preferences['notify_errors'] ?? true;
                default:
                    return true;
            }
        });
    }

    /**
     * Send email notifications
     *
     * @param array $notification Notification data
     * @param array $users Users to notify
     */
    private function send_email_notifications(array $notification, array $users): void {
        $subject = sprintf(
            '[%s] %s',
            get_bloginfo('name'),
            $notification['title']
        );

        $message = $notification['message'] . "\n\n";
        if (!empty($notification['url'])) {
            $message .= sprintf(
                __('View Details: %s', 'piper-privacy-sorn'),
                $notification['url']
            );
        }

        foreach ($users as $user) {
            wp_mail($user->user_email, $subject, $message);
        }
    }

    /**
     * Send admin notification
     *
     * @param array $notification Notification data
     */
    private function send_admin_notification(array $notification): void {
        $notice_type = $notification['type'] === 'error' ? 'error' : 'info';

        add_action('admin_notices', function() use ($notification, $notice_type) {
            ?>
            <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible">
                <p>
                    <strong><?php echo esc_html($notification['title']); ?></strong><br>
                    <?php echo esc_html($notification['message']); ?>
                </p>
                <?php if (!empty($notification['url'])): ?>
                    <p>
                        <a href="<?php echo esc_url($notification['url']); ?>" class="button">
                            <?php _e('View Details', 'piper-privacy-sorn'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        });
    }

    /**
     * Send Slack notification
     *
     * @param array $notification Notification data
     * @param string $webhook_url Slack webhook URL
     */
    private function send_slack_notification(array $notification, string $webhook_url): void {
        $color = match ($notification['type']) {
            'error' => '#ff0000',
            'publication' => '#36a64f',
            default => '#0066cc'
        };

        $payload = [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $notification['title'],
                    'text' => $notification['message'],
                    'title_link' => $notification['url'] ?? '',
                    'footer' => get_bloginfo('name'),
                    'ts' => time()
                ]
            ]
        ];

        wp_remote_post($webhook_url, [
            'body' => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json']
        ]);
    }

    /**
     * Send Teams notification
     *
     * @param array $notification Notification data
     * @param string $webhook_url Teams webhook URL
     */
    private function send_teams_notification(array $notification, string $webhook_url): void {
        $theme_color = match ($notification['type']) {
            'error' => '#ff0000',
            'publication' => '#36a64f',
            default => '#0066cc'
        };

        $payload = [
            'type' => 'MessageCard',
            'context' => 'http://schema.org/extensions',
            'themeColor' => $theme_color,
            'title' => $notification['title'],
            'text' => $notification['message'],
            'potentialAction' => []
        ];

        if (!empty($notification['url'])) {
            $payload['potentialAction'][] = [
                '@type' => 'OpenUri',
                'name' => __('View Details', 'piper-privacy-sorn'),
                'targets' => [['os' => 'default', 'uri' => $notification['url']]]
            ];
        }

        wp_remote_post($webhook_url, [
            'body' => wp_json_encode($payload),
            'headers' => ['Content-Type' => 'application/json']
        ]);
    }

    /**
     * Get submission URL
     *
     * @param FederalRegisterSubmission $submission Submission
     * @return string URL
     */
    private function get_submission_url(FederalRegisterSubmission $submission): string {
        return add_query_arg([
            'page' => 'piper-privacy-sorn-federal-register',
            'submission' => $submission->get_id()
        ], admin_url('admin.php'));
    }
}
