<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Services\PerformanceService;

/**
 * Tests for PerformanceService.
 *
 * @covers \WpAgent\Services\PerformanceService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class PerformanceServiceTest extends TestCase
{
    private PerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new PerformanceService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // clearCache()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_flushes_caches_and_returns_status(): void
    {
        \WP_Mock::userFunction('wp_cache_flush')
            ->once()
            ->andReturn(true);

        $status = $this->service->clearCache();

        self::assertArrayHasKey('object_cache', $status);
        self::assertTrue($status['object_cache']);
    }
}
