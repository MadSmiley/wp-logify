<?php
/**
 * WP Logify Uninstall Script
 *
 * Fires when the plugin is uninstalled via WordPress admin.
 * Cleans up database table and plugin options.
 *
 * @package WP_Logify
 * @since 1.0.0
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Define table name
$table_name = $wpdb->prefix . 'wp_logify';

// Drop the logs table
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options
delete_option('wp_logify_db_version');

// Clear any cached data
wp_cache_flush();

// Unschedule cron job if it exists
$timestamp = wp_next_scheduled('wp_logify_cleanup_logs');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'wp_logify_cleanup_logs');
}
