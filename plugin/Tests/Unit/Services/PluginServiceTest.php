<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\ToolException;
use WpAgent\Services\PluginService;

/**
 * Tests for PluginService.
 *
 * @covers \WpAgent\Services\PluginService
 *
 * @package WpAgent\Tests\Unit\Services
 */
final class PluginServiceTest extends TestCase
{
    private PluginService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \WP_Mock::setUp();

        $this->service = new PluginService();
    }

    protected function tearDown(): void
    {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_lists_installed_plugins(): void
    {
        // Mock get_plugins() and option active_plugins.
        $pluginList = [
            'hello-dolly/hello.php' => [
                'Name'        => 'Hello Dolly',
                'Version'     => '1.7.2',
                'Author'      => 'Matt Mullenweg',
                'Description' => 'This is not just a band.',
                'PluginURI'   => 'https://wordpress.org/plugins/hello-dolly/',
            ]
        ];

        \WP_Mock::userFunction('get_plugins')->andReturn($pluginList);
        \WP_Mock::userFunction('get_option')
            ->with('active_plugins', \WP_Mock\Functions::type('array'))
            ->andReturn(['hello-dolly/hello.php']);

        \WP_Mock::userFunction('get_site_transient')
            ->with('update_plugins')
            ->andReturn((object) ['response' => []]);

        $list = $this->service->list();

        self::assertCount(1, $list);
        self::assertArrayHasKey('hello-dolly/hello.php', $list);

        $plugin = $list['hello-dolly/hello.php'];
        self::assertSame('Hello Dolly', $plugin['name']);
        self::assertSame('1.7.2', $plugin['version']);
        self::assertTrue($plugin['active']);
    }

    // -------------------------------------------------------------------------
    // search()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_querying_wp_org_api_fails(): void
    {
        $wpError = new \WP_Error('api_error', 'API is down.');

        \WP_Mock::userFunction('plugins_api')
            ->with('query_plugins', \WP_Mock\Functions::type('array'))
            ->andReturn($wpError);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('API is down.');

        $this->service->search('query');
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_deleting_active_plugin(): void
    {
        // Mock list to show the target is active.
        $pluginList = [
            'some-plugin/some-plugin.php' => [
                'Name'        => 'Some Plugin',
                'Version'     => '1.0.0',
                'Author'      => 'Author Name',
                'Description' => 'Description.',
            ]
        ];

        \WP_Mock::userFunction('get_plugins')->andReturn($pluginList);
        \WP_Mock::userFunction('get_option')->andReturn(['some-plugin/some-plugin.php']);
        \WP_Mock::userFunction('get_site_transient')->andReturn((object) ['response' => []]);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Cannot delete active plugin');

        $this->service->delete('some-plugin/some-plugin.php');
    }
}
