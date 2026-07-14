<?php

declare(strict_types=1);

namespace WpAgent\MCP\Registry;

use WpAgent\Exceptions\McpException;
use WpAgent\MCP\Contracts\ToolInterface;

/**
 * Central tool registry.
 *
 * All MCP tools are registered here and looked up by name during
 * tools/list and tools/call dispatching. Supports discovery-by-namespace
 * and provides the MCP tools manifest format.
 *
 * @package WpAgent\MCP\Registry
 * @since   0.1.0
 */
final class ToolRegistry
{
    /** @var array<string, ToolInterface> Keyed by tool name. */
    private array $tools = [];

    /**
     * Registers a tool instance.
     *
     * @throws \InvalidArgumentException If a tool with the same name is already registered.
     */
    public function register(ToolInterface $tool): void
    {
        $name = $tool->getName();

        if ( isset( $this->tools[$name] ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Tool "%s" is already registered. Use a unique tool name.', $name )
            );
        }

        $this->tools[$name] = $tool;

        /**
         * Fires when a tool is registered in the registry.
         *
         * @param ToolInterface $tool The registered tool.
         *
         * @since 0.1.0
         */
        do_action('wpa_tool_registered', $tool);
    }

    /**
     * Registers multiple tools at once.
     *
     * @param ToolInterface[] $tools
     */
    public function registerMany(array $tools): void
    {
        foreach ( $tools as $tool ) {
            $this->register($tool);
        }
    }

    /**
     * Resolves a tool by its name.
     *
     * @throws McpException If the tool is not found.
     */
    public function resolve(string $name): ToolInterface
    {
        if ( ! isset( $this->tools[$name] ) ) {
            throw McpException::toolNotFound($name);
        }

        return $this->tools[$name];
    }

    /**
     * Checks if a tool is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Returns all registered tools, optionally filtered by namespace prefix.
     *
     * @param string $namespace Optional dot-notation namespace (e.g. "wordpress.pages").
     *
     * @return ToolInterface[]
     */
    public function all(string $namespace = ''): array
    {
        if ( empty($namespace) ) {
            return array_values($this->tools);
        }

        return array_values(
            array_filter(
                $this->tools,
                static fn (string $name): bool => str_starts_with($name, $namespace),
                ARRAY_FILTER_USE_KEY,
            )
        );
    }

    /**
     * Returns the count of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Serializes all tools to the MCP tools/list manifest format.
     *
     * @return array{tools: array<int, array<string, mixed>>}
     */
    public function toManifest(): array
    {
        $tools = [];

        foreach ( $this->tools as $tool ) {
            $definition = [
                'name'        => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];

            $annotations = $tool->getAnnotations();
            if ( ! empty($annotations) ) {
                $definition['annotations'] = $annotations;
            }

            $tools[] = $definition;
        }

        /**
         * Filters the tools manifest before returning to the client.
         *
         * @param array<int, array<string, mixed>> $tools Tool definitions.
         *
         * @since 0.1.0
         */
        $tools = (array) apply_filters('wpa_tools_manifest', $tools);

        return ['tools' => $tools];
    }
}
