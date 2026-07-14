<?php

declare(strict_types=1);

namespace WpAgent\Tools\Media;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MediaService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMedia;

/**
 * Tool: wordpress.media.replace
 *
 * Replaces the physical file of an attachment without changing its ID.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_others_posts (since attachments are posts)
 *
 * @package WpAgent\Tools\Media
 * @since   0.1.0
 */
final class ReplaceMediaTool extends AbstractTool
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.media.replace';
    }

    public function getDescription(): string
    {
        return 'Replaces the underlying physical file for an existing media attachment by its ID. '
            . 'Preserves the attachment ID, URL, description, alt text, and relations. '
            . 'Use this to update/change images in pages without breaking layout embeds or links.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'attachment_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the attachment to replace.',
                    'minimum'     => 1,
                ],
                'file_content'  => [
                    'type'        => 'string',
                    'description' => 'Base64-encoded replacement file content payload.',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['attachment_id', 'file_content'],
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

        $attachmentId = (int) $args['attachment_id'];
        $fileContent  = $args['file_content'];

        $attachment = $this->mediaService->replaceFile($attachmentId, $fileContent);

        return ToolResult::json(FormatsMedia::format($attachment));
    }
}
