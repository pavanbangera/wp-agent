<?php

declare(strict_types=1);

namespace WpAgent\Tools\Site;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.options.set
 *
 * Updates a WordPress option value by its option name. Security-sensitive option keys
 * (auth keys, salts, JWT secrets) are blocked regardless of caller permissions.
 *
 * Required scope: wp-agent:write
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\Site
 * @since   0.1.0
 */
final class SetOptionTool extends AbstractTool
{
    /**
     * Options that must never be written, regardless of caller permissions.
     * Mirrors the blocked list in GetOptionTool.
     *
     * @var string[]
     */
    private const BLOCKED_OPTIONS = [
        'auth_key',
        'secure_auth_key',
        'logged_in_key',
        'nonce_key',
        'auth_salt',
        'secure_auth_salt',
        'logged_in_salt',
        'nonce_salt',
        'admin_password',
        'wpa_auth_keys',
        'wpa_jwt_secret',
        // Additional write-only sensitive options.
        'siteurl',
        'home',
        'admin_email',
    ];

    public function getName(): string
    {
        return 'wordpress.options.set';
    }

    public function getDescription(): string
    {
        return 'Updates a WordPress option value by its option name. '
            . 'Security-sensitive option keys (auth keys, salts, site URL, admin email) are blocked. '
            . 'Returns the previous value so changes can be verified or rolled back. '
            . 'For reading options, use wordpress.options.get.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'option_name' => [
                    'type'        => 'string',
                    'description' => 'The WordPress option name (e.g. "blogname", "show_on_front", "permalink_structure").',
                    'minLength'   => 1,
                    'maxLength'   => 191,
                ],
                'value' => [
                    'description' => 'The new value to store. Can be a string, integer, boolean, or array.',
                ],
                'autoload' => [
                    'type'        => 'string',
                    'description' => 'Whether to autoload this option. "yes" or "no". Defaults to current setting.',
                    'enum'        => ['yes', 'no'],
                ],
            ],
            'required'             => ['option_name', 'value'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);

        $optionName = sanitize_key($args['option_name']);
        $newValue   = $args['value'];
        $autoload   = $args['autoload'] ?? null;

        // Security: block sensitive options.
        if ( in_array($optionName, self::BLOCKED_OPTIONS, true) ) {
            return ToolResult::error(
                "Option '{$optionName}' is blocked for security reasons and cannot be updated via this tool."
            );
        }

        // Capture previous value before writing.
        $previousValue = get_option($optionName);
        $existed       = ($previousValue !== false);

        // Update or add the option.
        if ( null !== $autoload ) {
            $updated = update_option($optionName, $newValue, $autoload);
        } else {
            $updated = update_option($optionName, $newValue);
        }

        return ToolResult::json([
            'success'        => true,
            'option_name'    => $optionName,
            'updated'        => $updated,
            'previously_existed' => $existed,
            'previous_value' => $previousValue !== false ? $previousValue : null,
            'new_value'      => get_option($optionName),
            'message'        => $updated
                ? "Option '{$optionName}' updated successfully."
                : "Option '{$optionName}' was not changed (value may already be identical).",
        ]);
    }
}
