<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsWoo;

/**
 * Tool: wordpress.woo.coupon.create
 *
 * Creates a discount coupon.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_shop_coupons (or standard admin capability)
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class CreateCouponTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.coupon.create';
    }

    public function getDescription(): string
    {
        return 'Creates a WooCommerce discount coupon code.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'code'          => [
                    'type'        => 'string',
                    'description' => 'The coupon code (e.g. "SUMMER50").',
                    'minLength'   => 1,
                ],
                'amount'        => [
                    'type'        => 'number',
                    'description' => 'Coupon amount/percentage.',
                    'minimum'     => 0,
                ],
                'discount_type' => [
                    'type'        => 'string',
                    'enum'        => ['fixed_cart', 'percent', 'fixed_product'],
                    'default'     => 'fixed_cart',
                ],
                'expiry_date'   => [
                    'type'        => 'string',
                    'description' => 'Expiration date (YYYY-MM-DD).',
                ],
                'usage_limit'   => [
                    'type'        => 'integer',
                    'description' => 'Usage limit per coupon.',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['code', 'amount'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);

        $coupon = $this->woocommerceService->createCoupon($args);

        return ToolResult::json(FormatsWoo::formatCoupon($coupon));
    }
}
