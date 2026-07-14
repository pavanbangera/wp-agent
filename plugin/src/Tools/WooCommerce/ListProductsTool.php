<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsWoo;

/**
 * Tool: wordpress.woo.product.list
 *
 * Lists WooCommerce products.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class ListProductsTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.product.list';
    }

    public function getDescription(): string
    {
        return 'Lists products from the WooCommerce catalog with filtering and pagination.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status'   => [
                    'type'    => 'string',
                    'enum'    => ['any', 'publish', 'draft', 'private'],
                    'default' => 'any',
                ],
                'category' => [
                    'type'        => 'string',
                    'description' => 'Category slug to filter by.',
                ],
                'search'   => [
                    'type'        => 'string',
                    'description' => 'Search term.',
                ],
                'page'     => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'default'     => 1,
                ],
                'per_page' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'default'     => 20,
                ],
            ],
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
        $this->requireCapability('read', $identity);

        $results = $this->woocommerceService->listProducts($args);

        return ToolResult::json([
            'products'     => array_map(
                static fn (\WC_Product $p): array => FormatsWoo::formatProduct($p),
                $results['products']
            ),
            'total'        => $results['total'],
            'total_pages'  => $results['pages'],
            'per_page'     => $args['per_page'] ?? 20,
            'current_page' => $args['page'] ?? 1,
        ]);
    }
}
