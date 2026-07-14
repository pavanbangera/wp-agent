<?php

declare(strict_types=1);

namespace WpAgent\Tools\Media;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Repositories\MediaRepository;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.media.alt_text.generate
 *
 * Generates an alternative text description for an image.
 * Provides a hook so AI Vision integrations (e.g. Gemini, OpenAI)
 * can power the actual generation. If no hook is attached, fallback to a description
 * based on the file/post title.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Media
 * @since   0.1.0
 */
final class GenerateAltTextTool extends AbstractTool
{
    public function __construct(
        private readonly MediaRepository $repository,
    ) {}

    public function getName(): string
    {
        return 'wordpress.media.alt_text.generate';
    }

    public function getDescription(): string
    {
        return 'Generates and saves a descriptive Alt text for an image attachment. '
            . 'Allows hooks to integrate with AI vision APIs (like Gemini or OpenAI). '
            . 'If no AI integration is loaded, generates a clean fallback descriptive alt text from the filename.';
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
                'overwrite'     => [
                    'type'        => 'boolean',
                    'description' => 'Overwrite existing alt text if present. Default is false.',
                    'default'     => false,
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
        $overwrite    = (bool) ($args['overwrite'] ?? false);

        $attachment = $this->repository->findOrFail($attachmentId);

        $existingAlt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);

        if ( ! empty($existingAlt) && ! $overwrite ) {
            return ToolResult::json([
                'success'       => true,
                'attachment_id' => $attachmentId,
                'alt_text'      => $existingAlt,
                'message'       => 'Skipped: Alt text already exists. Set overwrite to true to replace.',
            ]);
        }

        // Clean filename fallback.
        $attachedFile = get_attached_file($attachmentId);
        $baseName     = $attachedFile ? pathinfo($attachedFile, PATHINFO_FILENAME) : $attachment->post_title;
        $fallbackAlt  = ucwords(str_replace(['-', '_'], ' ', $baseName));

        /**
         * Filter to generate alternative text for media items.
         *
         * Hook here to integrate Vision/AI services (Gemini/OpenAI/etc.)
         *
         * @param string   $altText      The generated alt text.
         * @param int      $attachmentId The attachment post ID.
         * @param \WP_Post $attachment   The attachment post model.
         *
         * @since 0.1.0
         */
        $altText = (string) apply_filters('wpa_generate_alt_text', $fallbackAlt, $attachmentId, $attachment);
        $altText = sanitize_text_field(trim($altText));

        update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);

        return ToolResult::json([
            'success'       => true,
            'attachment_id' => $attachmentId,
            'alt_text'      => $altText,
            'message'       => 'Alt text successfully updated.',
        ]);
    }
}
