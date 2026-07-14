<?php

declare(strict_types=1);

namespace WpAgent\Tools\Media;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MediaService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.media.duplicates.detect
 *
 * Scans the database and detects duplicate attachments using MD5 checksums.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Media
 * @since   0.1.0
 */
final class DetectDuplicatesTool extends AbstractTool
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.media.duplicates.detect';
    }

    public function getDescription(): string
    {
        return 'Scans the Media Library and detects duplicate uploads by comparing MD5 file checksums. '
            . 'Returns groups of duplicate attachments so you can clean up duplicate items using deletion tools.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:read'];
    }

    public function getAnnotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        $duplicates = $this->mediaService->detectDuplicates();

        $formatted = [];
        foreach ( $duplicates as $checksum => $ids ) {
            $formatted[] = [
                'checksum' => $checksum,
                'count'    => count($ids),
                'ids'      => $ids,
            ];
        }

        return ToolResult::json([
            'success'          => true,
            'duplicate_groups' => $formatted,
            'count'            => count($formatted),
        ]);
    }
}
