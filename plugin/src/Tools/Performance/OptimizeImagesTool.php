<?php

declare(strict_types=1);

namespace WpAgent\Tools\Performance;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.performance.images.optimize
 *
 * Runs minor bulk image resizing or checks unoptimized attachments.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Performance
 * @since   0.1.0
 */
final class OptimizeImagesTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.performance.images.optimize';
    }

    public function getDescription(): string
    {
        return 'Audits the media library for unoptimized attachments and triggers sub-sizes regenerations.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'limit' => [
                    'type'        => 'integer',
                    'description' => 'Maximum number of images to check.',
                    'default'     => 10,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);

        $limit = (int) ($args['limit'] ?? 10);

        $images = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
        ]);

        $optimized = 0;
        foreach ( $images as $id ) {
            // Re-generate sub-sizes metadata if missing.
            $file = get_attached_file($id);
            if ( $file && file_exists($file) ) {
                $meta = wp_get_attachment_metadata($id);
                if ( empty($meta) && function_exists('wp_generate_attachment_metadata') ) {
                    $newMeta = wp_generate_attachment_metadata($id, $file);
                    wp_update_attachment_metadata($id, $newMeta);
                    $optimized++;
                }
            }
        }

        return ToolResult::json([
            'success'          => true,
            'total_inspected'  => count($images),
            'regenerated_meta' => $optimized,
            'message'          => "Regenerated image sizes metadata for {$optimized} attachments.",
        ]);
    }
}
