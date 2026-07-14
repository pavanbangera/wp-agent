<?php

declare(strict_types=1);

namespace WpAgent\MCP\Protocol;

/**
 * MCP server capability declaration.
 *
 * Returned in the `initialize` response to inform the client
 * what protocol features this server supports.
 *
 * @package WpAgent\MCP\Protocol
 * @since   0.1.0
 * @see     https://spec.modelcontextprotocol.io/specification/basic/lifecycle/
 */
final class McpCapabilities
{
    /**
     * @param bool $toolsListChanged       Server emits tools/list_changed notifications.
     * @param bool $resourcesSubscribe     Server supports resource subscriptions.
     * @param bool $resourcesListChanged   Server emits resources/list_changed notifications.
     * @param bool $promptsListChanged     Server emits prompts/list_changed notifications.
     * @param bool $loggingEnabled         Server supports logging/setLevel.
     */
    public function __construct(
        private readonly bool $toolsListChanged = true,
        private readonly bool $resourcesSubscribe = false,
        private readonly bool $resourcesListChanged = true,
        private readonly bool $promptsListChanged = true,
        private readonly bool $loggingEnabled = true,
    ) {}

    /**
     * Returns the capabilities array for the initialize response.
     *
     * @return array{
     *   tools: array{listChanged: bool},
     *   resources: array{subscribe: bool, listChanged: bool},
     *   prompts: array{listChanged: bool},
     *   logging: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $caps = [
            'tools' => [
                'listChanged' => $this->toolsListChanged,
            ],
            'resources' => [
                'subscribe'   => $this->resourcesSubscribe,
                'listChanged' => $this->resourcesListChanged,
            ],
            'prompts' => [
                'listChanged' => $this->promptsListChanged,
            ],
        ];

        if ( $this->loggingEnabled ) {
            $caps['logging'] = new \stdClass(); // Empty object per MCP spec.
        }

        return $caps;
    }
}
