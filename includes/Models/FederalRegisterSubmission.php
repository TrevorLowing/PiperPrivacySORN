<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Models;

/**
 * Federal Register submission model
 */
class FederalRegisterSubmission {
    /**
     * @var int
     */
    private int $id;

    /**
     * @var int
     */
    private int $sorn_id;

    /**
     * @var string
     */
    private string $submission_id;

    /**
     * @var string|null
     */
    private ?string $document_number;

    /**
     * @var string
     */
    private string $status;

    /**
     * @var string
     */
    private string $submitted_at;

    /**
     * @var string|null
     */
    private ?string $published_at;

    /**
     * @var string
     */
    private string $created_at;

    /**
     * @var string
     */
    private string $updated_at;

    /**
     * Get submission by ID
     *
     * @param int $id Submission ID
     * @return self|null
     */
    public static function find(int $id): ?self {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return self::from_array($row);
    }

    /**
     * Get submission by submission ID
     *
     * @param string $submission_id Federal Register submission ID
     * @return self|null
     */
    public static function find_by_submission_id(string $submission_id): ?self {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions WHERE submission_id = %s",
                $submission_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return self::from_array($row);
    }

    /**
     * Get submissions by SORN ID
     *
     * @param int $sorn_id SORN ID
     * @return array
     */
    public static function find_by_sorn_id(int $sorn_id): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions WHERE sorn_id = %d ORDER BY created_at DESC",
                $sorn_id
            ),
            ARRAY_A
        );

        return array_map([self::class, 'from_array'], $rows ?: []);
    }

    /**
     * Get recent submissions
     *
     * @param int $limit Maximum number of submissions to return
     * @return array
     */
    public static function get_recent(int $limit = 20): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, n.title as sorn_title 
                FROM {$wpdb->prefix}piper_privacy_sorn_fr_submissions s
                LEFT JOIN {$wpdb->prefix}piper_privacy_sorns n ON s.sorn_id = n.id
                ORDER BY s.created_at DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return array_map([self::class, 'from_array'], $rows ?: []);
    }

    /**
     * Create a new submission
     *
     * @param array $data Submission data
     * @return self
     */
    public static function create(array $data): self {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorn_fr_submissions',
            [
                'sorn_id' => $data['sorn_id'],
                'submission_id' => $data['submission_id'],
                'document_number' => $data['document_number'] ?? null,
                'status' => $data['status'] ?? 'submitted',
                'submitted_at' => $data['submitted_at'] ?? current_time('mysql'),
                'published_at' => $data['published_at'] ?? null
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return self::find($wpdb->insert_id);
    }

    /**
     * Update submission status
     *
     * @param string $status New status
     * @param string|null $document_number Document number (if available)
     * @param string|null $published_at Publication date (if available)
     * @return bool
     */
    public function update_status(string $status, ?string $document_number = null, ?string $published_at = null): bool {
        global $wpdb;

        $data = ['status' => $status];
        $format = ['%s'];

        if ($document_number) {
            $data['document_number'] = $document_number;
            $format[] = '%s';
        }

        if ($published_at) {
            $data['published_at'] = $published_at;
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'piper_privacy_sorn_fr_submissions',
            $data,
            ['id' => $this->id],
            $format,
            ['%d']
        );

        if ($result !== false) {
            $this->status = $status;
            if ($document_number) {
                $this->document_number = $document_number;
            }
            if ($published_at) {
                $this->published_at = $published_at;
            }
            return true;
        }

        return false;
    }

    /**
     * Add submission event
     *
     * @param string $event_type Event type
     * @param array $event_data Event data
     * @return bool
     */
    public function add_event(string $event_type, array $event_data = []): bool {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'piper_privacy_sorn_fr_submission_events',
            [
                'submission_id' => $this->submission_id,
                'event_type' => $event_type,
                'event_data' => $event_data ? wp_json_encode($event_data) : null
            ],
            ['%s', '%s', '%s']
        ) !== false;
    }

    /**
     * Get submission events
     *
     * @return array
     */
    public function get_events(): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}piper_privacy_sorn_fr_submission_events 
                WHERE submission_id = %s 
                ORDER BY created_at DESC",
                $this->submission_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Create instance from array
     *
     * @param array $data Submission data
     * @return self
     */
    private static function from_array(array $data): self {
        $instance = new self();
        $instance->id = (int) $data['id'];
        $instance->sorn_id = (int) $data['sorn_id'];
        $instance->submission_id = $data['submission_id'];
        $instance->document_number = $data['document_number'];
        $instance->status = $data['status'];
        $instance->submitted_at = $data['submitted_at'];
        $instance->published_at = $data['published_at'];
        $instance->created_at = $data['created_at'];
        $instance->updated_at = $data['updated_at'];
        return $instance;
    }

    /**
     * Get submission ID
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get SORN ID
     *
     * @return int
     */
    public function get_sorn_id(): int {
        return $this->sorn_id;
    }

    /**
     * Get Federal Register submission ID
     *
     * @return string
     */
    public function get_submission_id(): string {
        return $this->submission_id;
    }

    /**
     * Get document number
     *
     * @return string|null
     */
    public function get_document_number(): ?string {
        return $this->document_number;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * Get submission date
     *
     * @return string
     */
    public function get_submitted_at(): string {
        return $this->submitted_at;
    }

    /**
     * Get publication date
     *
     * @return string|null
     */
    public function get_published_at(): ?string {
        return $this->published_at;
    }

    /**
     * Get creation date
     *
     * @return string
     */
    public function get_created_at(): string {
        return $this->created_at;
    }

    /**
     * Get last update date
     *
     * @return string
     */
    public function get_updated_at(): string {
        return $this->updated_at;
    }
}
