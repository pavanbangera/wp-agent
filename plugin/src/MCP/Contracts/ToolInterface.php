<?php

declare(strict_types=1);

namespace WpAgent\MCP\Contracts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;

/**
 * Contract for all MCP tools.
 *
 * Every tool exposed via the Model Context Protocol must implement this
 * interface. Tools are discovered and registered via the ToolRegistry.
 *
 * @package WpAgent\MCP\Contracts
 * @since   0.1.0
 */
interface ToolInterface
{
    /**
     * Returns the tool's unique name (dot-notation, e.g. "wordpress.pages.create").
     */
    public function getName(): string;

    /**
     * Returns a human-readable description of what the tool does.
     */
    public function getDescription(): string;

    /**
     * Returns a JSON Schema 7 object describing the accepted input.
     *
     * @return array{
     *   type: 'object',
     *   properties: array<string, array<string, mixed>>,
     *   required?: string[],
     *   additionalProperties?: bool
     * }
     */
    public function getInputSchema(): array;

    /**
     * Returns the MCP scopes required to invoke this tool.
     *
     * @return string[]
     */
    public function getRequiredScopes(): array;

    /**
     * Returns optional annotations for tool metadata (e.g. readOnly, destructive).
     *
     * @return array<string, mixed>
     */
    public function getAnnotations(): array;

    /**
     * Executes the tool with the given arguments.
     *
     * @param array<string, mixed> $args     Validated tool arguments.
     * @param Identity             $identity The authenticated caller.
     *
     * @return ToolResult
     */
    public function execute(array $args, Identity $identity): ToolResult;
}
