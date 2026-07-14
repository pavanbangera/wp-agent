<?php

declare(strict_types=1);

namespace WpAgent\Tools\Themes;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.themes.demo.import
 *
 * Imports customizer settings or theme options.
 * Useful for demo/starter site configurations.
 *
 * Required scope: wp-agent:admin
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Themes
 * @since   0.1.0
 */
final class ImportDemoTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.themes.demo.import';
    }

    public function getDescription(): string
    {
        return 'Imports theme Customizer options or custom settings payload. '
            . 'Allows you to programmatically configure active theme styling and options.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'options' => [
                    'type'        => 'object',
                    'description' => 'Key-value map of Customizer mods/settings to import.',
                ],
            ],
            'required'             => ['options'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_theme_options', $identity);

        $options    = (array) $args['options'];
        $stylesheet = get_stylesheet();

        // Update Theme Mods in WordPress.
        foreach ( $options as $key => $value ) {
            set_theme_mod((string) $key, $value);
        }

        return ToolResult::json([
            'success'    => true,
            'stylesheet' => $stylesheet,
            'imported'   => array_keys($options),
            'count'      => count($options),
            'message'    => "Successfully imported options for theme '{$stylesheet}'.",
        ]);
    }
}
