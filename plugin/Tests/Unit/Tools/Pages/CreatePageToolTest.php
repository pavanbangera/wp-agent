<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Tools\Pages;

use PHPUnit\Framework\TestCase;
use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ValidationException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\Pages\CreatePageTool;

/**
 * Tests for CreatePageTool.
 *
 * @covers \WpAgent\Tools\Pages\CreatePageTool
 * @covers \WpAgent\Tools\AbstractTool
 *
 * @package WpAgent\Tests\Unit\Tools\Pages
 */
final class CreatePageToolTest extends TestCase
{
    private PageServiceInterface $pageService;
    private CreatePageTool $tool;
    private Identity $identity;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->pageService = $this->createMock(PageServiceInterface::class);
        $this->tool        = new CreatePageTool($this->pageService);

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

    // -------------------------------------------------------------------------
    // Metadata
    // -------------------------------------------------------------------------

    /** @test */
    public function it_has_correct_name(): void
    {
        self::assertSame('wordpress.pages.create', $this->tool->getName());
    }

    /** @test */
    public function it_requires_write_scope(): void
    {
        self::assertContains('wp-agent:write', $this->tool->getRequiredScopes());
    }

    /** @test */
    public function it_is_not_read_only(): void
    {
        self::assertFalse($this->tool->getAnnotations()['readOnlyHint'] ?? true);
    }

    // -------------------------------------------------------------------------
    // Input schema validation
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_validation_error_when_title_is_missing(): void
    {
        $this->expectException(ValidationException::class);

        $this->tool->execute([], $this->identity);
    }

    /** @test */
    public function it_throws_validation_error_when_title_is_empty(): void
    {
        $this->expectException(ValidationException::class);

        $this->tool->execute(['title' => ''], $this->identity);
    }

    /** @test */
    public function it_throws_validation_error_for_invalid_status(): void
    {
        $this->expectException(ValidationException::class);

        $this->tool->execute(['title' => 'Test', 'status' => 'invalid_status'], $this->identity);
    }

    // -------------------------------------------------------------------------
    // Successful execution
    // -------------------------------------------------------------------------

    /** @test */
    public function it_creates_page_and_returns_json_result(): void
    {
        $page = $this->makeWpPost(99, 'My Page', 'draft');

        $this->pageService
            ->expects($this->once())
            ->method('create')
            ->with(['title' => 'My Page', 'status' => 'draft'])
            ->willReturn($page);

        $this->mockPageFormatFunctions($page);

        $result = $this->tool->execute(
            ['title' => 'My Page', 'status' => 'draft'],
            $this->identity
        );

        self::assertFalse($result->isError());
        self::assertCount(1, $result->getContent());

        $json = json_decode($result->getContent()[0]->toArray()['text'], true);
        self::assertSame(99, $json['id']);
        self::assertSame('My Page', $json['title']);
    }

    /** @test */
    public function it_passes_all_fields_to_service(): void
    {
        $page = $this->makeWpPost(100, 'SEO Page', 'publish');

        $expectedArgs = [
            'title'           => 'SEO Page',
            'status'          => 'publish',
            'seo_title'       => 'Custom SEO Title',
            'seo_description' => 'Meta description',
            'slug'            => 'seo-page',
            'parent_id'       => 0,
        ];

        $this->pageService
            ->expects($this->once())
            ->method('create')
            ->with($expectedArgs)
            ->willReturn($page);

        $this->mockPageFormatFunctions($page);

        $this->tool->execute($expectedArgs, $this->identity);
    }

    // -------------------------------------------------------------------------
    // Capability check
    // -------------------------------------------------------------------------

    /** @test */
    public function it_checks_edit_pages_capability(): void
    {
        $this->identity
            ->expects($this->once())
            ->method('can')
            ->with('edit_pages')
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
        $post->post_type        = 'page';
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
        $post->comment_status   = 'closed';

        return $post;
    }

    private function mockPageFormatFunctions(\WP_Post $page): void
    {
        \WP_Mock::userFunction('get_permalink')->andReturn("https://example.com/{$page->post_name}");
        \WP_Mock::userFunction('get_userdata')->andReturn(false);
        \WP_Mock::userFunction('get_post_meta')->andReturn('');
        \WP_Mock::userFunction('get_post_thumbnail_id')->andReturn(0);
        \WP_Mock::userFunction('get_edit_post_link')->andReturn('');
    }
}
