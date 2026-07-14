<?php

declare(strict_types=1);

namespace WpAgent\Core;

/**
 * Immutable configuration value object.
 *
 * Wraps plugin options with type-safe accessors and dot-notation support.
 * Merges WordPress option storage with runtime defaults.
 *
 * @package WpAgent\Core
 * @since   0.1.0
 */
final class Config
{
    /** @var array<string, mixed> */
    private readonly array $data;

    /**
     * @param array<string, mixed> $overrides Runtime overrides (for testing).
     */
    public function __construct(array $overrides = [])
    {
        $stored = get_option('wpa_settings', []);
        if ( ! is_array($stored) ) {
            $stored = [];
        }

        $this->data = array_replace_recursive($this->defaults(), $stored, $overrides);
    }

    /**
     * Get a config value by dot-notation key.
     *
     * @param string $key     Dot-separated key path (e.g. "auth.jwt.expiry").
     * @param mixed  $default Fallback if key is not set.
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current  = $this->data;

        foreach ( $segments as $segment ) {
            if ( ! is_array($current) || ! array_key_exists($segment, $current) ) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_int($value) ? $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        return (bool) $value;
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Returns all config data (for serialization/debugging).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Plugin default configuration values.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'version' => WPA_VERSION,
            'auth'    => [
                'default_driver' => 'application_password',
                'drivers'        => ['application_password', 'jwt', 'api_key'],
                'jwt'            => [
                    'secret'   => '',
                    'expiry'   => 900,       // 15 minutes.
                    'refresh'  => 604800,    // 7 days.
                    'algorithm' => 'HS256',
                ],
            ],
            'mcp'     => [
                'protocol_version' => '2024-11-05',
                'server_name'      => 'wp-agent',
                'server_version'   => WPA_VERSION,
                'max_payload_size' => 10 * 1024 * 1024, // 10 MB.
                'sse_heartbeat'    => 30,                // seconds.
            ],
            'rate_limit' => [
                'enabled'           => true,
                'window_seconds'    => 60,
                'max_requests'      => 120,
                'tool_call_limit'   => 30,
            ],
            'logging'  => [
                'enabled' => true,
                'level'   => 'info',
                'drivers' => ['database'],
                'retention_days' => 30,
            ],
            'tools'    => [
                'enabled' => true,
                'allowed_scopes' => [
                    'wp-agent:read',
                    'wp-agent:write',
                    'wp-agent:admin',
                    'wp-agent:developer',
                    'wp-agent:security',
                    'wp-agent:superadmin',
                ],
            ],
        ];
    }
}
