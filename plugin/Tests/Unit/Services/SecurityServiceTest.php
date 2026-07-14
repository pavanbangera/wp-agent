<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Services\SecurityService;

/**
 * Tests for SecurityService.
 *
 * @covers \WpAgent\Services\SecurityService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class SecurityServiceTest extends TestCase
{
    private SecurityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new SecurityService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // checkHeaders()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_checks_all_defined_security_headers(): void
    {
        \WP_Mock::userFunction('headers_sent')->andReturn(true);

        $results = $this->service->checkHeaders();

        self::assertArrayHasKey('Content-Security-Policy', $results);
        self::assertArrayHasKey('X-Frame-Options', $results);
        self::assertSame('missing', $results['X-Frame-Options']['status']);
    }
}
