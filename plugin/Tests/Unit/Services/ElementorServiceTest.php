<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\ElementorService;

/**
 * Tests for ElementorService.
 *
 * @covers \WpAgent\Services\ElementorService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class ElementorServiceTest extends TestCase
{
    private ElementorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new ElementorService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // requireElementor()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_if_elementor_is_not_active(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Elementor plugin is not active');

        $this->service->requireElementor();
    }

    // -------------------------------------------------------------------------
    // createPageLayout()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_updates_elementor_data_meta_successfully(): void
    {
        if ( ! defined('ELEMENTOR_VERSION') ) {
            define('ELEMENTOR_VERSION', '3.0.0');
        }

        $page = new \WP_Post((object) ['ID' => 50, 'post_type' => 'page']);

        \WP_Mock::userFunction('get_post')->with(50)->andReturn($page);

        \WP_Mock::userFunction('update_post_meta')
            ->with(50, '_elementor_data', \WP_Mock\Functions::type('string'))
            ->once();

        \WP_Mock::userFunction('update_post_meta')
            ->with(50, '_elementor_edit_mode', 'builder')
            ->once();

        \WP_Mock::userFunction('update_post_meta')
            ->with(50, '_elementor_template_type', 'wp-page')
            ->once();

        \WP_Mock::expectAction('wpa_elementor_page_layout_created', 50, []);

        $result = $this->service->createPageLayout(50, []);

        self::assertTrue($result);
    }
}
