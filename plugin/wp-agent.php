<?php
/**
 * Plugin Name:       WP Agent
 * Plugin URI:        https://github.com/wp-agent/wp-agent
 * Description:       The Open Source AI Agent for WordPress. Control your site with AI IDEs via the Model Context Protocol.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            WP Agent Contributors
 * Author URI:        https://github.com/wp-agent
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-agent
 * Domain Path:       /languages
 *
 * @package WpAgent
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

namespace WpAgent;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Minimum requirements check — bail early with an admin notice on failure.
if ( version_compare( PHP_VERSION, '8.2.0', '<' ) ) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'WP Agent requires PHP 8.2 or higher. Please upgrade your PHP version.',
                'wp-agent'
            );
            echo '</p></div>';
        }
    );
    return;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'WP Agent: Composer dependencies are missing. Please run `composer install` in the plugin directory.',
                'wp-agent'
            );
            echo '</p></div>';
        }
    );
    return;
}

// Load Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// Define plugin constants.
define( 'WPA_VERSION', '0.1.0' );
define( 'WPA_FILE', __FILE__ );
define( 'WPA_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPA_URL', plugin_dir_url( __FILE__ ) );
define( 'WPA_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPA_MIN_WP_VERSION', '6.4' );
define( 'WPA_MIN_PHP_VERSION', '8.2' );

// Bootstrap the plugin after all plugins have loaded (allows extension hooks).
add_action( 'plugins_loaded', static function (): void {
    Core\Plugin::getInstance()->boot();
}, 0 );

// Activation / deactivation hooks must be registered at file load time.
register_activation_hook( __FILE__, [ Core\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Core\Plugin::class, 'deactivate' ] );
register_uninstall_hook( __FILE__, [ Core\Plugin::class, 'uninstall' ] );
