<?php

declare(strict_types=1);

namespace App\Mcp\Tools\BrandReferencePhoto;

use App\Enums\Media\Type as MediaType;
use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Issue a one-shot signed POST URL for uploading a Brand Reference Photo to the current workspace. The URL accepts only an image in the media multipart field. After the client uploads the file, it is immediately available through list-brand-reference-photos-tool. An optional label describes how AI should use the reference image.')]
class RequestBrandReferencePhotoUploadTool extends Tool
{
    use AuthorizesMcpTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->authorizeCurrentWorkspace(
            $request,
            'update',
            'Not authorized to manage Brand Reference Photos.',
        );

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $token = (string) Str::uuid();
        $expiresAt = CarbonImmutable::now()->addMinutes(
            (int) config('trypost.media.signed_upload_url_ttl_minutes'),
        );
        $parameters = [
            'token' => $token,
            'workspace_id' => $workspace->id,
        ];

        if (($label = data_get($validated, 'label')) !== null) {
            $parameters['label'] = $label;
        }

        return Response::structured([
            'upload_token' => $token,
            'upload_url' => URL::temporarySignedRoute('api.brand-reference-uploads.store', $expiresAt, $parameters),
            'expires_at' => $expiresAt->toIso8601String(),
            'max_bytes' => MediaType::Image->maxSizeInBytes(),
            'field_name' => 'media',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'label' => $schema->string()->description('Optional description of the reference image, maximum 100 characters.'),
        ];
    }
}
