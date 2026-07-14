<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\WooCommerceService;

/**
 * Tests for WooCommerceService.
 *
 * @covers \WpAgent\Services\WooCommerceService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class WooCommerceServiceTest extends TestCase
{
    private WooCommerceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new WooCommerceService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // requireWooCommerce()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_if_woocommerce_not_active(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('WooCommerce plugin is not active');

        $this->service->requireWooCommerce();
    }

    // -------------------------------------------------------------------------
    // manageInventory()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_updating_nonexistent_product_inventory(): void
    {
        if ( ! class_exists('WooCommerce') ) {
            // Define dummy WooCommerce class to bypass requireWooCommerce check in test.
            eval('class WooCommerce {}');
        }

        \WP_Mock::userFunction('wc_get_product')->with(999)->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionCode(ToolException::RESOURCE_NOT_FOUND);

        $this->service->manageInventory(999, 10);
    }
}
