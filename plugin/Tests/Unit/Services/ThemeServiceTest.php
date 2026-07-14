<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\ThemeService;

/**
 * Tests for ThemeService.
 *
 * @covers \WpAgent\Services\ThemeService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class ThemeServiceTest extends TestCase
{
    private ThemeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new ThemeService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // search()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_querying_wp_org_themes_api_fails(): void
    {
        $wpError = new \WP_Error('api_error', 'API is down.');

        \WP_Mock::userFunction('themes_api')
            ->with('query_themes', \WP_Mock\Functions::type('array'))
            ->andReturn($wpError);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('API is down.');

        $this->service->search('astra');
    }

    // -------------------------------------------------------------------------
    // createChildTheme()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_parent_theme_not_installed(): void
    {
        $parentThemeMock = $this->createMock(\WP_Theme::class);
        $parentThemeMock->method('exists')->willReturn(false);

        \WP_Mock::userFunction('wp_get_theme')
            ->with('nonexistent-parent')
            ->andReturn($parentThemeMock);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Parent theme 'nonexistent-parent' is not installed");

        $this->service->createChildTheme('nonexistent-parent', 'child', 'Child Theme');
    }
}
