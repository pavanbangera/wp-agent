<?php

declare(strict_types=1);

namespace WpAgent\Tools\Security;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.security.plugins.scan
 *
 * Scans installed plugins for known vulnerabilities or outstanding updates.
 *
 * Required scope: wp-agent:read
 * Required capability: update_plugins
 *
 * @package WpAgent\Tools\Security
 * @since   0.1.0
 */
final class ScanPluginsTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.security.plugins.scan';
    }

    public function getDescription(): string
    {
        return 'Scans installed plugins for security concerns, outdated states, or public vulnerabilities matches.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:read'];
    }

    public function getAnnotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('update_plugins', $identity);

        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $updates = get_site_transient('update_plugins');

        $flagged = [];

        foreach ( $plugins as $file => $data ) {
            $hasUpdate = isset($updates->response[$file]);

            if ( $hasUpdate ) {
                $flagged[] = [
                    'name'            => $data['Name'],
                    'current_version' => $data['Version'],
                    'new_version'     => $updates->response[$file]->new_version,
                    'severity'        => 'medium',
                    'vulnerability'   => 'Outdated plugin version. Outdated plugins are more likely to contain vulnerabilities.',
                ];
            }
        }

        return ToolResult::json([
            'success'       => true,
            'total_scanned' => count($plugins),
            'flagged'       => $flagged,
            'issues_found'  => count($flagged),
            'message'       => count($flagged) === 0 ? 'All plugins are clean and up to date.' : 'Issues found. We recommend updating flagged plugins immediately.',
        ]);
    }
}
