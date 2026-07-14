<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\CodeExecutionService;

/**
 * Tests for CodeExecutionService.
 *
 * @covers \WpAgent\Services\CodeExecutionService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class CodeExecutionServiceTest extends TestCase
{
    private CodeExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new CodeExecutionService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // executePhp()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_if_blacklist_function_matched(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("is blacklisted");

        $this->service->executePhp('shell_exec("ls");');
    }

    /** @test */
    public function it_captures_outputs_and_returns_values_successfully(): void
    {
        $result = $this->service->executePhp('echo "hello output"; return 42;');

        self::assertSame('hello output', $result['output']);
        self::assertSame(42, $result['returned']);
    }
}
