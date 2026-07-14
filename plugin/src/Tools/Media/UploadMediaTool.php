<?php

declare(strict_types=1);

namespace WpAgent\Tools\Media;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MediaService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMedia;

/**
 * Tool: wordpress.media.upload
 *
 * Uploads a base64 encoded file into the WordPress Media Library.
 *
 * Required scope: wp-agent:write
 * Required capability: upload_files
 *
 * @package WpAgent\Tools\Media
 * @since   0.1.0
 */
final class UploadMediaTool extends AbstractTool
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.media.upload';
    }

    public function getDescription(): string
    {
        return 'Uploads a media file (image, document, audio, video) to the WordPress Media Library. '
            . 'Expects the file contents as a base64-encoded string and a filename. '
            . 'Returns the uploaded attachment details including its URL and attachment ID.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'filename'       => [
                    'type'        => 'string',
                    'description' => 'Target name for the file (e.g. "hero-banner.jpg", "product-manual.pdf").',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'file_content'   => [
                    'type'        => 'string',
                    'description' => 'Base64-encoded file data payload.',
                    'minLength'   => 1,
                ],
                'parent_post_id' => [
                    'type'        => 'integer',
                    'description' => 'Optional post ID to attach this media to.',
                    'minimum'     => 0,
                    'default'     => 0,
                ],
            ],
            'required'             => ['filename', 'file_content'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('upload_files', $identity);

        $filename     = $args['filename'];
        $fileContent  = $args['file_content'];
        $parentPostId = (int) ($args['parent_post_id'] ?? 0);

        $attachment = $this->mediaService->uploadFromBase64($filename, $fileContent, $parentPostId);

        return ToolResult::json(FormatsMedia::format($attachment));
    }
}
