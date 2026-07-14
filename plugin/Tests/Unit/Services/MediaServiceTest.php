<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Repositories\MediaRepository;
use WpAgent\Services\MediaService;

/**
 * Tests for MediaService.
 *
 * @covers \WpAgent\Services\MediaService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class MediaServiceTest extends TestCase
{
    private MediaRepository $repository;
    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->repository = $this->createMock(MediaRepository::class);
        $this->service    = new MediaService($this->repository);
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // uploadFromBase64()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_on_invalid_base64_upload(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Invalid base64 payload');

        $this->service->uploadFromBase64('test.png', 'invalid-base64-content!!!');
    }

    // -------------------------------------------------------------------------
    // compressImage()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_if_physical_image_file_not_found(): void
    {
        $attachment = $this->makeAttachment(10, 'image.jpg');
        $this->repository->method('findOrFail')->willReturn($attachment);

        \WP_Mock::userFunction('get_attached_file')
            ->with(10)
            ->andReturn('/tmp/nonexistent-file.jpg');

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Image file not found');

        $this->service->compressImage(10, 82);
    }

    // -------------------------------------------------------------------------
    // detectDuplicates()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_detects_duplicate_attachments(): void
    {
        global $wpdb;
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->postmeta = 'wp_postmeta';

        // Mock database results.
        $dbResults = [
            (object) ['post_id' => '1', 'meta_value' => 'abcde12345'],
            (object) ['post_id' => '2', 'meta_value' => 'abcde12345'],
            (object) ['post_id' => '3', 'meta_value' => 'other7777'],
        ];

        $wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn($dbResults);

        $duplicates = $this->service->detectDuplicates();

        self::assertCount(1, $duplicates);
        self::assertSame([1, 2], $duplicates['abcde12345']);
        self::assertArrayNotHasKey('other7777', $duplicates);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAttachment(int $id, string $title): \WP_Post
    {
        $post                  = new \WP_Post((object) []);
        $post->ID              = $id;
        $post->post_title      = $title;
        $post->post_status     = 'inherit';
        $post->post_type       = 'attachment';
        $post->post_mime_type  = 'image/jpeg';
        $post->guid            = "https://example.com/wp-content/uploads/{$title}";
        $post->post_parent     = 0;
        $post->post_date       = '2025-01-01 12:00:00';

        return $post;
    }
}
