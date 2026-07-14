<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for WP Agent unit tests.
 *
 * For unit tests we mock WordPress functions via WP_Mock.
 * Integration tests use the standard WordPress test suite bootstrap.
 */

// Composer autoloader.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize WP_Mock for unit tests.
\WP_Mock::bootstrap();

// Define plugin constants needed without WP.
if ( ! defined('WPA_VERSION') ) {
    define('WPA_VERSION', '0.1.0-test');
}
if ( ! defined('WPA_DIR') ) {
    define('WPA_DIR', dirname(__DIR__) . '/');
}
if ( ! defined('WPA_URL') ) {
    define('WPA_URL', 'http://example.com/wp-content/plugins/wp-agent/');
}
if ( ! defined('WPA_MIN_WP_VERSION') ) {
    define('WPA_MIN_WP_VERSION', '6.4');
}
if ( ! defined('WPA_MIN_PHP_VERSION') ) {
    define('WPA_MIN_PHP_VERSION', '8.2');
}
if ( ! defined('ABSPATH') ) {
    define('ABSPATH', '/tmp/wordpress/');
}
