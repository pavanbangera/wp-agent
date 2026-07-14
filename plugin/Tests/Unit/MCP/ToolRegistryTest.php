<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\MCP;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\McpException;
use WpAgent\MCP\Contracts\ToolInterface;
use WpAgent\MCP\Registry\ToolRegistry;

/**
 * Tests for the ToolRegistry.
 *
 * @covers \WpAgent\MCP\Registry\ToolRegistry
 *
 * @package WpAgent\Tests\Unit\MCP
 */
final class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();
        $this->registry = new ToolRegistry();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_registers_a_tool(): void
    {
        $tool = $this->makeTool('wordpress.site.info');

        \WP_Mock::expectAction('wpa_tool_registered', $tool);

        $this->registry->register($tool);

        self::assertTrue($this->registry->has('wordpress.site.info'));
    }

    /** @test */
    public function it_throws_when_registering_duplicate_tool(): void
    {
        $tool = $this->makeTool('wordpress.site.info');

        \WP_Mock::expectAction('wpa_tool_registered', $tool);

        $this->registry->register($tool);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('wordpress.site.info');

        // Second registration with same name.
        $duplicate = $this->makeTool('wordpress.site.info');
        $this->registry->register($duplicate);
    }

    // -------------------------------------------------------------------------
    // resolve()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_resolves_a_registered_tool(): void
    {
        $tool = $this->makeTool('wordpress.pages.create');
        \WP_Mock::expectAction('wpa_tool_registered', $tool);

        $this->registry->register($tool);

        $resolved = $this->registry->resolve('wordpress.pages.create');

        self::assertSame($tool, $resolved);
    }

    /** @test */
    public function it_throws_mcp_exception_for_unknown_tool(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionCode(McpException::TOOL_NOT_FOUND);

        $this->registry->resolve('wordpress.fake.tool');
    }

    // -------------------------------------------------------------------------
    // all()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_all_tools(): void
    {
        $tools = [
            $this->makeTool('wordpress.site.info'),
            $this->makeTool('wordpress.pages.create'),
            $this->makeTool('wordpress.posts.list'),
        ];

        foreach ( $tools as $tool ) {
            \WP_Mock::expectAction('wpa_tool_registered', $tool);
            $this->registry->register($tool);
        }

        $all = $this->registry->all();

        self::assertCount(3, $all);
    }

    /** @test */
    public function it_filters_tools_by_namespace(): void
    {
        $tools = [
            $this->makeTool('wordpress.site.info'),
            $this->makeTool('wordpress.pages.create'),
            $this->makeTool('wordpress.pages.delete'),
        ];

        foreach ( $tools as $tool ) {
            \WP_Mock::expectAction('wpa_tool_registered', $tool);
            $this->registry->register($tool);
        }

        $pages = $this->registry->all('wordpress.pages');

        self::assertCount(2, $pages);
    }

    // -------------------------------------------------------------------------
    // count()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_correct_count(): void
    {
        self::assertSame(0, $this->registry->count());

        $tool = $this->makeTool('wordpress.site.info');
        \WP_Mock::expectAction('wpa_tool_registered', $tool);
        $this->registry->register($tool);

        self::assertSame(1, $this->registry->count());
    }

    // -------------------------------------------------------------------------
    // toManifest()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_generates_valid_mcp_manifest(): void
    {
        $tool = $this->makeTool('wordpress.site.info');
        \WP_Mock::expectAction('wpa_tool_registered', $tool);
        $this->registry->register($tool);

        \WP_Mock::expectFilter('wpa_tools_manifest', \WP_Mock\Functions::type('array'));

        $manifest = $this->registry->toManifest();

        self::assertArrayHasKey('tools', $manifest);
        self::assertCount(1, $manifest['tools']);

        $toolDef = $manifest['tools'][0];
        self::assertSame('wordpress.site.info', $toolDef['name']);
        self::assertArrayHasKey('description', $toolDef);
        self::assertArrayHasKey('inputSchema', $toolDef);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTool(string $name): ToolInterface
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn($name);
        $tool->method('getDescription')->willReturn("Description for {$name}");
        $tool->method('getInputSchema')->willReturn(['type' => 'object', 'properties' => []]);
        $tool->method('getAnnotations')->willReturn([]);

        return $tool;
    }
}
