<?php

declare(strict_types=1);

namespace WpAgent\MCP\Protocol;

/**
 * Represents an MCP tool result.
 *
 * A tool result can contain multiple content items of different types:
 * text, image, or embedded resources. This matches the MCP specification
 * for the CallToolResult schema.
 *
 * @package WpAgent\MCP\Protocol
 * @since   0.1.0
 * @see     https://spec.modelcontextprotocol.io/specification/server/tools/
 */
final class ToolResult
{
    /**
     * @param ToolContent[] $content  Result content items.
     * @param bool          $isError  True if the tool encountered an error.
     */
    public function __construct(
        private readonly array $content,
        private readonly bool $isError = false,
    ) {}

    // -------------------------------------------------------------------------
    // Static factories
    // -------------------------------------------------------------------------

    /**
     * Creates a successful text result.
     */
    public static function text(string $text): self
    {
        return new self([ToolContent::text($text)]);
    }

    /**
     * Creates a successful JSON result (serialized as text content).
     *
     * @param array<string, mixed>|object $data
     */
    public static function json(array|object $data): self
    {
        return new self([ToolContent::text(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        )]);
    }

    /**
     * Creates an error result.
     */
    public static function error(string $message): self
    {
        return new self([ToolContent::text($message)], isError: true);
    }

    /**
     * Creates a result with multiple content items.
     *
     * @param ToolContent[] $contents
     */
    public static function many(array $contents, bool $isError = false): self
    {
        return new self($contents, $isError);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /** @return ToolContent[] */
    public function getContent(): array
    {
        return $this->content;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * Serializes to MCP spec format.
     *
     * @return array{content: array<int, array<string, mixed>>, isError?: bool}
     */
    public function toArray(): array
    {
        $result = [
            'content' => array_map(
                static fn (ToolContent $c): array => $c->toArray(),
                $this->content
            ),
        ];

        if ( $this->isError ) {
            $result['isError'] = true;
        }

        return $result;
    }
}


/**
 * A single content item within a tool result.
 *
 * @package WpAgent\MCP\Protocol
 * @since   0.1.0
 */
final class ToolContent
{
    private function __construct(
        private readonly string $type,
        private readonly string $text = '',
        private readonly string $data = '',
        private readonly string $mimeType = '',
        private readonly string $uri = '',
    ) {}

    public static function text(string $text): self
    {
        return new self(type: 'text', text: $text);
    }

    /**
     * @param string $base64Data Base64-encoded image data.
     * @param string $mimeType   MIME type (e.g. 'image/png').
     */
    public static function image(string $base64Data, string $mimeType): self
    {
        return new self(type: 'image', data: $base64Data, mimeType: $mimeType);
    }

    public static function resource(string $uri, string $mimeType = ''): self
    {
        return new self(type: 'resource', uri: $uri, mimeType: $mimeType);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return match ($this->type) {
            'text'     => ['type' => 'text', 'text' => $this->text],
            'image'    => ['type' => 'image', 'data' => $this->data, 'mimeType' => $this->mimeType],
            'resource' => ['type' => 'resource', 'resource' => ['uri' => $this->uri, 'mimeType' => $this->mimeType]],
            default    => ['type' => 'text', 'text' => $this->text],
        };
    }
}
