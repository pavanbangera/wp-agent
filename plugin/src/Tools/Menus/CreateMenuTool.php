<?php

declare(strict_types=1);

namespace WpAgent\Tools\Menus;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MenuService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMenu;

/**
 * Tool: wordpress.menus.create
 *
 * Creates a new navigation menu.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Menus
 * @since   0.1.0
 */
final class CreateMenuTool extends AbstractTool
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.menus.create';
    }

    public function getDescription(): string
    {
        return 'Creates a new empty Navigation Menu with the specified name (e.g. "Primary Navigation"). '
            . 'Returns the created menu term object. Use wordpress.menus.update to assign links and hierarchy.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'The user-facing name for the navigation menu.',
                    'minLength'   => 1,
                    'maxLength'   => 100,
                ],
            ],
            'required'             => ['name'],
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

        $name = $args['name'];
        $menu = $this->menuService->create($name);

        return ToolResult::json(FormatsMenu::format($menu));
    }
}
