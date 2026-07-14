<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.woo.shipping.configure
 *
 * Configures shipping zones or classes.
 *
 * Required scope: wp-agent:admin
 * Required capability: manage_woocommerce
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class ConfigureShippingTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.shipping.configure';
    }

    public function getDescription(): string
    {
        return 'Configures WooCommerce shipping zones or adds new shipping classes.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['create_class', 'create_zone'],
                    'description' => 'Target shipping option action.',
                ],
                'name'   => [
                    'type'        => 'string',
                    'description' => 'The name of the shipping class or zone (e.g. "Heavy Goods" or "Europe Zone").',
                    'minLength'   => 1,
                ],
                'slug'   => [
                    'type'        => 'string',
                    'description' => 'Slug (required for shipping class).',
                ],
            ],
            'required'             => ['action', 'name'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);
        $this->woocommerceService->requireWooCommerce();

        $action = $args['action'];
        $name   = $args['name'];

        if ( $action === 'create_class' ) {
            $slug = $args['slug'] ?? sanitize_title($name);
            $term = wp_insert_term($name, 'product_shipping_class', ['slug' => $slug]);

            if ( is_wp_error($term) ) {
                throw ToolException::fromWpError(self::TOOL_NAME, $term);
            }

            return ToolResult::json([
                'success'    => true,
                'class_id'   => (int) $term['term_id'],
                'name'       => $name,
                'slug'       => $slug,
                'message'    => "WooCommerce Shipping Class '{$name}' successfully created.",
            ]);
        }

        // Create Zone.
        if ( class_exists('\WC_Shipping_Zones') ) {
            $zone = new \WC_Shipping_Zone();
            $zone->set_zone_name(sanitize_text_field($name));
            $zone->save();

            return ToolResult::json([
                'success' => true,
                'zone_id' => $zone->get_id(),
                'name'    => $name,
                'message' => "WooCommerce Shipping Zone '{$name}' successfully created.",
            ]);
        }

        throw new ToolException('Shipping Zone API not available.', self::TOOL_NAME);
    }
}
