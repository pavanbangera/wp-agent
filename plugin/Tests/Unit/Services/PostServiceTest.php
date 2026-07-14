<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Repositories\Contracts\PostRepositoryInterface;
use WpAgent\Services\PostService;

/**
 * Tests for PostService.
 *
 * @covers \WpAgent\Services\PostService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class PostServiceTest extends TestCase
{
    private PostRepositoryInterface $repository;
    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->repository = $this->createMock(PostRepositoryInterface::class);
        $this->service    = new PostService($this->repository);
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
    public function it_creates_a_post_with_default_draft_status(): void
    {
        $post = $this->makePost(1, 'Hello World', 'draft');

        \WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        \WP_Mock::expectAction('wpa_post_created', $post, \WP_Mock\Functions::type('array'));
        \WP_Mock::userFunction('defined')->andReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (array $d): bool =>
                $d['post_type'] === 'post' && $d['post_status'] === 'draft'
            ))
            ->willReturn($post);

        $result = $this->service->create(['title' => 'Hello World']);

        self::assertSame(1, $result->ID);
    }

    /** @test */
    public function it_sets_categories_on_create(): void
    {
        $post = $this->makePost(1, 'Post', 'draft');

        \WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        \WP_Mock::expectAction('wpa_post_created', $post, \WP_Mock\Functions::type('array'));
        \WP_Mock::userFunction('defined')->andReturn(false);
        \WP_Mock::userFunction('wp_set_post_categories')
            ->with(1, [3, 5])
            ->once();

        $this->repository->method('insert')->willReturn($post);

        $this->service->create(['title' => 'Post', 'categories' => [3, 5]]);
    }

    // -------------------------------------------------------------------------
    // setFeaturedImage()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_sets_featured_image(): void
    {
        $post = $this->makePost(10, 'Post', 'publish');

        $this->repository->method('findOrFail')->willReturn($post);

        \WP_Mock::userFunction('wp_attachment_is_image')
            ->with(99)
            ->andReturn(true);
        \WP_Mock::userFunction('set_post_thumbnail')
            ->with(10, 99)
            ->andReturn(true);

        $result = $this->service->setFeaturedImage(10, 99);

        self::assertTrue($result);
    }

    /** @test */
    public function it_removes_featured_image_when_attachment_id_is_zero(): void
    {
        $post = $this->makePost(10, 'Post', 'publish');

        $this->repository->method('findOrFail')->willReturn($post);

        \WP_Mock::userFunction('delete_post_thumbnail')
            ->with(10)
            ->andReturn(true);

        $result = $this->service->setFeaturedImage(10, 0);

        self::assertTrue($result);
    }

    /** @test */
    public function it_throws_when_attachment_is_not_an_image(): void
    {
        $post = $this->makePost(10, 'Post', 'publish');

        $this->repository->method('findOrFail')->willReturn($post);

        \WP_Mock::userFunction('wp_attachment_is_image')->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('not a valid image');

        $this->service->setFeaturedImage(10, 999);
    }

    // -------------------------------------------------------------------------
    // manageCategories()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_sets_categories_replacing_existing(): void
    {
        $post = $this->makePost(10, 'Post', 'publish');

        $this->repository->method('findOrFail')->willReturn($post);

        \WP_Mock::userFunction('wp_set_post_categories')->with(10, [1, 2])->once();
        \WP_Mock::userFunction('wp_get_post_categories')->andReturn([1, 2]);

        $result = $this->service->manageCategories(10, [1, 2], 'set');

        self::assertSame([1, 2], $result);
    }

    /** @test */
    public function it_adds_categories_without_removing_existing(): void
    {
        $post = $this->makePost(10, 'Post', 'publish');

        $this->repository->method('findOrFail')->willReturn($post);

        \WP_Mock::userFunction('wp_get_post_categories')
            ->andReturn([1], [1, 3]); // First call = current, second = final.

        \WP_Mock::userFunction('wp_set_post_categories')
            ->with(10, [1, 3])
            ->once();

        $this->service->manageCategories(10, [3], 'add');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePost(int $id, string $title, string $status): \WP_Post
    {
        $post                   = new \WP_Post((object) []);
        $post->ID               = $id;
        $post->post_title       = $title;
        $post->post_status      = $status;
        $post->post_type        = 'post';
        $post->post_content     = '';
        $post->post_excerpt     = '';
        $post->post_name        = sanitize_title($title);
        $post->post_author      = 1;
        $post->post_parent      = 0;
        $post->menu_order       = 0;
        $post->post_date        = '2025-01-01 12:00:00';
        $post->post_modified    = '2025-01-01 12:00:00';
        $post->post_date_gmt    = '2025-01-01 12:00:00';
        $post->post_modified_gmt = '2025-01-01 12:00:00';
        $post->comment_status   = 'open';

        return $post;
    }
}
