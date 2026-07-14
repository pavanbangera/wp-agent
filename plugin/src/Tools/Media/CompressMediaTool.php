<?php

declare(strict_types=1);

namespace WpAgent\Tools\Media;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MediaService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMedia;

/**
 * Tool: wordpress.media.compress
 *
 * Compresses an image in the media library by adjusting quality.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Media
 * @since   0.1.0
 */
final class CompressMediaTool extends AbstractTool
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.media.compress';
    }

    public function getDescription(): string
    {
        return 'Optimizes the file size of an existing image attachment by compressing it. '
            . 'Resaves the image at a specified quality level (default: 82). '
            . 'Automatically regenerates all thumbnail intermediate sub-sizes.';
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
                'quality'       => [
                    'type'        => 'integer',
                    'description' => 'Compression quality level (1 to 100). Default is 82.',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'default'     => 82,
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

        $attachmentId = (int) $args['attachment_id'];
        $quality      = (int) ($args['quality'] ?? 82);

        $attachment = $this->mediaService->compressImage($attachmentId, $quality);

        return ToolResult::json(FormatsMedia::format($attachment));
    }
}
