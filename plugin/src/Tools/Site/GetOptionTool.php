<?php

declare(strict_types=1);

namespace WpAgent\Tools\Site;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.options.get
 *
 * Reads a WordPress option value by key.
 *
 * Security: Certain option keys are blocked to prevent credential leakage
 * (auth_key, secure_auth_key, etc.). AI clients should use specific
 * site info tools for structured data rather than raw option reads.
 *
 * Required scope: wp-agent:read
 *
 * @package WpAgent\Tools\Site
 * @since   0.1.0
 */
final class GetOptionTool extends AbstractTool
{
    /**
     * Options that must never be returned, regardless of caller permissions.
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
        'admin_password', // custom stores.
        'wpa_auth_keys',
        'wpa_jwt_secret',
    ];

    public function getName(): string
    {
        return 'wordpress.options.get';
    }

    public function getDescription(): string
    {
        return 'Reads a WordPress option value by its option name. '
            . 'Security-sensitive option keys (auth keys, salts) are blocked. '
            . 'Use wordpress.site.info for structured site data instead of raw options.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'option_name' => [
                    'type'        => 'string',
                    'description' => 'The WordPress option name (e.g. "blogname", "permalink_structure").',
                    'minLength'   => 1,
                    'maxLength'   => 191,
                ],
                'default' => [
                    'description' => 'Default value if option does not exist.',
                ],
            ],
            'required'             => ['option_name'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:read'];
    }

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint'   => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $optionName = sanitize_key($args['option_name']);
        $default    = $args['default'] ?? false;

        // Security: block sensitive options.
        if ( in_array($optionName, self::BLOCKED_OPTIONS, true) ) {
            return ToolResult::error(
                "Option '{$optionName}' is blocked for security reasons."
            );
        }

        $value = get_option($optionName, $default);

        if ( false === $value && false === $default ) {
            return ToolResult::json([
                'option_name' => $optionName,
                'exists'      => false,
                'value'       => null,
            ]);
        }

        return ToolResult::json([
            'option_name' => $optionName,
            'exists'      => true,
            'value'       => $value,
        ]);
    }
}
