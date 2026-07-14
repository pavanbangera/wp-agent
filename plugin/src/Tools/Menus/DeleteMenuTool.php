<?php

declare(strict_types=1);

namespace WpAgent\Tools\Menus;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MenuService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.menus.delete
 *
 * Deletes a navigation menu.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Menus
 * @since   0.1.0
 */
final class DeleteMenuTool extends AbstractTool
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.menus.delete';
    }

    public function getDescription(): string
    {
        return 'Deletes a Navigation Menu by ID. '
            . 'Removes the menu term from the database. '
            . 'Menu item assignments are cleaned up automatically. '
            . 'CAUTION: This does not delete original pages or posts, but cannot be undone.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'menu_id' => [
                    'type'        => 'integer',
                    'description' => 'The navigation menu term ID to delete.',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['menu_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    public function getAnnotations(): array
    {
        return ['destructiveHint' => true, 'readOnlyHint' => false];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_theme_options', $identity);

        $menuId = (int) $args['menu_id'];

        $this->menuService->delete($menuId);

        return ToolResult::json([
            'success' => true,
            'menu_id' => $menuId,
            'message' => "Navigation Menu #{$menuId} has been successfully deleted.",
        ]);
    }
}
