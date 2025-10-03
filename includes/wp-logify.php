<?php
/**
 * WP Logify Core Class
 *
 * Handles database table creation, logging functionality, and data management.
 *
 * @package WP_Logify
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main logging class
 */
class WP_Logify {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    const TABLE_NAME = 'wp_logify';

    /**
     * Cron hook name
     *
     * @var string
     */
    const CRON_HOOK = 'wp_logify_cleanup_logs';

    /**
     * Days to keep logs before cleanup
     *
     * @var int
     */
    const CLEANUP_DAYS = 90;

    /**
     * Initialize the class
     */
    public static function init() {
        // Register cron cleanup action
        add_action(self::CRON_HOOK, [__CLASS__, 'cleanup_old_logs']);
    }

    /**
     * Get the full table name with WordPress prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the logs table in the database
     *
     * @return void
     */
    public static function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            action varchar(255) NOT NULL,
            object_type varchar(100) DEFAULT NULL,
            object_id varchar(255) DEFAULT NULL,
            meta longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Store database version
        update_option('wp_logify_db_version', WP_LOGIFY_VERSION);
    }

    /**
     * Log an action to the database
     *
     * @param string $action The action being logged
     * @param string|null $object_type Optional object type (post, user, order, etc.)
     * @param string|int|null $object_id Optional object ID (can be int or string UID)
     * @param array $meta Optional metadata
     * @return int|false Log entry ID on success, false on failure
     */
    public static function log($action, $object_type = null, $object_id = null, $meta = []) {
        global $wpdb;

        // Get current user ID (0 if not logged in)
        $user_id = get_current_user_id();

        // Prepare data
        $data = [
            'user_id' => $user_id ?: null,
            'action' => sanitize_text_field($action),
            'object_type' => $object_type ? sanitize_text_field($object_type) : null,
            'object_id' => $object_id ? sanitize_text_field($object_id) : null,
            'meta' => !empty($meta) ? wp_json_encode($meta) : null,
            'created_at' => current_time('mysql')
        ];

        // Format data types
        $format = ['%d', '%s', '%s', '%s', '%s', '%s'];

        // Allow filtering before inserting
        $data = apply_filters('wp_logify_before_log', $data, $action, $object_type, $object_id, $meta);

        // Insert into database
        $result = $wpdb->insert(
            self::get_table_name(),
            $data,
            $format
        );

        if ($result === false) {
            return false;
        }

        $log_id = $wpdb->insert_id;

        // Fire action hook after logging
        do_action('wp_logify_after_log', $log_id, $data);

        // Fire specific action hook
        do_action('wp_logify_log', $action, $object_type, $object_id, $meta, $log_id);

        return $log_id;
    }

    /**
     * Get logs from the database
     *
     * @param array $args Query arguments
     * @return array Array of log objects
     */
    public static function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'action' => null,
            'object_type' => null,
            'object_id' => null,
            'date_from' => null,
            'date_to' => null,
            'search' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $table_name = self::get_table_name();
        $where = ['1=1'];
        $where_values = [];

        // Build WHERE clause
        if ($args['user_id']) {
            if (is_array($args['user_id'])) {
                $placeholders = implode(',', array_fill(0, count($args['user_id']), '%d'));
                $where[] = "user_id IN ({$placeholders})";
                foreach ($args['user_id'] as $uid) {
                    $where_values[] = absint($uid);
                }
            } else {
                $where[] = 'user_id = %d';
                $where_values[] = absint($args['user_id']);
            }
        }

        if ($args['action']) {
            if (is_array($args['action'])) {
                $placeholders = implode(',', array_fill(0, count($args['action']), '%s'));
                $where[] = "action IN ({$placeholders})";
                foreach ($args['action'] as $act) {
                    $where_values[] = sanitize_text_field($act);
                }
            } else {
                $where[] = 'action = %s';
                $where_values[] = sanitize_text_field($args['action']);
            }
        }

        if ($args['object_type']) {
            if (is_array($args['object_type'])) {
                $placeholders = implode(',', array_fill(0, count($args['object_type']), '%s'));
                $where[] = "object_type IN ({$placeholders})";
                foreach ($args['object_type'] as $otype) {
                    $where_values[] = sanitize_text_field($otype);
                }
            } else {
                $where[] = 'object_type = %s';
                $where_values[] = sanitize_text_field($args['object_type']);
            }
        }

        if ($args['object_id']) {
            $where[] = 'object_id = %s';
            $where_values[] = sanitize_text_field($args['object_id']);
        }

        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($args['date_from']);
        }

        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($args['date_to']);
        }

        if ($args['search']) {
            $search_term = sanitize_text_field($args['search']);
            $search = '%' . $wpdb->esc_like($search_term) . '%';
            $where[] = '(action LIKE %s OR object_type LIKE %s OR object_id = %s OR meta LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search_term; // Exact match for object_id
            $where_values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        // Build ORDER BY clause
        $allowed_orderby = ['id', 'user_id', 'action', 'object_type', 'object_id', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Build LIMIT clause
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        // Build final query
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

        // Prepare query if we have values
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get total count of logs
     *
     * @param array $args Query arguments (same as get_logs)
     * @return int Total number of logs
     */
    public static function count_logs($args = []) {
        global $wpdb;

        $table_name = self::get_table_name();
        $where = ['1=1'];
        $where_values = [];

        // Build WHERE clause (same logic as get_logs)
        if (!empty($args['user_id'])) {
            if (is_array($args['user_id'])) {
                $placeholders = implode(',', array_fill(0, count($args['user_id']), '%d'));
                $where[] = "user_id IN ({$placeholders})";
                foreach ($args['user_id'] as $uid) {
                    $where_values[] = absint($uid);
                }
            } else {
                $where[] = 'user_id = %d';
                $where_values[] = absint($args['user_id']);
            }
        }

        if (!empty($args['action'])) {
            if (is_array($args['action'])) {
                $placeholders = implode(',', array_fill(0, count($args['action']), '%s'));
                $where[] = "action IN ({$placeholders})";
                foreach ($args['action'] as $act) {
                    $where_values[] = sanitize_text_field($act);
                }
            } else {
                $where[] = 'action = %s';
                $where_values[] = sanitize_text_field($args['action']);
            }
        }

        if (!empty($args['object_type'])) {
            if (is_array($args['object_type'])) {
                $placeholders = implode(',', array_fill(0, count($args['object_type']), '%s'));
                $where[] = "object_type IN ({$placeholders})";
                foreach ($args['object_type'] as $otype) {
                    $where_values[] = sanitize_text_field($otype);
                }
            } else {
                $where[] = 'object_type = %s';
                $where_values[] = sanitize_text_field($args['object_type']);
            }
        }

        if (!empty($args['object_id'])) {
            $where[] = 'object_id = %s';
            $where_values[] = sanitize_text_field($args['object_id']);
        }

        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($args['date_to']);
        }

        if (!empty($args['search'])) {
            $search_term = sanitize_text_field($args['search']);
            $search = '%' . $wpdb->esc_like($search_term) . '%';
            $where[] = '(action LIKE %s OR object_type LIKE %s OR object_id = %s OR meta LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search_term; // Exact match for object_id
            $where_values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";

        // Prepare query if we have values
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Delete a log entry
     *
     * @param int $log_id Log entry ID
     * @return bool True on success, false on failure
     */
    public static function delete_log($log_id) {
        global $wpdb;

        $result = $wpdb->delete(
            self::get_table_name(),
            ['id' => absint($log_id)],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete logs older than specified days
     *
     * @param int $days Number of days to keep
     * @return int|false Number of deleted rows or false on failure
     */
    public static function cleanup_old_logs($days = null) {
        global $wpdb;

        if ($days === null) {
            $days = self::CLEANUP_DAYS;
        }

        $days = absint($days);
        $table_name = self::get_table_name();

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $date_threshold
            )
        );

        // Log the cleanup action
        if ($result !== false && $result > 0) {
            self::log('logs_cleaned_up', null, null, [
                'deleted_count' => $result,
                'older_than_days' => $days
            ]);
        }

        return $result;
    }

    /**
     * Schedule the cron job for automatic cleanup
     *
     * @return void
     */
    public static function schedule_cleanup_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the cron job
     *
     * @return void
     */
    public static function unschedule_cleanup_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Get distinct actions from logs
     *
     * @return array Array of action names
     */
    public static function get_distinct_actions() {
        global $wpdb;

        $table_name = self::get_table_name();

        $results = $wpdb->get_col(
            "SELECT DISTINCT action FROM {$table_name} WHERE action IS NOT NULL ORDER BY action ASC"
        );

        return $results ?: [];
    }

    /**
     * Get distinct object types from logs
     *
     * @return array Array of object type names
     */
    public static function get_distinct_object_types() {
        global $wpdb;

        $table_name = self::get_table_name();

        $results = $wpdb->get_col(
            "SELECT DISTINCT object_type FROM {$table_name} WHERE object_type IS NOT NULL ORDER BY object_type ASC"
        );

        return $results ?: [];
    }
}
