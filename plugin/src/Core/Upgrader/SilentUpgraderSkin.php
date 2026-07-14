<?php

declare(strict_types=1);

namespace WpAgent\Core\Upgrader;

// Ensure standard WP files are loaded.
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * A silent, non-HTML-outputting upgrader skin for plugin and theme installation.
 *
 * Prevents WordPress from echoing raw HTML progress text and tags during
 * execution, returning status information and errors programmatically.
 *
 * @package WpAgent\Core\Upgrader
 * @since   0.1.0
 */
final class SilentUpgraderSkin extends \WP_Upgrader_Skin
{
    /** @var string[] List of messages emitted during upgrader execution. */
    public array $messages = [];

    /** @var string[] List of errors emitted. */
    public array $errors = [];

    /**
     * Overrides main feedback method to log messages rather than printing them.
     *
     * @param string $string feedback text.
     * @param mixed  ...$args
     */
    public function feedback($string, ...$args): void
    {
        if ( isset( $this->upgrader->strings[$string] ) ) {
            $string = $this->upgrader->strings[$string];
        }

        if ( str_contains($string, '%') && ! empty($args) ) {
            $string = vsprintf($string, $args);
        }

        $string = strip_tags($string);

        if ( ! empty($string) ) {
            $this->messages[] = $string;
        }
    }

    /**
     * Overrides error handling to prevent rendering HTML blocks.
     *
     * @param string|\WP_Error $errors
     */
    public function error($errors): void
    {
        if ( is_wp_error($errors) ) {
            foreach ( $errors->get_error_messages() as $message ) {
                $this->errors[] = strip_tags($message);
            }
        } elseif ( is_string($errors) ) {
            $this->errors[] = strip_tags($errors);
        }
    }

    /**
     * Disable default header printing.
     */
    public function header(): void {}

    /**
     * Disable default footer printing.
     */
    public function footer(): void {}

    /**
     * Disable default action links.
     */
    public function decrement_update_count($type): void {}
}
