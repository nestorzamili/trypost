<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Enums\Media\Type as MediaType;
use App\Http\Resources\Api\PostResource;
use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Post;
use App\Support\PostMediaRules;
use finfo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach media (image, video, or PDF document) encoded as a base64 string or Data URI (e.g. data:image/png;base64,...) directly to a post. Useful for AI agents and sandboxes that cannot perform direct HTTP upload egress.')]
class AttachMediaFromBase64Tool extends Tool
{
    use AuthorizesMcpTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'post_id' => ['required', 'uuid'],
            'data' => ['required', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:'.PostMediaRules::ALT_TEXT_MAX_LENGTH],
        ]);

        $workspaceId = $request->user()?->current_workspace_id;

        $post = $workspaceId
            ? Post::where('workspace_id', $workspaceId)->find(data_get($validated, 'post_id'))
            : null;

        if (! $post) {
            return Response::error('Post not found.');
        }

        if ($denied = $this->denyUnlessCan($request, 'update', $post, 'Not authorized to update this post.')) {
            return $denied;
        }

        $rawData = (string) data_get($validated, 'data', '');
        $explicitMime = null;

        if (preg_match('/^data:([a-zA-Z0-9\/\+\-\.]+);base64,(.+)$/s', $rawData, $matches)) {
            $explicitMime = $matches[1];
            $base64String = $matches[2];
        } else {
            $base64String = $rawData;
        }

        // Clean any whitespace or newlines inside base64 string
        $base64String = preg_replace('/\s+/', '', $base64String) ?? $base64String;
        $binary = base64_decode($base64String, true);

        if ($binary === false || strlen($binary) === 0) {
            return Response::error('Invalid base64 encoded media data.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($binary);
        $mimeType = $detectedMime ?: $explicitMime ?: 'application/octet-stream';

        $mediaType = MediaType::fromMime($mimeType);

        if (! $mediaType) {
            return Response::error("Unsupported media format ({$mimeType}). Allowed formats: image/jpeg, image/png, image/gif, image/webp, video/mp4, video/quicktime, application/pdf.");
        }

        $sizeInBytes = strlen($binary);
        if ($sizeInBytes > $mediaType->maxSizeInBytes()) {
            return Response::error("Media size ({$sizeInBytes} bytes) exceeds the maximum allowed size of {$mediaType->maxSizeInMb()}MB for {$mediaType->value}.");
        }

        if (! in_array($mediaType, $post->allowedMediaTypes(), true)) {
            return Response::error('No enabled platform on this post accepts this media type.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'mcp_b64_');

        if ($tempFile === false) {
            return Response::error('Failed to allocate temporary storage for media processing.');
        }

        file_put_contents($tempFile, $binary);

        try {
            $extension = $mediaType->extensions()[0] ?? 'bin';
            $originalFilename = data_get($validated, 'filename') ?: ('media_'.Str::random(8).'.'.$extension);

            $media = $post->workspace->addMediaFromPath(
                $tempFile,
                $originalFilename,
                'assets',
                mimeType: $mimeType,
            );

            $item = [
                'id' => $media->id,
                'path' => $media->path,
                'url' => $media->url,
                'type' => $media->type,
                'mime_type' => $media->mime_type,
                'original_filename' => $media->original_filename,
            ];

            if (($alt = data_get($validated, 'alt')) !== null && $media->isImage()) {
                $item['meta'] = ['alt_text' => $alt];
            }

            $post->appendMedia([$item]);
            $post->refresh()->load(['postPlatforms.socialAccount', 'labels']);

            return Response::structured([
                'post' => (new PostResource($post))->resolve(),
            ]);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->string()->required()->description('UUID of the post to attach media to.'),
            'data' => $schema->string()->required()->description('Base64-encoded media bytes or Data URI (e.g. data:image/png;base64,...). Allowed types: image/jpeg, image/png, image/gif, image/webp, video/mp4, video/quicktime, application/pdf.'),
            'filename' => $schema->string()->description('Optional original filename (e.g. "generated-chart.png").'),
            'alt' => $schema->string()->description('Optional accessibility alt text for images (max 2000 chars).'),
        ];
    }
}
