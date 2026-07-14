<?php

declare(strict_types=1);

namespace WpAgent\Tools\Themes;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.themes.demo.export
 *
 * Exports Customizer settings/mods for the active theme.
 *
 * Required scope: wp-agent:admin
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Themes
 * @since   0.1.0
 */
final class ExportDemoTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.themes.demo.export';
    }

    public function getDescription(): string
    {
        return 'Exports all customizer mods/configurations for the active theme.';
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
        return ['wp-agent:admin'];
    }

    public function getAnnotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_theme_options', $identity);

        $mods       = get_theme_mods();
        $stylesheet = get_stylesheet();

        return ToolResult::json([
            'success'    => true,
            'stylesheet' => $stylesheet,
            'options'    => is_array($mods) ? $mods : new \stdClass(),
        ]);
    }
}
