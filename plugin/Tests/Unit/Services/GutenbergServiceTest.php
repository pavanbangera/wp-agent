<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\GutenbergService;

/**
 * Tests for GutenbergService.
 *
 * @covers \WpAgent\Services\GutenbergService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class GutenbergServiceTest extends TestCase
{
    private GutenbergService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new GutenbergService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // generateBlockMarkup()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_generates_block_without_attributes(): void
    {
        $markup = $this->service->generateBlockMarkup('core/paragraph', [], '<p>hello</p>');

        self::assertSame("<!-- wp:core/paragraph -->\n<p>hello</p>\n<!-- /wp:core/paragraph -->\n", $markup);
    }

    /** @test */
    public function it_generates_block_with_attributes(): void
    {
        \WP_Mock::userFunction('wp_json_encode')
            ->with(['align' => 'center'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->andReturn('{"align":"center"}');

        $markup = $this->service->generateBlockMarkup('core/paragraph', ['align' => 'center'], '<p>hello</p>');

        self::assertSame("<!-- wp:core/paragraph {\"align\":\"center\"} -->\n<p>hello</p>\n<!-- /wp:core/paragraph -->\n", $markup);
    }

    /** @test */
    public function it_generates_self_closing_block(): void
    {
        \WP_Mock::userFunction('wp_json_encode')
            ->with(['id' => 9], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->andReturn('{"id":9}');

        $markup = $this->service->generateBlockMarkup('core/image', ['id' => 9]);

        self::assertSame("<!-- wp:core/image {\"id\":9} /-->\n", $markup);
    }

    // -------------------------------------------------------------------------
    // createTemplate()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_on_invalid_template_cpt_type(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Invalid template CPT type');

        $this->service->createTemplate('slug', 'invalid_cpt', 'content', 'Title');
    }
}
