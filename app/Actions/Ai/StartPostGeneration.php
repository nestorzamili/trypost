<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Events\Ai\PostCreationReady;
use App\Jobs\Ai\StreamPostCreation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\PostGenerationCatalog;
use App\Support\AiPromptRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StartPostGeneration
{
    public const MAX_IMAGE_COUNT = 10;

    /**
     * @param  array<string, mixed>  $data
     * @return array{creation_id: string, channel: string}
     *
     * @throws ValidationException
     */
    public static function execute(User $user, Workspace $workspace, array $data): array
    {
        $gate = Gate::forUser($user)->inspect('useAi', $workspace->account);

        if ($gate->denied()) {
            throw ValidationException::withMessages([
                'ai' => (string) $gate->message(),
            ]);
        }

        if (empty($data['style']) && ! empty($data['template'])) {
            $data['style'] = $data['template'];
        }

        $catalog = PostGenerationCatalog::forWorkspace($workspace);

        $validator = Validator::make($data, self::rules($workspace, $catalog), attributes: [
            'creation_id' => 'creation_id',
            'format' => 'format',
            'style' => 'style',
            'social_account_id' => 'account',
            'image_count' => 'images',
            'prompt' => 'prompt',
            'date' => 'date',
            'language_code' => 'language',
            'label_ids' => 'labels',
        ]);

        $validated = $validator->validate();
        $creationId = ! empty($validated['creation_id']) && Str::isUuid((string) $validated['creation_id'])
            ? (string) $validated['creation_id']
            : (string) Str::uuid();

        StreamPostCreation::dispatch(
            userId: $user->id,
            creationId: $creationId,
            workspaceId: $workspace->id,
            format: (string) $validated['format'],
            socialAccountId: $validated['social_account_id'] ?? null,
            imageCount: (int) ($validated['image_count'] ?? 0),
            prompt: trim((string) $validated['prompt']),
            date: $validated['date'] ?? null,
            template: (string) ($validated['style'] ?? 'image_card'),
            applyBrandVisuals: (bool) ($data['apply_brand_visuals'] ?? true),
            useBrandReferences: (bool) ($data['use_brand_references'] ?? true),
            referenceMediaIds: self::referenceMediaIds($workspace, $data),
            languageCode: $validated['language_code'] ?? null,
            labelIds: self::labelIds($workspace, $data),
        );

        return [
            'creation_id' => $creationId,
            'channel' => self::channelFor($user->id, $creationId),
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private static function rules(Workspace $workspace, array $catalog): array
    {
        $formatValues = array_values(array_unique(
            array_column(data_get($catalog, 'formats', []), 'value')
        ));

        $styleKeys = array_values(array_unique(
            array_column(data_get($catalog, 'styles', []), 'key')
        ));

        $accountIds = array_values(array_unique(array_merge(
            ...array_map(
                fn (array $format): array => array_column(data_get($format, 'accounts', []), 'id'),
                data_get($catalog, 'formats', []),
            ),
        )));

        $languageCodes = array_values(array_filter(
            array_column(data_get($catalog, 'languages', []), 'language_code'),
            fn ($code): bool => is_string($code) && $code !== '',
        ));

        $labelIds = $workspace->labels()->pluck('id')->all();

        return array_filter([
            'creation_id' => ['nullable', 'uuid'],
            'format' => ['required', 'string', 'in:'.implode(',', $formatValues)],
            'style' => $styleKeys === [] ? null : ['required', 'string', 'in:'.implode(',', $styleKeys)],
            'social_account_id' => ['nullable', 'uuid', 'in:'.implode(',', $accountIds)],
            'image_count' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_IMAGE_COUNT],
            'prompt' => AiPromptRules::generationPromptRule(),
            'date' => ['nullable', 'date_format:Y-m-d'],
            'language_code' => $languageCodes === [] ? null : ['nullable', 'string', 'in:'.implode(',', $languageCodes)],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['uuid', 'in:'.implode(',', $labelIds)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function referenceMediaIds(Workspace $workspace, array $data): array
    {
        if (! ($data['use_brand_references'] ?? true)) {
            return [];
        }

        $ids = collect($data['reference_media_ids'] ?? [])
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return $workspace->media()
            ->whereIn('id', $ids)
            ->where('collection', 'brand_references')
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function labelIds(Workspace $workspace, array $data): array
    {
        $ids = collect($data['label_ids'] ?? [])
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return $workspace->labels()->whereIn('id', $ids)->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    private static function channelFor(string $userId, string $creationId): string
    {
        $channel = (new PostCreationReady($userId, $creationId))->broadcastOn();

        return Str::after($channel->name, 'private-');
    }
}
