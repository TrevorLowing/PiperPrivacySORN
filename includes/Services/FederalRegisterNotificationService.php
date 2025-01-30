<?php
namespace PiperPrivacySorn\Services;

/**
 * Handles email notifications for Federal Register submissions
 */
class FederalRegisterNotificationService {
    /**
     * Default notification templates
     */
    private const DEFAULT_TEMPLATES = [
        'submitted' => [
            'subject' => '[{site_name}] SORN Submission Received - {sorn_title}',
            'message' => "A new SORN submission has been received and is being processed.\n\n" .
                        "SORN: {sorn_title}\n" .
                        "Submission ID: {submission_id}\n" .
                        "Status: {status}\n" .
                        "Submitted: {submitted_date}\n\n" .
                        "You will receive updates as the submission progresses.\n\n" .
                        "View Details: {submission_url}"
        ],
        'in_review' => [
            'subject' => '[{site_name}] SORN Under Review - {sorn_title}',
            'message' => "Your SORN submission is now under review by the Federal Register.\n\n" .
                        "SORN: {sorn_title}\n" .
                        "Submission ID: {submission_id}\n" .
                        "Status: {status}\n" .
                        "Review Started: {event_date}\n\n" .
                        "You will be notified when the review is complete.\n\n" .
                        "View Details: {submission_url}"
        ],
        'approved' => [
            'subject' => '[{site_name}] SORN Approved - {sorn_title}',
            'message' => "Your SORN submission has been approved by the Federal Register.\n\n" .
                        "SORN: {sorn_title}\n" .
                        "Submission ID: {submission_id}\n" .
                        "Document Number: {document_number}\n" .
                        "Status: {status}\n" .
                        "Approval Date: {event_date}\n\n" .
                        "The SORN will be scheduled for publication soon.\n\n" .
                        "View Details: {submission_url}"
        ],
        'published' => [
            'subject' => '[{site_name}] SORN Published - {sorn_title}',
            'message' => "Your SORN has been published in the Federal Register.\n\n" .
                        "SORN: {sorn_title}\n" .
                        "Submission ID: {submission_id}\n" .
                        "Document Number: {document_number}\n" .
                        "Status: {status}\n" .
                        "Publication Date: {published_date}\n\n" .
                        "View in Federal Register: {document_url}\n" .
                        "View Details: {submission_url}"
        ],
        'rejected' => [
            'subject' => '[{site_name}] SORN Submission Rejected - {sorn_title}',
            'message' => "Your SORN submission has been rejected by the Federal Register.\n\n" .
                        "SORN: {sorn_title}\n" .
                        "Submission ID: {submission_id}\n" .
                        "Status: {status}\n" .
                        "Rejection Date: {event_date}\n" .
                        "Reason: {event_message}\n\n" .
                        "Please review the rejection reason and make necessary corrections.\n\n" .
                        "View Details: {submission_url}"
        ],
        'error' => [
            'subject' => '[{site_name}] SORN Submission Error - {sorn_title}',
            'message' => "An error occurred with your SORN submission.\n\n" .
                        "SORN: {sorn_title}\n" .
                        "Submission ID: {submission_id}\n" .
                        "Status: {status}\n" .
                        "Error Date: {event_date}\n" .
                        "Error: {event_message}\n\n" .
                        "The system will attempt to retry the submission automatically.\n\n" .
                        "View Details: {submission_url}"
        ]
    ];

    /**
     * Send notification for a submission event
     *
     * @param \PiperPrivacySorn\Models\FederalRegisterSubmission $submission
     * @param array $event Event data
     * @return bool Whether the notification was sent successfully
     */
    public function send_notification($submission, array $event): bool {
        // Get notification settings
        $settings = $this->get_notification_settings();
        if (!$settings['enabled']) {
            return false;
        }

        // Check if this event type should trigger a notification
        if (!in_array($event['event_type'], $settings['events'])) {
            return false;
        }

        // Get recipients
        $recipients = $this->get_notification_recipients($submission, $event);
        if (empty($recipients)) {
            return false;
        }

        // Get template
        $template = $this->get_notification_template($event['event_type']);
        if (empty($template)) {
            return false;
        }

        // Prepare template variables
        $vars = $this->prepare_template_variables($submission, $event);

        // Replace variables in template
        $subject = $this->replace_variables($template['subject'], $vars);
        $message = $this->replace_variables($template['message'], $vars);

        // Send email
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
        ];

        return wp_mail($recipients, $subject, $message, $headers);
    }

    /**
     * Get notification settings
     *
     * @return array
     */
    public function get_notification_settings(): array {
        $defaults = [
            'enabled' => true,
            'events' => ['submitted', 'in_review', 'approved', 'published', 'rejected', 'error'],
            'recipients' => [
                'admin' => true,
                'author' => true,
                'custom' => []
            ],
            'templates' => self::DEFAULT_TEMPLATES
        ];

        $settings = get_option('piper_privacy_sorn_fr_notifications', []);
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Update notification settings
     *
     * @param array $settings
     * @return bool
     */
    public function update_notification_settings(array $settings): bool {
        return update_option('piper_privacy_sorn_fr_notifications', $settings);
    }

    /**
     * Get notification recipients for a submission
     *
     * @param \PiperPrivacySorn\Models\FederalRegisterSubmission $submission
     * @param array $event
     * @return array
     */
    private function get_notification_recipients($submission, array $event): array {
        $settings = $this->get_notification_settings();
        $recipients = [];

        // Add admin if enabled
        if ($settings['recipients']['admin']) {
            $recipients[] = get_bloginfo('admin_email');
        }

        // Add post author if enabled
        if ($settings['recipients']['author']) {
            $sorn = get_post($submission->get_sorn_id());
            if ($sorn) {
                $author = get_user_by('id', $sorn->post_author);
                if ($author) {
                    $recipients[] = $author->user_email;
                }
            }
        }

        // Add custom recipients
        if (!empty($settings['recipients']['custom'])) {
            $recipients = array_merge($recipients, $settings['recipients']['custom']);
        }

        // Filter recipients
        $recipients = array_unique(array_filter($recipients));

        return apply_filters(
            'piper_privacy_sorn_fr_notification_recipients',
            $recipients,
            $submission,
            $event
        );
    }

    /**
     * Get notification template for an event type
     *
     * @param string $event_type
     * @return array|null
     */
    private function get_notification_template(string $event_type): ?array {
        $settings = $this->get_notification_settings();
        return $settings['templates'][$event_type] ?? self::DEFAULT_TEMPLATES[$event_type] ?? null;
    }

    /**
     * Prepare template variables for replacement
     *
     * @param \PiperPrivacySorn\Models\FederalRegisterSubmission $submission
     * @param array $event
     * @return array
     */
    private function prepare_template_variables($submission, array $event): array {
        $sorn = get_post($submission->get_sorn_id());
        $event_data = json_decode($event['event_data'], true);

        return [
            'site_name' => get_bloginfo('name'),
            'sorn_title' => $sorn ? $sorn->post_title : 'Unknown SORN',
            'submission_id' => $submission->get_submission_id(),
            'document_number' => $submission->get_document_number(),
            'status' => ucfirst($submission->get_status()),
            'submitted_date' => wp_date(
                get_option('date_format'),
                strtotime($submission->get_submitted_at())
            ),
            'published_date' => $submission->get_published_at() ? wp_date(
                get_option('date_format'),
                strtotime($submission->get_published_at())
            ) : '',
            'event_date' => wp_date(
                get_option('date_format'),
                strtotime($event['created_at'])
            ),
            'event_message' => $event_data['message'] ?? '',
            'document_url' => $submission->get_document_number() ?
                'https://www.federalregister.gov/d/' . $submission->get_document_number() : '',
            'submission_url' => admin_url(
                'admin.php?page=piper-privacy-sorn-federal-register&submission=' . $submission->get_id()
            )
        ];
    }

    /**
     * Replace variables in a template string
     *
     * @param string $template
     * @param array $vars
     * @return string
     */
    private function replace_variables(string $template, array $vars): string {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
}
