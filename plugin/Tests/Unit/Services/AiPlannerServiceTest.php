<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Services\AiPlannerService;

/**
 * Tests for AiPlannerService.
 *
 * @covers \WpAgent\Services\AiPlannerService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class AiPlannerServiceTest extends TestCase
{
    private AiPlannerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new AiPlannerService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // planGoal()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_creates_a_valid_multistep_woocommerce_plan(): void
    {
        $plan = $this->service->planGoal('Build a WooCommerce store with a landing page');

        self::assertSame('build a woocommerce store with a landing page', $plan['goal']);
        self::assertGreaterThan(0, $plan['steps_count']);
        self::assertSame('wordpress.plugins.install', $plan['recommended_plan'][0]['tool']);
    }
}
