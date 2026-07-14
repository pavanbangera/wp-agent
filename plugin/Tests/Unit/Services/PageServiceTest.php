<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Repositories\Contracts\PostRepositoryInterface;
use WpAgent\Services\PageService;

/**
 * Tests for PageService.
 *
 * @covers \WpAgent\Services\PageService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class PageServiceTest extends TestCase
{
    private PostRepositoryInterface $repository;
    private PageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->repository = $this->createMock(PostRepositoryInterface::class);
        $this->service    = new PageService($this->repository);
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
    public function it_creates_a_page_with_default_draft_status(): void
    {
        $expectedPage = $this->makePage(123, 'My Page', 'draft');

        \WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        \WP_Mock::expectAction('wpa_page_created', $expectedPage, \WP_Mock\Functions::type('array'));
        \WP_Mock::userFunction('update_post_meta');
        \WP_Mock::userFunction('defined')->andReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (array $data): bool {
                return $data['post_type'] === 'page'
                    && $data['post_status'] === 'draft'
                    && $data['post_title'] === 'My Page';
            }))
            ->willReturn($expectedPage);

        $result = $this->service->create(['title' => 'My Page']);

        self::assertSame(123, $result->ID);
        self::assertSame('draft', $result->post_status);
    }

    /** @test */
    public function it_creates_a_page_with_published_status(): void
    {
        $expectedPage = $this->makePage(124, 'Published Page', 'publish');

        \WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        \WP_Mock::expectAction('wpa_page_created', $expectedPage, \WP_Mock\Functions::type('array'));
        \WP_Mock::userFunction('defined')->andReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (array $d): bool => $d['post_status'] === 'publish'))
            ->willReturn($expectedPage);

        $result = $this->service->create(['title' => 'Published Page', 'status' => 'publish']);

        self::assertSame('publish', $result->post_status);
    }

    // -------------------------------------------------------------------------
    // duplicate()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_duplicates_a_page_as_draft(): void
    {
        $original  = $this->makePage(10, 'Original Page', 'publish');
        $duplicate = $this->makePage(11, 'Original Page (Copy)', 'draft');

        $this->repository->method('findOrFail')->willReturn($original);

        \WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        \WP_Mock::userFunction('get_post_meta')->andReturn([]);
        \WP_Mock::userFunction('get_object_taxonomies')->andReturn([]);
        \WP_Mock::expectAction('wpa_page_duplicated', $duplicate, $original);

        $this->repository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (array $d): bool =>
                $d['post_title'] === 'Original Page (Copy)'
                && $d['post_status'] === 'draft'
            ))
            ->willReturn($duplicate);

        $result = $this->service->duplicate(10);

        self::assertSame(11, $result->ID);
        self::assertSame('draft', $result->post_status);
    }

    /** @test */
    public function it_uses_custom_title_suffix_when_duplicating(): void
    {
        $original  = $this->makePage(10, 'Page A', 'publish');
        $duplicate = $this->makePage(11, 'Page A — Clone', 'draft');

        $this->repository->method('findOrFail')->willReturn($original);

        \WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        \WP_Mock::userFunction('get_post_meta')->andReturn([]);
        \WP_Mock::userFunction('get_object_taxonomies')->andReturn([]);
        \WP_Mock::expectAction('wpa_page_duplicated', $duplicate, $original);

        $this->repository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(
                static fn (array $d): bool => $d['post_title'] === 'Page A — Clone'
            ))
            ->willReturn($duplicate);

        $this->service->duplicate(10, ' — Clone');
    }

    // -------------------------------------------------------------------------
    // schedule()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_on_past_schedule_date(): void
    {
        $page = $this->makePage(10, 'Page', 'draft');
        $this->repository->method('findOrFail')->willReturn($page);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('must be in the future');

        $this->service->schedule(10, '2000-01-01T00:00:00');
    }

    /** @test */
    public function it_throws_on_invalid_date_format(): void
    {
        $page = $this->makePage(10, 'Page', 'draft');
        $this->repository->method('findOrFail')->willReturn($page);

        $this->expectException(ToolException::class);

        $this->service->schedule(10, 'not-a-date');
    }

    /** @test */
    public function it_schedules_a_page_for_future_date(): void
    {
        $page      = $this->makePage(10, 'Page', 'draft');
        $scheduled = $this->makePage(10, 'Page', 'future');

        $this->repository->method('findOrFail')->willReturn($page);

        $futureDate = date('Y-m-d\TH:i:s', strtotime('+1 month'));

        \WP_Mock::expectAction('wpa_page_scheduled', $scheduled, $futureDate);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(10, $this->callback(
                static fn (array $d): bool => $d['post_status'] === 'future'
            ))
            ->willReturn($scheduled);

        $result = $this->service->schedule(10, $futureDate);

        self::assertSame('future', $result->post_status);
    }

    // -------------------------------------------------------------------------
    // publish()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_publishes_a_draft_page(): void
    {
        $draft     = $this->makePage(5, 'Draft Page', 'draft');
        $published = $this->makePage(5, 'Draft Page', 'publish');

        $this->repository->method('findOrFail')->willReturn($draft);

        \WP_Mock::userFunction('current_time')
            ->with('mysql')
            ->andReturn(current_time('mysql'));
        \WP_Mock::userFunction('current_time')
            ->with('mysql', true)
            ->andReturn(current_time('mysql', true));

        \WP_Mock::expectAction('wpa_page_published', $published);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(5, $this->callback(
                static fn (array $d): bool => $d['post_status'] === 'publish'
            ))
            ->willReturn($published);

        $result = $this->service->publish(5);

        self::assertSame('publish', $result->post_status);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePage(int $id, string $title, string $status): \WP_Post
    {
        $post               = new \WP_Post((object) []);
        $post->ID           = $id;
        $post->post_title   = $title;
        $post->post_status  = $status;
        $post->post_type    = 'page';
        $post->post_content = '';
        $post->post_excerpt = '';
        $post->post_name    = sanitize_title($title);
        $post->post_author  = 1;
        $post->post_parent  = 0;
        $post->menu_order   = 0;
        $post->post_date    = current_time('mysql');
        $post->post_modified = current_time('mysql');
        $post->post_date_gmt = current_time('mysql', true);
        $post->post_modified_gmt = current_time('mysql', true);
        $post->comment_status = 'closed';

        return $post;
    }
}
