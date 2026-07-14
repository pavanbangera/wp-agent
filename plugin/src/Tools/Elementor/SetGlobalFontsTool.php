<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.global_fonts.set
 *
 * Configures global typography families and weights inside Elementor Kit.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class SetGlobalFontsTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.global_fonts.set';
    }

    public function getDescription(): string
    {
        return 'Configures global default typography (family and weight) inside the active Elementor Kit.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'fonts' => [
                    'type'        => 'object',
                    'description' => 'Font configuration mapping (e.g. {"primary": {"family": "Outfit", "weight": "600"}}).',
                    'properties'  => [
                        'primary'   => [
                            'type'       => 'object',
                            'properties' => [
                                'family' => ['type' => 'string'],
                                'weight' => ['type' => 'string'],
                            ],
                            'required'   => ['family'],
                        ],
                        'secondary' => [
                            'type'       => 'object',
                            'properties' => [
                                'family' => ['type' => 'string'],
                                'weight' => ['type' => 'string'],
                            ],
                            'required'   => ['family'],
                        ],
                        'text'      => [
                            'type'       => 'object',
                            'properties' => [
                                'family' => ['type' => 'string'],
                                'weight' => ['type' => 'string'],
                            ],
                            'required'   => ['family'],
                        ],
                        'accent'    => [
                            'type'       => 'object',
                            'properties' => [
                                'family' => ['type' => 'string'],
                                'weight' => ['type' => 'string'],
                            ],
                            'required'   => ['family'],
                        ],
                    ],
                ],
            ],
            'required'             => ['fonts'],
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

        $fonts = (array) $args['fonts'];
        $this->elementorService->setGlobalFonts($fonts);

        return ToolResult::json([
            'success' => true,
            'fonts'   => $fonts,
            'message' => 'Elementor global typography styles successfully updated.',
        ]);
    }
}
