<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.global_colors.set
 *
 * Configures the primary/secondary global colors inside the Elementor Kit.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class SetGlobalColorsTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.global_colors.set';
    }

    public function getDescription(): string
    {
        return 'Sets global system color palettes (primary, secondary, text, accent) '
            . 'inside the active Elementor styling Kit and clears element cache.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'colors' => [
                    'type'                 => 'object',
                    'description'          => 'Key-value mapping of color identifiers to hex color codes (e.g. {"primary": "#1188ff"}).',
                    'additionalProperties' => [
                        'type'    => 'string',
                        'pattern' => '^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$',
                    ],
                ],
            ],
            'required'             => ['colors'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_theme_options', $identity);

        $colors = (array) $args['colors'];
        $this->elementorService->setGlobalColors($colors);

        return ToolResult::json([
            'success' => true,
            'colors'  => $colors,
            'message' => 'Elementor global color palette successfully updated.',
        ]);
    }
}
