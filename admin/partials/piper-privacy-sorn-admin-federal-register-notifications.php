<?php
/**
 * Federal Register notification settings
 */

// Verify user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'piper-privacy-sorn'));
}

$notification_service = new \PiperPrivacySorn\Services\FederalRegisterNotificationService();
$settings = $notification_service->get_notification_settings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('fr_notification_settings')) {
    $settings['enabled'] = isset($_POST['notifications_enabled']);
    $settings['events'] = array_map('sanitize_text_field', $_POST['notification_events'] ?? []);
    $settings['recipients']['admin'] = isset($_POST['notify_admin']);
    $settings['recipients']['author'] = isset($_POST['notify_author']);
    
    // Handle custom recipients
    $custom_recipients = array_map('trim', explode("\n", $_POST['custom_recipients'] ?? ''));
    $settings['recipients']['custom'] = array_filter($custom_recipients, function($email) {
        return !empty($email) && is_email($email);
    });

    // Handle templates
    foreach ($_POST['templates'] ?? [] as $event_type => $template) {
        if (isset($settings['templates'][$event_type])) {
            $settings['templates'][$event_type]['subject'] = sanitize_text_field($template['subject']);
            $settings['templates'][$event_type]['message'] = sanitize_textarea_field($template['message']);
        }
    }

    if ($notification_service->update_notification_settings($settings)) {
        add_settings_error(
            'fr_notifications',
            'settings_updated',
            __('Notification settings saved.', 'piper-privacy-sorn'),
            'updated'
        );
    }
}

// Get event types
$event_types = [
    'submitted' => __('Submission Received', 'piper-privacy-sorn'),
    'in_review' => __('Under Review', 'piper-privacy-sorn'),
    'approved' => __('Approved', 'piper-privacy-sorn'),
    'published' => __('Published', 'piper-privacy-sorn'),
    'rejected' => __('Rejected', 'piper-privacy-sorn'),
    'error' => __('Error Occurred', 'piper-privacy-sorn')
];
?>

<div class="wrap">
    <h1><?php echo esc_html__('Federal Register Notification Settings', 'piper-privacy-sorn'); ?></h1>

    <?php settings_errors('fr_notifications'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('fr_notification_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Notifications', 'piper-privacy-sorn'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="notifications_enabled" value="1"
                            <?php checked($settings['enabled']); ?>>
                        <?php _e('Send email notifications for Federal Register submissions', 'piper-privacy-sorn'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Notification Events', 'piper-privacy-sorn'); ?></th>
                <td>
                    <?php foreach ($event_types as $type => $label): ?>
                        <label>
                            <input type="checkbox" name="notification_events[]" value="<?php echo esc_attr($type); ?>"
                                <?php checked(in_array($type, $settings['events'])); ?>>
                            <?php echo esc_html($label); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php _e('Select which events should trigger notifications.', 'piper-privacy-sorn'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Default Recipients', 'piper-privacy-sorn'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="notify_admin" value="1"
                            <?php checked($settings['recipients']['admin']); ?>>
                        <?php _e('Site Administrator', 'piper-privacy-sorn'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="notify_author" value="1"
                            <?php checked($settings['recipients']['author']); ?>>
                        <?php _e('SORN Author', 'piper-privacy-sorn'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Additional Recipients', 'piper-privacy-sorn'); ?></th>
                <td>
                    <textarea name="custom_recipients" rows="5" class="large-text code"><?php
                        echo esc_textarea(implode("\n", $settings['recipients']['custom']));
                    ?></textarea>
                    <p class="description">
                        <?php _e('Enter one email address per line.', 'piper-privacy-sorn'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Email Templates', 'piper-privacy-sorn'); ?></h2>
        <p class="description">
            <?php _e('Customize email templates for different notification types. Available variables: {site_name}, {sorn_title}, {submission_id}, {document_number}, {status}, {submitted_date}, {published_date}, {event_date}, {event_message}, {document_url}, {submission_url}', 'piper-privacy-sorn'); ?>
        </p>

        <div class="fr-notification-templates">
            <?php foreach ($event_types as $type => $label): ?>
                <?php $template = $settings['templates'][$type] ?? []; ?>
                <div class="fr-template-card">
                    <h3><?php echo esc_html($label); ?></h3>
                    
                    <div class="fr-template-field">
                        <label>
                            <?php _e('Subject', 'piper-privacy-sorn'); ?>
                            <input type="text" name="templates[<?php echo esc_attr($type); ?>][subject]"
                                value="<?php echo esc_attr($template['subject'] ?? ''); ?>"
                                class="large-text">
                        </label>
                    </div>

                    <div class="fr-template-field">
                        <label>
                            <?php _e('Message', 'piper-privacy-sorn'); ?>
                            <textarea name="templates[<?php echo esc_attr($type); ?>][message]"
                                rows="5" class="large-text"><?php
                                echo esc_textarea($template['message'] ?? '');
                            ?></textarea>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php submit_button(__('Save Settings', 'piper-privacy-sorn')); ?>
    </form>

    <div class="fr-notification-test">
        <h2><?php _e('Test Notifications', 'piper-privacy-sorn'); ?></h2>
        <p>
            <?php _e('Send a test notification to verify your settings.', 'piper-privacy-sorn'); ?>
        </p>
        <select id="test-notification-type">
            <?php foreach ($event_types as $type => $label): ?>
                <option value="<?php echo esc_attr($type); ?>">
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="button" id="send-test-notification">
            <?php _e('Send Test', 'piper-privacy-sorn'); ?>
        </button>
        <span class="spinner"></span>
    </div>
</div>
