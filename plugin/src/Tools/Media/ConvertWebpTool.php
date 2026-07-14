<?php

declare(strict_types=1);

namespace WpAgent\Tools\Media;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MediaService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMedia;

/**
 * Tool: wordpress.media.convert_webp
 *
 * Converts a PNG or JPG attachment to WebP format.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Media
 * @since   0.1.0
 */
final class ConvertWebpTool extends AbstractTool
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.media.convert_webp';
    }

    public function getDescription(): string
    {
        return 'Converts an existing PNG or JPG/JPEG image attachment to WebP format. '
            . 'WebP images are significantly smaller and faster to load. '
            . 'Deletes the original PNG/JPG file on disk to save space and '
            . 'updates all database references, filenames, and sub-size thumbnails.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'attachment_id' => [
                    'type'        => 'integer',
                    'description' => 'Image attachment ID.',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['attachment_id'],
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

        $attachment = $this->mediaService->convertToWebp((int) $args['attachment_id']);

        return ToolResult::json(FormatsMedia::format($attachment));
    }
}
