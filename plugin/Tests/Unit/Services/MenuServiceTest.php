<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\MenuService;

/**
 * Tests for MenuService.
 *
 * @covers \WpAgent\Services\MenuService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class MenuServiceTest extends TestCase
{
    private MenuService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new MenuService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_creates_a_menu_successfully(): void
    {
        $menuTerm = $this->makeMenuTerm(12, 'Primary Menu', 'primary-menu');

        \WP_Mock::userFunction('wp_create_nav_menu')
            ->with('Primary Menu')
            ->andReturn(12);

        \WP_Mock::userFunction('wp_get_nav_menu_object')
            ->with(12)
            ->andReturn($menuTerm);

        \WP_Mock::expectAction('wpa_menu_created', $menuTerm);

        $result = $this->service->create('Primary Menu');

        self::assertSame(12, $result->term_id);
        self::assertSame('Primary Menu', $result->name);
    }

    /** @test */
    public function it_throws_when_menu_creation_fails(): void
    {
        $wpError = new \WP_Error('menu_exists', 'Menu already exists.');

        \WP_Mock::userFunction('wp_create_nav_menu')
            ->with('Existing Menu')
            ->andReturn($wpError);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Menu already exists.');

        $this->service->create('Existing Menu');
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_retrieving_nonexistent_menu(): void
    {
        \WP_Mock::userFunction('wp_get_nav_menu_object')
            ->with('Fake Menu')
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionCode(ToolException::RESOURCE_NOT_FOUND);

        $this->service->get('Fake Menu');
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_deletes_a_menu_successfully(): void
    {
        $menuTerm = $this->makeMenuTerm(15, 'Footer Menu', 'footer-menu');

        \WP_Mock::userFunction('wp_get_nav_menu_object')
            ->with(15)
            ->andReturn($menuTerm);

        \WP_Mock::userFunction('wp_delete_nav_menu')
            ->with(15)
            ->andReturn(true);

        \WP_Mock::expectAction('wpa_menu_deleted', 15);

        $result = $this->service->delete(15);

        self::assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMenuTerm(int $id, string $name, string $slug): \WP_Term
    {
        $term           = new \WP_Term((object) []);
        $term->term_id  = $id;
        $term->name     = $name;
        $term->slug     = $slug;
        $term->taxonomy = 'nav_menu';
        $term->count    = 0;

        return $term;
    }
}
