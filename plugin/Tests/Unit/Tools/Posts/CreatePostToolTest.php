<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Tools\Posts;

use PHPUnit\Framework\TestCase;
use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ValidationException;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\Posts\CreatePostTool;

/**
 * Tests for CreatePostTool.
 *
 * @covers \WpAgent\Tools\Posts\CreatePostTool
 *
 * @package WpAgent\Tests\Unit\Tools\Posts
 */
final class CreatePostToolTest extends TestCase
{
    private PostServiceInterface $postService;
    private CreatePostTool $tool;
    private Identity $identity;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->postService = $this->createMock(PostServiceInterface::class);
        $this->tool        = new CreatePostTool($this->postService);

        $this->identity = $this->createMock(Identity::class);
        $this->identity->method('can')->willReturn(true);
        $this->identity->method('hasScope')->willReturn(true);
        $this->identity->method('hasAllScopes')->willReturn(true);
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    /** @test */
    public function it_has_correct_name(): void
    {
        self::assertSame('wordpress.posts.create', $this->tool->getName());
    }

    /** @test */
    public function it_requires_write_scope(): void
    {
        self::assertContains('wp-agent:write', $this->tool->getRequiredScopes());
    }

    /** @test */
    public function it_throws_when_title_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->tool->execute([], $this->identity);
    }

    /** @test */
    public function it_throws_on_invalid_post_format(): void
    {
        $this->expectException(ValidationException::class);

        $this->tool->execute([
            'title'  => 'Post',
            'format' => 'invalid-format',
        ], $this->identity);
    }

    /** @test */
    public function it_creates_post_with_required_fields_only(): void
    {
        $post = $this->makeWpPost(42, 'Hello World', 'draft');

        $this->postService
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (array $d): bool => $d['title'] === 'Hello World'))
            ->willReturn($post);

        $this->mockPostFormatFunctions($post);

        $result = $this->tool->execute(['title' => 'Hello World'], $this->identity);

        self::assertFalse($result->isError());
        $json = json_decode($result->getContent()[0]->toArray()['text'], true);
        self::assertSame(42, $json['id']);
    }

    /** @test */
    public function it_creates_post_with_categories_and_tags(): void
    {
        $post = $this->makeWpPost(50, 'Tagged Post', 'publish');

        $this->postService
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (array $d): bool =>
                $d['categories'] === [1, 2]
                && $d['tags']       === ['php', 'wordpress']
            ))
            ->willReturn($post);

        $this->mockPostFormatFunctions($post);

        $this->tool->execute([
            'title'      => 'Tagged Post',
            'status'     => 'publish',
            'categories' => [1, 2],
            'tags'       => ['php', 'wordpress'],
        ], $this->identity);
    }

    /** @test */
    public function it_checks_edit_posts_capability(): void
    {
        $this->identity
            ->expects($this->once())
            ->method('can')
            ->with('edit_posts')
            ->willReturn(false);

        $this->expectException(\WpAgent\Exceptions\ToolException::class);

        $this->tool->execute(['title' => 'Test'], $this->identity);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeWpPost(int $id, string $title, string $status): \WP_Post
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

    private function mockPostFormatFunctions(\WP_Post $post): void
    {
        \WP_Mock::userFunction('get_permalink')->andReturn("https://example.com/{$post->post_name}");
        \WP_Mock::userFunction('get_userdata')->andReturn(false);
        \WP_Mock::userFunction('get_post_meta')->andReturn('');
        \WP_Mock::userFunction('get_post_thumbnail_id')->andReturn(0);
        \WP_Mock::userFunction('wp_get_post_categories')->andReturn([]);
        \WP_Mock::userFunction('wp_get_post_tags')->andReturn([]);
        \WP_Mock::userFunction('get_post_format')->andReturn('standard');
    }
}
