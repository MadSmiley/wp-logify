<?php
/**
 * Plugin Name: WP Logify
 * Plugin URI: https://github.com/MadSmiley/wp-logify
 * Description: A comprehensive logging system for WordPress that tracks user actions, events, and custom activities.
 * Version: 1.0.0
 * Author: MadSmiley
 * Author URI: https://www.linkedin.com/in/germain-belacel/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-logify
 * Domain Path: /languages
 *
 * @package WP_Logify
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_LOGIFY_VERSION', '1.0.0');
define('WP_LOGIFY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_LOGIFY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_LOGIFY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WP Logify Class
 *
 * @since 1.0.0
 */
final class WP_Logify_Bootstrap {

    /**
     * Single instance of the class
     *
     * @var WP_Logify_Bootstrap
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return WP_Logify_Bootstrap
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once WP_LOGIFY_PLUGIN_DIR . 'includes/wp-logify.php';

        if (is_admin()) {
            require_once WP_LOGIFY_PLUGIN_DIR . 'admin/wp-logify-admin.php';
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init']);
        add_action('upgrader_process_complete', [$this, 'on_plugin_upgrade'], 10, 2);
    }

    /**
     * Plugin activation callback
     */
    public function activate() {
        WP_Logify::create_table();
        WP_Logify::schedule_cleanup_cron();

        // Log plugin activation
        wp_logify_log('plugin_activated', 'plugin', null, [
            'plugin' => 'WP Logify',
            'version' => WP_LOGIFY_VERSION
        ]);
    }

    /**
     * Plugin deactivation callback
     */
    public function deactivate() {
        WP_Logify::unschedule_cleanup_cron();

        // Log plugin deactivation
        wp_logify_log('plugin_deactivated', 'plugin', null, [
            'plugin' => 'WP Logify',
            'version' => WP_LOGIFY_VERSION
        ]);
    }

    /**
     * Plugin upgrade callback
     *
     * @param WP_Upgrader $upgrader_object WP_Upgrader instance
     * @param array $options Array of bulk item update data
     */
    public function on_plugin_upgrade($upgrader_object, $options) {
        // Check if this is a plugin update
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        // Check if our plugin was updated
        if (!isset($options['plugins']) || !is_array($options['plugins'])) {
            return;
        }

        foreach ($options['plugins'] as $plugin) {
            if ($plugin === WP_LOGIFY_PLUGIN_BASENAME) {
                $this->upgrade();
                break;
            }
        }
    }

    /**
     * Handle plugin upgrade tasks
     */
    private function upgrade() {
        // Get stored version
        $stored_version = get_option('wp_logify_db_version', '0.0.0');

        // If versions match, no upgrade needed
        if (version_compare($stored_version, WP_LOGIFY_VERSION, '>=')) {
            return;
        }

        // Run table creation/update (dbDelta will handle schema changes)
        WP_Logify::create_table();

        // Log the upgrade
        wp_logify_log('plugin_upgraded', 'plugin', null, [
            'plugin' => 'WP Logify',
            'from_version' => $stored_version,
            'to_version' => WP_LOGIFY_VERSION
        ]);

        // Update stored version
        update_option('wp_logify_db_version', WP_LOGIFY_VERSION);
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize core class
        WP_Logify::init();

        // Initialize admin interface
        if (is_admin()) {
            WP_Logify_Admin::init();
        }

        // Load text domain for translations
        load_plugin_textdomain('wp-logify', false, dirname(WP_LOGIFY_PLUGIN_BASENAME) . '/languages');
    }
}

/**
 * Utility function to log an action
 *
 * @param string $action The action being logged
 * @param string|null $object_type Optional object type (post, user, order, etc.)
 * @param string|int|null $object_id Optional object ID (can be int or string UID)
 * @param array $meta Optional metadata array
 * @return int|false Log entry ID on success, false on failure
 */
function wp_logify_log($action, $object_type = null, $object_id = null, $meta = []) {
    return WP_Logify::log($action, $object_type, $object_id, $meta);
}

// Initialize the plugin
WP_Logify_Bootstrap::get_instance();
