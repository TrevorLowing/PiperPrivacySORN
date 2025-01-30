<?php

declare(strict_types=1);

namespace PiperPrivacySorn\Services;

use PiperPrivacySorn\Models\Sorn;
use PiperPrivacySorn\Models\SornVersion;

/**
 * Manages SORN creation, updates, and tracking.
 */
class SornManager {
    /**
     * @var GptTrainerApi
     */
    private GptTrainerApi $api;

    /**
     * @var string
     */
    private string $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'piper_privacy_sorns';
        $this->api = new GptTrainerApi();
    }

    /**
     * Create tables on plugin activation.
     */
    public function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // SORN table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            system_name varchar(255) NOT NULL,
            identifier varchar(100) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'draft',
            agency varchar(255) NOT NULL,
            categories text,
            purpose text,
            authority text,
            routine_uses text,
            retention text,
            safeguards text,
            access_procedures text,
            contesting_procedures text,
            notification_procedures text,
            exemptions text,
            history text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            version int unsigned NOT NULL DEFAULT 1,
            is_current tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY idx_identifier (identifier),
            KEY idx_status (status),
            KEY idx_agency (agency),
            KEY idx_created_by (created_by),
            KEY idx_version (version)
        ) $charset_collate;";

        // SORN versions table
        $sql .= "CREATE TABLE IF NOT EXISTS {$this->table_name}_versions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sorn_id bigint(20) unsigned NOT NULL,
            version int unsigned NOT NULL,
            changes text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_sorn_id (sorn_id),
            KEY idx_version (version),
            CONSTRAINT fk_sorn_version FOREIGN KEY (sorn_id) REFERENCES {$this->table_name} (id) ON DELETE CASCADE
        ) $charset_collate;";

        // SORN comments table
        $sql .= "CREATE TABLE IF NOT EXISTS {$this->table_name}_comments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sorn_id bigint(20) unsigned NOT NULL,
            comment text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_sorn_id (sorn_id),
            CONSTRAINT fk_sorn_comment FOREIGN KEY (sorn_id) REFERENCES {$this->table_name} (id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create a new SORN.
     *
     * @param array $data SORN data
     * @return int|false The SORN ID if successful, false otherwise
     */
    public function create_sorn(array $data) {
        global $wpdb;

        // Validate required fields
        $required_fields = ['title', 'system_name', 'identifier', 'agency'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException(
                    sprintf(__('Missing required field: %s', 'piper-privacy-sorn'), $field)
                );
            }
        }

        // Sanitize and prepare data
        $sorn_data = [
            'title' => sanitize_text_field($data['title']),
            'system_name' => sanitize_text_field($data['system_name']),
            'identifier' => sanitize_text_field($data['identifier']),
            'agency' => sanitize_text_field($data['agency']),
            'status' => 'draft',
            'created_by' => get_current_user_id(),
            'updated_by' => get_current_user_id(),
        ];

        // Optional fields
        $optional_fields = [
            'categories', 'purpose', 'authority', 'routine_uses',
            'retention', 'safeguards', 'access_procedures',
            'contesting_procedures', 'notification_procedures',
            'exemptions', 'history'
        ];

        foreach ($optional_fields as $field) {
            if (!empty($data[$field])) {
                $sorn_data[$field] = wp_kses_post($data[$field]);
            }
        }

        // Insert SORN
        $result = $wpdb->insert($this->table_name, $sorn_data);
        if ($result === false) {
            throw new \RuntimeException(
                __('Failed to create SORN', 'piper-privacy-sorn')
            );
        }

        $sorn_id = $wpdb->insert_id;

        // Create initial version
        $this->create_version($sorn_id, 1, __('Initial version', 'piper-privacy-sorn'));

        // Use GPT to analyze SORN
        try {
            $this->analyze_sorn($sorn_id);
        } catch (\Exception $e) {
            // Log error but don't fail SORN creation
            error_log(sprintf(
                '[SORN Manager] Failed to analyze SORN %d: %s',
                $sorn_id,
                $e->getMessage()
            ));
        }

        do_action('piper_privacy_sorn_created', $sorn_id, $sorn_data);

        return $sorn_id;
    }

    /**
     * Update an existing SORN.
     *
     * @param int   $sorn_id SORN ID
     * @param array $data    Updated SORN data
     * @return bool True if successful, false otherwise
     */
    public function update_sorn(int $sorn_id, array $data): bool {
        global $wpdb;

        // Get current SORN
        $current_sorn = $this->get_sorn($sorn_id);
        if (!$current_sorn) {
            throw new \InvalidArgumentException(
                __('SORN not found', 'piper-privacy-sorn')
            );
        }

        // Prepare update data
        $update_data = [
            'updated_by' => get_current_user_id(),
            'version' => $current_sorn->version + 1
        ];

        // Update fields
        $updatable_fields = [
            'title', 'system_name', 'identifier', 'agency', 'status',
            'categories', 'purpose', 'authority', 'routine_uses',
            'retention', 'safeguards', 'access_procedures',
            'contesting_procedures', 'notification_procedures',
            'exemptions', 'history'
        ];

        foreach ($updatable_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $field === 'status' ? 
                    sanitize_text_field($data[$field]) :
                    wp_kses_post($data[$field]);
            }
        }

        // Update SORN
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $sorn_id]
        );

        if ($result === false) {
            throw new \RuntimeException(
                __('Failed to update SORN', 'piper-privacy-sorn')
            );
        }

        // Create new version
        $this->create_version(
            $sorn_id,
            $update_data['version'],
            $data['change_description'] ?? __('Updated SORN', 'piper-privacy-sorn')
        );

        do_action('piper_privacy_sorn_updated', $sorn_id, $update_data);

        return true;
    }

    /**
     * Get a SORN by ID.
     *
     * @param int $sorn_id SORN ID
     * @return object|null SORN object if found, null otherwise
     */
    public function get_sorn(int $sorn_id): ?object {
        global $wpdb;

        $sorn = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $sorn_id
        ));

        if ($sorn) {
            $sorn->versions = $this->get_versions($sorn_id);
            $sorn->comments = $this->get_comments($sorn_id);
        }

        return $sorn;
    }

    /**
     * Get SORN versions.
     *
     * @param int $sorn_id SORN ID
     * @return array Array of version objects
     */
    public function get_versions(int $sorn_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}_versions WHERE sorn_id = %d ORDER BY version DESC",
            $sorn_id
        ));
    }

    /**
     * Create a new SORN version.
     *
     * @param int    $sorn_id      SORN ID
     * @param int    $version      Version number
     * @param string $changes      Description of changes
     * @return bool True if successful, false otherwise
     */
    private function create_version(int $sorn_id, int $version, string $changes): bool {
        global $wpdb;

        return $wpdb->insert(
            "{$this->table_name}_versions",
            [
                'sorn_id' => $sorn_id,
                'version' => $version,
                'changes' => wp_kses_post($changes),
                'created_by' => get_current_user_id()
            ]
        ) !== false;
    }

    /**
     * Add a comment to a SORN.
     *
     * @param int    $sorn_id SORN ID
     * @param string $comment Comment text
     * @return bool True if successful, false otherwise
     */
    public function add_comment(int $sorn_id, string $comment): bool {
        global $wpdb;

        return $wpdb->insert(
            "{$this->table_name}_comments",
            [
                'sorn_id' => $sorn_id,
                'comment' => wp_kses_post($comment),
                'created_by' => get_current_user_id()
            ]
        ) !== false;
    }

    /**
     * Get SORN comments.
     *
     * @param int $sorn_id SORN ID
     * @return array Array of comment objects
     */
    public function get_comments(int $sorn_id): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}_comments WHERE sorn_id = %d ORDER BY created_at DESC",
            $sorn_id
        ));
    }

    /**
     * Search SORNs based on criteria.
     *
     * @param array $criteria Search criteria
     * @param int   $page     Page number
     * @param int   $per_page Items per page
     * @return array Array containing results and total count
     */
    public function search_sorns(array $criteria, int $page = 1, int $per_page = 10): array {
        global $wpdb;

        $where = [];
        $values = [];

        // Build search conditions
        if (!empty($criteria['search'])) {
            $search = '%' . $wpdb->esc_like($criteria['search']) . '%';
            $where[] = "(title LIKE %s OR system_name LIKE %s OR identifier LIKE %s)";
            $values = array_merge($values, [$search, $search, $search]);
        }

        if (!empty($criteria['agency'])) {
            $where[] = "agency = %s";
            $values[] = $criteria['agency'];
        }

        if (!empty($criteria['status'])) {
            $where[] = "status = %s";
            $values[] = $criteria['status'];
        }

        // Build query
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $per_page;

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} $where_clause";
        $total = $wpdb->get_var($wpdb->prepare($count_query, $values));

        // Get results
        $query = "SELECT * FROM {$this->table_name} $where_clause ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($query, $values));

        return [
            'results' => $results,
            'total' => (int)$total,
            'pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Analyze a SORN using GPT.
     *
     * @param int $sorn_id SORN ID
     * @return array Analysis results
     */
    private function analyze_sorn(int $sorn_id): array {
        $sorn = $this->get_sorn($sorn_id);
        if (!$sorn) {
            throw new \InvalidArgumentException('SORN not found');
        }

        // Prepare SORN content for analysis
        $content = wp_strip_all_tags(implode("\n\n", [
            $sorn->title,
            $sorn->system_name,
            $sorn->purpose,
            $sorn->categories,
            $sorn->routine_uses,
            $sorn->safeguards
        ]));

        try {
            // Use GPT to analyze content
            $analysis = $this->api->analyze_text($content);

            // Store analysis results as comments
            if (!empty($analysis['suggestions'])) {
                foreach ($analysis['suggestions'] as $suggestion) {
                    $this->add_comment(
                        $sorn_id,
                        sprintf(
                            __('AI Suggestion: %s', 'piper-privacy-sorn'),
                            $suggestion
                        )
                    );
                }
            }

            return $analysis;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf('Failed to analyze SORN: %s', $e->getMessage())
            );
        }
    }
}
