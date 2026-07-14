<?php

declare(strict_types=1);

namespace WpAgent\Tools\Media;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MediaService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMedia;

/**
 * Tool: wordpress.media.rename
 *
 * Renames a media file on disk and updates database entries.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Media
 * @since   0.1.0
 */
final class RenameMediaTool extends AbstractTool
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.media.rename';
    }

    public function getDescription(): string
    {
        return 'Renames an existing media file on disk and updates its database records '
            . '(GUID, slug, titles, meta sizes). Keeps file extensions intact. '
            . 'Use this to optimize image filenames for SEO (e.g. rename "IMG_9821.jpg" to "blue-leather-sofa.jpg").';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'attachment_id' => [
                    'type'        => 'integer',
                    'description' => 'Attachment ID.',
                    'minimum'     => 1,
                ],
                'new_filename'  => [
                    'type'        => 'string',
                    'description' => 'The new filename (e.g. "blue-sofa"). Extension is automatically preserved if omitted.',
                    'minLength'   => 1,
                    'maxLength'   => 100,
                ],
            ],
            'required'             => ['attachment_id', 'new_filename'],
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
        $newFilename  = $args['new_filename'];

        $attachment = $this->mediaService->renameFile($attachmentId, $newFilename);

        return ToolResult::json(FormatsMedia::format($attachment));
    }
}
