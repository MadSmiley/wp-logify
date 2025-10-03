# WP Logify

A comprehensive logging system for WordPress that tracks user actions, events, and custom activities.

## Description

WP Logify provides a powerful and flexible logging system for WordPress. It allows you to track any action, event, or custom activity in your WordPress site with detailed metadata. Perfect for debugging, auditing, analytics, and monitoring user activities.

## Features

- 🗄️ **Dedicated Database Table** - Clean, optimized table structure with indexed columns
- 👤 **Automatic User Tracking** - Automatically captures the current user ID for each log entry
- 🎯 **Flexible Metadata** - Store any additional data as JSON in the meta field
- 📊 **Admin Interface** - Beautiful WP_List_Table display under Tools > WP Logify
- 🔍 **Advanced Filtering** - Filter logs by user, action, date range with pagination
- 🗑️ **Auto-Cleanup** - Daily cron job to purge logs older than 90 days
- 🪝 **Developer-Friendly Hooks** - Multiple action and filter hooks for extensibility
- 🔒 **Security-First** - Proper escaping, sanitization, and capability checks

## Installation

1. Download the plugin files
2. Upload the `wp-logify` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Access logs via **Tools > WP Logify** in your admin menu

The database table will be created automatically upon activation.

## Usage

### Basic Logging

Use the utility function anywhere in your code:

```php
// Simple log
wp_logify_log('user_login');

// Log with object ID
wp_logify_log('post_updated', 123);

// Log with metadata
wp_logify_log('payment_received', $order_id, [
    'amount' => 99.99,
    'currency' => 'USD',
    'gateway' => 'stripe'
]);
```

### Advanced Usage

```php
// Log complex actions
wp_logify_log('api_request', null, [
    'endpoint' => '/api/v1/users',
    'method' => 'POST',
    'response_code' => 200,
    'duration' => 0.523
]);

// Track form submissions
wp_logify_log('contact_form_submitted', null, [
    'form_id' => 'contact-us',
    'fields' => ['name', 'email', 'message'],
    'ip_address' => $_SERVER['REMOTE_ADDR']
]);
```

### User ID Handling

The `user_id` is automatically captured from the current logged-in user:
- Logged-in users: Stores their WordPress user ID
- Guests/visitors: Stores `NULL`

## API Reference

### Core Function

```php
wp_logify_log( string $action, int|null $object_id = null, array $meta = [] )
```

**Parameters:**
- `$action` (string, required) - The action being logged
- `$object_id` (int, optional) - Related object ID (post, user, order, etc.)
- `$meta` (array, optional) - Associative array of additional data

**Returns:** `int|false` - Log entry ID on success, false on failure

### Class Methods

```php
// Retrieve logs
WP_Logify::get_logs( array $args = [] );

// Count logs
WP_Logify::count_logs( array $args = [] );

// Delete a log
WP_Logify::delete_log( int $log_id );

// Manual cleanup
WP_Logify::cleanup_old_logs( int $days = 90 );

// Get all distinct actions
WP_Logify::get_distinct_actions();
```

#### Query Arguments

```php
$args = [
    'user_id'   => 1,              // Filter by user ID
    'action'    => 'user_login',   // Filter by action
    'object_id' => 123,            // Filter by object ID
    'date_from' => '2024-01-01',   // Filter by start date
    'date_to'   => '2024-12-31',   // Filter by end date
    'limit'     => 20,             // Results per page
    'offset'    => 0,              // Pagination offset
    'orderby'   => 'created_at',   // Order by column
    'order'     => 'DESC'          // ASC or DESC
];

$logs = WP_Logify::get_logs($args);
```

## Hooks

### Actions

**`wp_logify_log`**
Fires after a log entry is created.

```php
do_action( 'wp_logify_log', string $action, int|null $object_id, array $meta, int $log_id );

// Example usage
add_action('wp_logify_log', function($action, $object_id, $meta, $log_id) {
    if ($action === 'critical_error') {
        // Send notification to admin
        wp_mail(get_option('admin_email'), 'Critical Error', 'A critical error was logged.');
    }
}, 10, 4);
```

**`wp_logify_after_log`**
Fires after a log entry is inserted into the database.

```php
do_action( 'wp_logify_after_log', int $log_id, array $data );
```

### Filters

**`wp_logify_before_log`**
Filter log data before insertion.

```php
apply_filters( 'wp_logify_before_log', array $data, string $action, int|null $object_id, array $meta );

// Example usage
add_filter('wp_logify_before_log', function($data, $action, $object_id, $meta) {
    // Add IP address to all logs
    if (is_array($data['meta'])) {
        $meta_decoded = json_decode($data['meta'], true);
        $meta_decoded['ip'] = $_SERVER['REMOTE_ADDR'];
        $data['meta'] = wp_json_encode($meta_decoded);
    }
    return $data;
}, 10, 4);
```

## Database Schema

Table: `{prefix}_wp_logify`

| Column      | Type                | Description                           |
|-------------|---------------------|---------------------------------------|
| `id`        | bigint(20) UNSIGNED | Primary key, auto-increment           |
| `user_id`   | bigint(20) UNSIGNED | WordPress user ID (NULL for guests)   |
| `action`    | varchar(255)        | Action identifier                     |
| `object_id` | bigint(20) UNSIGNED | Related object ID (NULL if not used)  |
| `meta`      | longtext            | JSON-encoded metadata                 |
| `created_at`| datetime            | Timestamp of log entry                |

**Indexes:** `user_id`, `action`, `object_id`, `created_at`

## Common Use Cases

### Track User Authentication

```php
add_action('wp_login', function($user_login, $user) {
    wp_logify_log('user_login', $user->ID, [
        'username' => $user_login,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
}, 10, 2);

add_action('wp_logout', function() {
    wp_logify_log('user_logout');
});
```

### Track Post Changes

```php
add_action('save_post', function($post_id, $post, $update) {
    if ($update) {
        wp_logify_log('post_updated', $post_id, [
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'post_title' => $post->post_title
        ]);
    }
}, 10, 3);
```

### Track WooCommerce Orders

```php
add_action('woocommerce_new_order', function($order_id) {
    $order = wc_get_order($order_id);
    wp_logify_log('order_created', $order_id, [
        'total' => $order->get_total(),
        'currency' => $order->get_currency(),
        'payment_method' => $order->get_payment_method()
    ]);
});
```

### Track Failed Login Attempts

```php
add_action('wp_login_failed', function($username) {
    wp_logify_log('login_failed', null, [
        'username' => $username,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
});
```

## Automatic Cleanup

WP Logify automatically deletes logs older than 90 days using WordPress Cron:
- **Schedule:** Daily
- **Retention:** 90 days (configurable)

To change retention period:

```php
// Keep logs for 30 days instead
add_action('wp_logify_cleanup_logs', function() {
    WP_Logify::cleanup_old_logs(30);
});
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Support

For issues, questions, or contributions, please visit:
- [GitHub Repository](https://github.com/yourusername/wp-logify)
- [Documentation](https://yourwebsite.com/wp-logify-docs)

## License

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

## Changelog

### 1.0.0
- Initial release
- Database table creation with dbDelta
- Admin interface with WP_List_Table
- Utility function for logging
- Automatic cleanup cron job
- Filter and search capabilities
- Developer hooks and filters
