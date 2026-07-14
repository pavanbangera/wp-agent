<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\MCP;

use PHPUnit\Framework\TestCase;
use WpAgent\Exceptions\McpException;
use WpAgent\MCP\Protocol\JsonRpc;

/**
 * Tests for the JSON-RPC 2.0 implementation.
 *
 * @covers \WpAgent\MCP\Protocol\JsonRpc
 *
 * @package WpAgent\Tests\Unit\MCP
 */
final class JsonRpcTest extends TestCase
{
    // -------------------------------------------------------------------------
    // parseRequest()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_parses_a_valid_request(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'tools/list',
            'params'  => [],
            'id'      => 1,
        ]);

        $parsed = JsonRpc::parseRequest($body);

        self::assertSame('2.0', $parsed['jsonrpc']);
        self::assertSame('tools/list', $parsed['method']);
        self::assertSame([], $parsed['params']);
        self::assertSame(1, $parsed['id']);
    }

    /** @test */
    public function it_parses_a_notification_without_id(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'notifications/initialized',
            'params'  => [],
        ]);

        $parsed = JsonRpc::parseRequest($body);

        self::assertNull($parsed['id']);
    }

    /** @test */
    public function it_throws_on_empty_body(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionCode(McpException::PARSE_ERROR);

        JsonRpc::parseRequest('');
    }

    /** @test */
    public function it_throws_on_missing_jsonrpc_version(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionCode(McpException::INVALID_REQUEST);

        $body = json_encode(['method' => 'tools/list', 'id' => 1]);
        JsonRpc::parseRequest($body);
    }

    /** @test */
    public function it_throws_on_wrong_jsonrpc_version(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionCode(McpException::INVALID_REQUEST);

        $body = json_encode(['jsonrpc' => '1.0', 'method' => 'tools/list', 'id' => 1]);
        JsonRpc::parseRequest($body);
    }

    /** @test */
    public function it_throws_on_missing_method(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionCode(McpException::INVALID_REQUEST);

        $body = json_encode(['jsonrpc' => '2.0', 'id' => 1]);
        JsonRpc::parseRequest($body);
    }

    /** @test */
    public function it_throws_on_empty_method(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionCode(McpException::INVALID_REQUEST);

        $body = json_encode(['jsonrpc' => '2.0', 'method' => '', 'id' => 1]);
        JsonRpc::parseRequest($body);
    }

    /** @test */
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\JsonException::class);

        JsonRpc::parseRequest('not-json');
    }

    // -------------------------------------------------------------------------
    // success()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_builds_a_success_response(): void
    {
        $response = JsonRpc::success(['tools' => []], 42);

        self::assertSame('2.0', $response['jsonrpc']);
        self::assertSame(['tools' => []], $response['result']);
        self::assertSame(42, $response['id']);
        self::assertArrayNotHasKey('error', $response);
    }

    /** @test */
    public function it_builds_a_success_response_with_null_id(): void
    {
        $response = JsonRpc::success([], null);

        self::assertNull($response['id']);
    }

    // -------------------------------------------------------------------------
    // error()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_builds_an_error_response(): void
    {
        $response = JsonRpc::error(-32601, 'Method not found', null, 1);

        self::assertSame('2.0', $response['jsonrpc']);
        self::assertSame(1, $response['id']);
        self::assertSame(-32601, $response['error']['code']);
        self::assertSame('Method not found', $response['error']['message']);
        self::assertArrayNotHasKey('result', $response);
    }

    /** @test */
    public function it_includes_data_in_error_when_provided(): void
    {
        $response = JsonRpc::error(-32602, 'Invalid params', ['field' => 'title'], 2);

        self::assertArrayHasKey('data', $response['error']);
        self::assertSame(['field' => 'title'], $response['error']['data']);
    }

    /** @test */
    public function it_omits_data_in_error_when_null(): void
    {
        $response = JsonRpc::error(-32603, 'Internal error', null, 3);

        self::assertArrayNotHasKey('data', $response['error']);
    }

    // -------------------------------------------------------------------------
    // fromException()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_builds_error_from_mcp_exception(): void
    {
        $exception = McpException::toolNotFound('wordpress.fake.tool');
        $response  = JsonRpc::fromException($exception, 5);

        self::assertSame(McpException::TOOL_NOT_FOUND, $response['error']['code']);
        self::assertStringContainsString('wordpress.fake.tool', $response['error']['message']);
        self::assertSame(5, $response['id']);
    }

    // -------------------------------------------------------------------------
    // encode()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_encodes_response_to_json_string(): void
    {
        $response = JsonRpc::success(['ok' => true], 1);
        $json     = JsonRpc::encode($response);

        self::assertJson($json);
        self::assertStringContainsString('"ok":true', $json);
    }

    /** @test */
    public function it_does_not_escape_unicode_in_output(): void
    {
        $response = JsonRpc::success(['name' => 'héllo'], 1);
        $json     = JsonRpc::encode($response);

        self::assertStringContainsString('héllo', $json);
    }
}
