<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\SeoService;

/**
 * Tests for SeoService.
 *
 * @covers \WpAgent\Services\SeoService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class SeoServiceTest extends TestCase
{
    private SeoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new SeoService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // setOgMeta()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_setting_og_for_nonexistent_post(): void
    {
        \WP_Mock::userFunction('get_post')->with(999)->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionCode(ToolException::RESOURCE_NOT_FOUND);

        $this->service->setOgMeta(999, ['title' => 'og title']);
    }

    /** @test */
    public function it_sets_og_metadata_successfully(): void
    {
        $post = new \WP_Post((object) ['ID' => 10, 'post_type' => 'post']);
        \WP_Mock::userFunction('get_post')->with(10)->andReturn($post);

        \WP_Mock::userFunction('update_post_meta')
            ->with(10, '_wpa_og_title', 'My title')
            ->once();

        \WP_Mock::userFunction('update_post_meta')
            ->with(10, '_wpa_og_description', 'My description')
            ->once();

        $result = $this->service->setOgMeta(10, [
            'title'       => 'My title',
            'description' => 'My description',
        ]);

        self::assertTrue($result);
    }
}
