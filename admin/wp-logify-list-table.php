<?php
/**
 * WP Logify List Table
 *
 * Extends WP_List_Table to display logs in the admin interface.
 *
 * @package WP_Logify
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table class for displaying logs
 */
class WP_Logify_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'log',
            'plural'   => 'logs',
            'ajax'     => false
        ]);
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'user'        => __('User', 'wp-logify'),
            'action'      => __('Action', 'wp-logify'),
            'object_type' => __('Object Type', 'wp-logify'),
            'object_id'   => __('Object ID', 'wp-logify'),
            'meta'        => __('Meta', 'wp-logify'),
            'created_at'  => __('Date', 'wp-logify')
        ];
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'user'        => ['user_id', false],
            'action'      => ['action', false],
            'object_type' => ['object_type', false],
            'object_id'   => ['object_id', false],
            'created_at'  => ['created_at', true] // true = already sorted
        ];
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wp-logify')
        ];
    }

    /**
     * Column checkbox
     *
     * @param object $item Log item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="log[]" value="%d" />',
            $item->id
        );
    }

    /**
     * Column user
     *
     * @param object $item Log item
     * @return string
     */
    public function column_user($item) {
        if (!$item->user_id) {
            return '<em>' . __('Guest', 'wp-logify') . '</em>';
        }

        $user = get_userdata($item->user_id);

        if (!$user) {
            return '<em>' . __('Deleted User', 'wp-logify') . '</em>';
        }

        $edit_link = get_edit_user_link($item->user_id);

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($edit_link),
            esc_html($user->display_name)
        );
    }

    /**
     * Column action
     *
     * @param object $item Log item
     * @return string
     */
    public function column_action($item) {
        return '<code>' . esc_html($item->action) . '</code>';
    }

    /**
     * Column object_type
     *
     * @param object $item Log item
     * @return string
     */
    public function column_object_type($item) {
        if (!$item->object_type) {
            return '—';
        }

        return '<code>' . esc_html($item->object_type) . '</code>';
    }

    /**
     * Column object_id
     *
     * @param object $item Log item
     * @return string
     */
    public function column_object_id($item) {
        if (!$item->object_id) {
            return '—';
        }

        return sprintf(
            '<code>%s</code>',
            esc_html($item->object_id)
        );
    }

    /**
     * Column meta
     *
     * @param object $item Log item
     * @return string
     */
    public function column_meta($item) {
        if (empty($item->meta)) {
            return '—';
        }

        $meta = json_decode($item->meta, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return '<em>' . __('Invalid JSON', 'wp-logify') . '</em>';
        }

        // Create a readable HTML preview
        $preview_html = $this->format_meta_preview($meta);

        // Create formatted JSON for modal
        $formatted_json = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $full_meta = esc_html($formatted_json);
        $modal_id = 'meta-modal-' . $item->id;

        return sprintf(
            '<div class="wp-logify-meta-preview">%s</div> <button type="button" class="button button-small wp-logify-view-meta" data-target="%s">%s</button>
            <div id="%s" class="wp-logify-meta-modal" style="display:none;">
                <div class="wp-logify-meta-content">
                    <span class="wp-logify-meta-close">&times;</span>
                    <h3>%s</h3>
                    <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">%s</pre>
                </div>
            </div>',
            $preview_html,
            esc_attr($modal_id),
            __('View JSON', 'wp-logify'),
            esc_attr($modal_id),
            __('Meta Data', 'wp-logify'),
            $full_meta
        );
    }

    /**
     * Format meta data for preview
     *
     * @param array $meta Meta data
     * @return string Formatted HTML
     */
    private function format_meta_preview($meta) {
        if (empty($meta)) {
            return '—';
        }

        $items = [];
        $count = 0;
        $max_items = 3; // Limite d'affichage dans la preview

        foreach ($meta as $key => $value) {
            if ($count >= $max_items) {
                $remaining = count($meta) - $max_items;
                $items[] = sprintf('<em>+%d %s</em>', $remaining, _n('other', 'others', $remaining, 'wp-logify'));
                break;
            }

            $formatted_value = $this->format_meta_value($value);
            $items[] = sprintf('<strong>%s:</strong> %s', esc_html($key), $formatted_value);
            $count++;
        }

        return implode('<br>', $items);
    }

    /**
     * Format a single meta value
     *
     * @param mixed $value Value to format
     * @return string Formatted value
     */
    private function format_meta_value($value) {
        if (is_array($value) || is_object($value)) {
            $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
            $preview = mb_substr($json, 0, 40);
            if (mb_strlen($json) > 40) {
                $preview .= '...';
            }
            return '<code>' . esc_html($preview) . '</code>';
        }

        if (is_bool($value)) {
            return $value ? '<em>true</em>' : '<em>false</em>';
        }

        if (is_null($value)) {
            return '<em>null</em>';
        }

        $str_value = (string) $value;
        if (mb_strlen($str_value) > 50) {
            $str_value = mb_substr($str_value, 0, 50) . '...';
        }

        return esc_html($str_value);
    }

    /**
     * Column created_at
     *
     * @param object $item Log item
     * @return string
     */
    public function column_created_at($item) {
        $timestamp = strtotime($item->created_at);
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        return sprintf(
            '<abbr title="%s">%s</abbr>',
            esc_attr($item->created_at),
            esc_html(date_i18n($date_format . ' ' . $time_format, $timestamp))
        );
    }

    /**
     * Default column renderer
     *
     * @param object $item Log item
     * @param string $column_name Column name
     * @return string
     */
    public function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
    }

    /**
     * Display extra tablenav (filters)
     *
     * @param string $which Position (top or bottom)
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $selected_actions = isset($_GET['filter_action']) && is_array($_GET['filter_action']) ? array_map('sanitize_text_field', $_GET['filter_action']) : [];
        $selected_users = isset($_GET['filter_user']) && is_array($_GET['filter_user']) ? array_map('absint', $_GET['filter_user']) : [];
        $selected_object_types = isset($_GET['filter_object_type']) && is_array($_GET['filter_object_type']) ? array_map('sanitize_text_field', $_GET['filter_object_type']) : [];

        $all_actions = WP_Logify::get_distinct_actions();
        $all_object_types = WP_Logify::get_distinct_object_types();

        // Get users who have created logs
        global $wpdb;
        $table_name = WP_Logify::get_table_name();
        $user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$table_name} WHERE user_id IS NOT NULL ORDER BY user_id ASC");

        ?>
        <div class="alignleft actions wp-logify-filters">
            <!-- Action Filter -->
            <div class="wp-logify-filter-group">
                <div class="wp-logify-dropdown">
                    <button type="button" class="button wp-logify-dropdown-toggle" id="filter-action-toggle" data-label="<?php esc_attr_e('Action', 'wp-logify'); ?>">
                        <strong><?php _e('Action:', 'wp-logify'); ?></strong>
                        <?php
                        if (!empty($selected_actions)) {
                            echo esc_html(sprintf(_n('%d selected', '%d selected', count($selected_actions), 'wp-logify'), count($selected_actions)));
                        } else {
                            _e('All', 'wp-logify');
                        }
                        ?> <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="wp-logify-dropdown-content" id="filter-action-content">
                        <?php foreach ($all_actions as $action): ?>
                            <label>
                                <input type="checkbox" name="filter_action[]" value="<?php echo esc_attr($action); ?>" <?php echo in_array($action, $selected_actions) ? 'checked' : ''; ?>>
                                <?php echo esc_html($action); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Object Type Filter -->
            <div class="wp-logify-filter-group">
                <div class="wp-logify-dropdown">
                    <button type="button" class="button wp-logify-dropdown-toggle" id="filter-object-type-toggle" data-label="<?php esc_attr_e('Object Type', 'wp-logify'); ?>">
                        <strong><?php _e('Object Type:', 'wp-logify'); ?></strong>
                        <?php
                        if (!empty($selected_object_types)) {
                            echo esc_html(sprintf(_n('%d selected', '%d selected', count($selected_object_types), 'wp-logify'), count($selected_object_types)));
                        } else {
                            _e('All', 'wp-logify');
                        }
                        ?> <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="wp-logify-dropdown-content" id="filter-object-type-content">
                        <?php foreach ($all_object_types as $otype): ?>
                            <label>
                                <input type="checkbox" name="filter_object_type[]" value="<?php echo esc_attr($otype); ?>" <?php echo in_array($otype, $selected_object_types) ? 'checked' : ''; ?>>
                                <?php echo esc_html($otype); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- User Filter -->
            <div class="wp-logify-filter-group">
                <div class="wp-logify-dropdown">
                    <button type="button" class="button wp-logify-dropdown-toggle" id="filter-user-toggle" data-label="<?php esc_attr_e('User', 'wp-logify'); ?>">
                        <strong><?php _e('User:', 'wp-logify'); ?></strong>
                        <?php
                        if (!empty($selected_users)) {
                            echo esc_html(sprintf(_n('%d selected', '%d selected', count($selected_users), 'wp-logify'), count($selected_users)));
                        } else {
                            _e('All', 'wp-logify');
                        }
                        ?> <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="wp-logify-dropdown-content" id="filter-user-content">
                        <?php foreach ($user_ids as $user_id):
                            $user = get_userdata($user_id);
                            if ($user):
                        ?>
                            <label>
                                <input type="checkbox" name="filter_user[]" value="<?php echo esc_attr($user_id); ?>" <?php echo in_array($user_id, $selected_users) ? 'checked' : ''; ?>>
                                <?php echo esc_html($user->display_name . ' (' . $user_id . ')'); ?>
                            </label>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="wp-logify-filter-group wp-logify-filter-buttons">
                <?php submit_button(__('Filter', 'wp-logify'), 'secondary', 'filter_submit', false); ?>
                <?php if (!empty($selected_actions) || !empty($selected_users) || !empty($selected_object_types)): ?>
                    <a href="<?php echo esc_url(remove_query_arg(['filter_action', 'filter_user', 'filter_object_type', 'paged'])); ?>" class="button">
                        <?php _e('Clear', 'wp-logify'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Prepare items for display
     */
    public function prepare_items() {
        // Register columns
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Handle bulk actions
        $this->process_bulk_action();

        // Get filter parameters
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        // Get filter parameters (support arrays)
        $filter_actions = isset($_GET['filter_action']) && is_array($_GET['filter_action']) ? array_map('sanitize_text_field', $_GET['filter_action']) : null;
        $filter_users = isset($_GET['filter_user']) && is_array($_GET['filter_user']) ? array_map('absint', $_GET['filter_user']) : null;
        $filter_object_types = isset($_GET['filter_object_type']) && is_array($_GET['filter_object_type']) ? array_map('sanitize_text_field', $_GET['filter_object_type']) : null;
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : null;

        // Build query args
        $args = [
            'limit' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => $orderby,
            'order' => $order
        ];

        if ($filter_actions && !empty($filter_actions)) {
            $args['action'] = $filter_actions;
        }

        if ($filter_users && !empty($filter_users)) {
            $args['user_id'] = $filter_users;
        }

        if ($filter_object_types && !empty($filter_object_types)) {
            $args['object_type'] = $filter_object_types;
        }

        if ($search) {
            $args['search'] = $search;
        }

        // Get items
        $this->items = WP_Logify::get_logs($args);

        // Build count args
        $count_args = [];
        if ($filter_actions && !empty($filter_actions)) {
            $count_args['action'] = $filter_actions;
        }
        if ($filter_users && !empty($filter_users)) {
            $count_args['user_id'] = $filter_users;
        }
        if ($filter_object_types && !empty($filter_object_types)) {
            $count_args['object_type'] = $filter_object_types;
        }
        if ($search) {
            $count_args['search'] = $search;
        }

        // Get total items for pagination
        $total_items = WP_Logify::count_logs($count_args);

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        // Handled in WP_Logify_Admin::handle_bulk_actions()
    }

    /**
     * Message to display when no items are found
     */
    public function no_items() {
        _e('No logs found.', 'wp-logify');
    }
}
