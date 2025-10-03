<?php
/**
 * WP Logify Admin Interface
 *
 * Handles the admin menu, page, and WP_List_Table display.
 *
 * @package WP_Logify
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class WP_Logify_Admin {

    /**
     * Initialize the admin interface
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // Handle bulk actions
        add_action('admin_init', [__CLASS__, 'handle_bulk_actions']);
    }

    /**
     * Add admin menu page
     */
    public static function add_admin_menu() {
        add_management_page(
            __('WP Logify', 'wp-logify'),
            __('WP Logify', 'wp-logify'),
            'manage_options',
            'wp-logify',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin styles and scripts
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueue_admin_assets($hook) {
        // Only load on our plugin page
        if ($hook !== 'tools_page_wp-logify') {
            return;
        }

        wp_enqueue_style(
            'wp-logify-admin',
            WP_LOGIFY_PLUGIN_URL . 'admin/css/admin.css',
            [],
            WP_LOGIFY_VERSION
        );

        wp_enqueue_script(
            'wp-logify-admin',
            WP_LOGIFY_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            WP_LOGIFY_VERSION,
            true
        );

        // Localize script for translations
        wp_localize_script('wp-logify-admin', 'wpLogifyL10n', [
            'all' => __('All', 'wp-logify'),
            'selected' => __('selected', 'wp-logify')
        ]);
    }

    /**
     * Render the admin page
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-logify'));
        }

        // Load the list table class
        require_once WP_LOGIFY_PLUGIN_DIR . 'admin/wp-logify-list-table.php';

        $list_table = new WP_Logify_List_Table();
        $list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="wp-logify">
                <?php
                $list_table->search_box(__('Search Logs', 'wp-logify'), 'wp-logify-search');
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle bulk actions
     */
    public static function handle_bulk_actions() {
        // Check if we're on the right page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-logify') {
            return;
        }

        // Check for bulk delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete') {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-logify'));
            }

            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'bulk-logs')) {
                wp_die(__('Security check failed.', 'wp-logify'));
            }

            // Get log IDs
            $log_ids = isset($_GET['log']) ? (array) $_GET['log'] : [];

            if (!empty($log_ids)) {
                foreach ($log_ids as $log_id) {
                    WP_Logify::delete_log($log_id);
                }

                // Redirect to avoid resubmission
                wp_redirect(add_query_arg([
                    'page' => 'wp-logify',
                    'deleted' => count($log_ids)
                ], admin_url('tools.php')));
                exit;
            }
        }
    }
}
