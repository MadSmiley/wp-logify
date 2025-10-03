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
            '<code>%d</code>',
            $item->object_id
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

        // Create a preview (first 50 chars)
        $meta_string = wp_json_encode($meta, JSON_UNESCAPED_UNICODE);
        $preview = mb_substr($meta_string, 0, 50);

        if (mb_strlen($meta_string) > 50) {
            $preview .= '...';
        }

        // Create expandable meta viewer
        $full_meta = esc_html($meta_string);
        $modal_id = 'meta-modal-' . $item->id;

        return sprintf(
            '<span class="wp-logify-meta-preview">%s</span> <button type="button" class="button button-small wp-logify-view-meta" data-target="%s">%s</button>
            <div id="%s" class="wp-logify-meta-modal" style="display:none;">
                <div class="wp-logify-meta-content">
                    <span class="wp-logify-meta-close">&times;</span>
                    <h3>%s</h3>
                    <pre>%s</pre>
                </div>
            </div>',
            esc_html($preview),
            esc_attr($modal_id),
            __('View', 'wp-logify'),
            esc_attr($modal_id),
            __('Meta Data', 'wp-logify'),
            $full_meta
        );
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
     * Get filter views
     *
     * @return array
     */
    public function get_views() {
        $current_action = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '';

        $views = [];

        // All logs
        $class = empty($current_action) ? 'current' : '';
        $all_url = remove_query_arg(['filter_action', 'paged']);
        $total_logs = WP_Logify::count_logs();
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url($all_url),
            $class,
            __('All', 'wp-logify'),
            $total_logs
        );

        // Get distinct actions
        $actions = WP_Logify::get_distinct_actions();

        foreach ($actions as $action) {
            $class = $current_action === $action ? 'current' : '';
            $action_url = add_query_arg(['filter_action' => $action, 'paged' => false]);
            $action_count = WP_Logify::count_logs(['action' => $action]);

            $views[$action] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url($action_url),
                $class,
                esc_html($action),
                $action_count
            );
        }

        return $views;
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

        $filter_action = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : null;
        $filter_user = isset($_GET['filter_user']) ? absint($_GET['filter_user']) : null;

        // Build query args
        $args = [
            'limit' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => $orderby,
            'order' => $order
        ];

        if ($filter_action) {
            $args['action'] = $filter_action;
        }

        if ($filter_user) {
            $args['user_id'] = $filter_user;
        }

        // Get items
        $this->items = WP_Logify::get_logs($args);

        // Get total items for pagination
        $total_items = WP_Logify::count_logs($filter_action ? ['action' => $filter_action] : []);

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
