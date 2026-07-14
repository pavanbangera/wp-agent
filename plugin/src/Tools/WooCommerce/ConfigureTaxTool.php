<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.woo.tax.configure
 *
 * Configures WooCommerce tax rates.
 *
 * Required scope: wp-agent:admin
 * Required capability: manage_woocommerce
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class ConfigureTaxTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.tax.configure';
    }

    public function getDescription(): string
    {
        return 'Adds or configures a tax rule rate in WooCommerce.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'country'  => [
                    'type'        => 'string',
                    'description' => '2-letter country code (e.g. "US").',
                    'maxLength'   => 2,
                    'default'     => '',
                ],
                'state'    => [
                    'type'        => 'string',
                    'description' => '2-letter state code (e.g. "CA").',
                    'maxLength'   => 2,
                    'default'     => '',
                ],
                'rate'     => [
                    'type'        => 'number',
                    'description' => 'Tax percentage rate (e.g. 7.25).',
                    'minimum'     => 0,
                ],
                'name'     => [
                    'type'        => 'string',
                    'description' => 'Display name for the tax rate (e.g. "State Tax").',
                    'minLength'   => 1,
                ],
                'priority' => [
                    'type'        => 'integer',
                    'description' => 'Tax rate priority (lower priority numbers apply first).',
                    'default'     => 1,
                ],
                'class'    => [
                    'type'        => 'string',
                    'description' => 'Tax class slug. Leave empty for standard rate class.',
                    'default'     => '',
                ],
            ],
            'required'             => ['rate', 'name'],
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

        $rateId = $this->woocommerceService->configureTax($args);

        return ToolResult::json([
            'success' => true,
            'rate_id' => $rateId,
            'name'    => $args['name'],
            'rate'    => (float) $args['rate'],
            'message' => "WooCommerce Tax rate rule '{$args['name']}' successfully configured with ID #{$rateId}.",
        ]);
    }
}
