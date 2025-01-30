<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use PiperPrivacySorn\Models\FederalRegisterSubmission;

/**
 * Handles Federal Register submission workflow
 */
class FederalRegisterSubmissionService {
    /**
     * @var FederalRegisterApi
     */
    private FederalRegisterApi $api;

    /**
     * @var array Submission statuses
     */
    public const STATUSES = [
        'DRAFT' => 'draft',
        'SUBMITTED' => 'submitted',
        'IN_REVIEW' => 'in_review',
        'CHANGES_REQUESTED' => 'changes_requested',
        'APPROVED' => 'approved',
        'SCHEDULED' => 'scheduled',
        'PUBLISHED' => 'published',
        'REJECTED' => 'rejected',
        'ERROR' => 'error'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new FederalRegisterApi();

        // Register hooks
        add_action('init', [$this, 'register_schedules']);
        add_action('piper_privacy_sorn_check_submission_status', [$this, 'check_submission_status']);
        add_action('piper_privacy_sorn_fr_submission_status_changed', [$this, 'handle_status_change'], 10, 3);
    }

    /**
     * Register cron schedules
     */
    public function register_schedules(): void {
        if (!wp_next_scheduled('piper_privacy_sorn_check_submission_status')) {
            wp_schedule_event(time(), 'hourly', 'piper_privacy_sorn_check_submission_status');
        }
    }

    /**
     * Submit SORN to Federal Register
     *
     * @param int $sorn_id SORN ID
     * @return FederalRegisterSubmission
     * @throws \Exception If submission fails
     */
    public function submit_sorn(int $sorn_id): FederalRegisterSubmission {
        // Get SORN data
        $sorn = get_post($sorn_id);
        if (!$sorn) {
            throw new \Exception(__('SORN not found', 'piper-privacy-sorn'));
        }

        // Format SORN for Federal Register
        $fr_data = $this->api->format_sorn_for_submission([
            'title' => $sorn->post_title,
            'content' => $sorn->post_content,
            // Add other SORN fields
        ]);

        try {
            // Submit to Federal Register
            $response = $this->api->submit_sorn($fr_data);

            // Create submission record
            $submission = FederalRegisterSubmission::create([
                'sorn_id' => $sorn_id,
                'submission_id' => $response['id'],
                'status' => self::STATUSES['SUBMITTED']
            ]);

            // Log submission event
            $submission->add_event('submitted', [
                'user_id' => get_current_user_id(),
                'response' => $response
            ]);

            // Send notification
            $this->send_submission_notification($submission);

            return $submission;
        } catch (\Exception $e) {
            // Log error
            error_log(sprintf(
                'Federal Register submission failed for SORN %d: %s',
                $sorn_id,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Check status of pending submissions
     */
    public function check_submission_status(): void {
        global $wpdb;

        // Get pending submissions
        $pending_statuses = [
            self::STATUSES['SUBMITTED'],
            self::STATUSES['IN_REVIEW'],
            self::STATUSES['APPROVED'],
            self::STATUSES['SCHEDULED']
        ];

        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions 
                WHERE status IN (" . implode(',', array_fill(0, count($pending_statuses), '%s')) . ")",
                $pending_statuses
            ),
            ARRAY_A
        );

        if (!$submissions) {
            return;
        }

        foreach ($submissions as $data) {
            $submission = FederalRegisterSubmission::from_array($data);
            $this->update_submission_status($submission);
        }
    }

    /**
     * Update submission status
     *
     * @param FederalRegisterSubmission $submission Submission to update
     */
    private function update_submission_status(FederalRegisterSubmission $submission): void {
        try {
            // Get status from Federal Register
            $status = $this->api->get_submission_status($submission->get_submission_id());

            // Map Federal Register status to our status
            $new_status = $this->map_fr_status($status['status']);

            // If status has changed
            if ($new_status !== $submission->get_status()) {
                $old_status = $submission->get_status();

                // Update submission
                $submission->update_status(
                    $new_status,
                    $status['document_number'] ?? null,
                    $status['publication_date'] ?? null
                );

                // Log event
                $submission->add_event('status_changed', [
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                    'fr_status' => $status
                ]);

                // Trigger status change action
                do_action(
                    'piper_privacy_sorn_fr_submission_status_changed',
                    $submission,
                    $old_status,
                    $new_status
                );
            }
        } catch (\Exception $e) {
            // Log error
            error_log(sprintf(
                'Failed to update Federal Register submission status for ID %s: %s',
                $submission->get_submission_id(),
                $e->getMessage()
            ));

            // Mark submission as error
            $submission->update_status(self::STATUSES['ERROR']);
            $submission->add_event('error', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle submission status change
     *
     * @param FederalRegisterSubmission $submission Submission that changed
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    public function handle_status_change(
        FederalRegisterSubmission $submission,
        string $old_status,
        string $new_status
    ): void {
        // Send notification
        $this->send_status_notification($submission, $old_status, $new_status);

        // If published, update SORN
        if ($new_status === self::STATUSES['PUBLISHED']) {
            $this->handle_publication($submission);
        }

        // If rejected or error, notify admin
        if (in_array($new_status, [self::STATUSES['REJECTED'], self::STATUSES['ERROR']])) {
            $this->notify_admin($submission);
        }
    }

    /**
     * Handle SORN publication
     *
     * @param FederalRegisterSubmission $submission Published submission
     */
    private function handle_publication(FederalRegisterSubmission $submission): void {
        // Get published document
        $document = $this->api->get_document($submission->get_document_number());

        // Update SORN with Federal Register data
        wp_update_post([
            'ID' => $submission->get_sorn_id(),
            'meta_input' => [
                'federal_register_url' => $document['html_url'],
                'federal_register_pdf_url' => $document['pdf_url'],
                'publication_date' => $document['publication_date'],
                'effective_date' => $document['effective_on']
            ]
        ]);

        // Send publication notification
        $this->send_publication_notification($submission, $document);
    }

    /**
     * Map Federal Register status to our status
     *
     * @param string $fr_status Federal Register status
     * @return string Our status
     */
    private function map_fr_status(string $fr_status): string {
        $map = [
            'pending' => self::STATUSES['SUBMITTED'],
            'in_review' => self::STATUSES['IN_REVIEW'],
            'approved' => self::STATUSES['APPROVED'],
            'scheduled' => self::STATUSES['SCHEDULED'],
            'published' => self::STATUSES['PUBLISHED'],
            'rejected' => self::STATUSES['REJECTED']
        ];

        return $map[$fr_status] ?? self::STATUSES['ERROR'];
    }

    /**
     * Send submission notification
     *
     * @param FederalRegisterSubmission $submission New submission
     */
    private function send_submission_notification(FederalRegisterSubmission $submission): void {
        $sorn = get_post($submission->get_sorn_id());
        $admin_email = get_option('admin_email');

        $subject = sprintf(
            __('[%s] SORN Submitted to Federal Register', 'piper-privacy-sorn'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __('SORN "%s" has been submitted to the Federal Register.

Submission Details:
- Submission ID: %s
- Status: %s
- Submitted: %s

You will receive notifications when the status changes.

View Submission: %s', 'piper-privacy-sorn'),
            $sorn->post_title,
            $submission->get_submission_id(),
            $submission->get_status(),
            $submission->get_submitted_at(),
            admin_url('admin.php?page=piper-privacy-sorn-federal-register&submission=' . $submission->get_id())
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Send status change notification
     *
     * @param FederalRegisterSubmission $submission Submission that changed
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    private function send_status_notification(
        FederalRegisterSubmission $submission,
        string $old_status,
        string $new_status
    ): void {
        $sorn = get_post($submission->get_sorn_id());
        $admin_email = get_option('admin_email');

        $subject = sprintf(
            __('[%s] SORN Federal Register Status Changed', 'piper-privacy-sorn'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __('The Federal Register status for SORN "%s" has changed.

Status Change:
- From: %s
- To: %s

Submission Details:
- Submission ID: %s
- Document Number: %s
- Submitted: %s

View Submission: %s', 'piper-privacy-sorn'),
            $sorn->post_title,
            $old_status,
            $new_status,
            $submission->get_submission_id(),
            $submission->get_document_number() ?? 'Not assigned',
            $submission->get_submitted_at(),
            admin_url('admin.php?page=piper-privacy-sorn-federal-register&submission=' . $submission->get_id())
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Send publication notification
     *
     * @param FederalRegisterSubmission $submission Published submission
     * @param array $document Federal Register document data
     */
    private function send_publication_notification(
        FederalRegisterSubmission $submission,
        array $document
    ): void {
        $sorn = get_post($submission->get_sorn_id());
        $admin_email = get_option('admin_email');

        $subject = sprintf(
            __('[%s] SORN Published in Federal Register', 'piper-privacy-sorn'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __('SORN "%s" has been published in the Federal Register.

Publication Details:
- Document Number: %s
- Publication Date: %s
- Effective Date: %s

View Online:
- HTML: %s
- PDF: %s

View Submission: %s', 'piper-privacy-sorn'),
            $sorn->post_title,
            $document['document_number'],
            $document['publication_date'],
            $document['effective_on'],
            $document['html_url'],
            $document['pdf_url'],
            admin_url('admin.php?page=piper-privacy-sorn-federal-register&submission=' . $submission->get_id())
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify admin of submission issues
     *
     * @param FederalRegisterSubmission $submission Problematic submission
     */
    private function notify_admin(FederalRegisterSubmission $submission): void {
        $sorn = get_post($submission->get_sorn_id());
        $admin_email = get_option('admin_email');

        $subject = sprintf(
            __('[%s] SORN Federal Register Submission Issue', 'piper-privacy-sorn'),
            get_bloginfo('name')
        );

        $events = $submission->get_events();
        $latest_event = end($events);
        $event_data = json_decode($latest_event['event_data'], true);

        $message = sprintf(
            __('There was an issue with the Federal Register submission for SORN "%s".

Submission Details:
- Submission ID: %s
- Status: %s
- Error: %s

Please review the submission and take appropriate action.

View Submission: %s', 'piper-privacy-sorn'),
            $sorn->post_title,
            $submission->get_submission_id(),
            $submission->get_status(),
            $event_data['message'] ?? 'Unknown error',
            admin_url('admin.php?page=piper-privacy-sorn-federal-register&submission=' . $submission->get_id())
        );

        wp_mail($admin_email, $subject, $message);
    }
}
