<?php

declare(strict_types=1);

namespace App\Ai\Tools\Post;

use App\Ai\Templates\AiTemplateRegistry;
use App\Ai\Templates\Concerns\ResolvesContentType;
use App\Ai\Tools\WorkspaceWriteTool;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\Workspace\ContentLanguage;
use App\Events\Ai\PostCreationReady;
use App\Jobs\Ai\StreamPostCreation;
use App\Services\Ai\PostGenerationCatalog;
use App\Support\AiPromptRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Starts an AI post generation and returns immediately.
 *
 * The tool never waits for the generation: it validates the choices the model
 * collected, dispatches {@see StreamPostCreation} on the `ai` queue, and
 * answers with the creation id plus the private channel
 * {@see PostCreationReady} broadcasts the finished post on. That is what keeps
 * a chat turn cheap — no PHP worker is held for the length of a generation.
 *
 * Every argument comes from a language model, so each one is validated here
 * and every failure names both what was wrong and the valid options, so the
 * model can correct itself and retry instead of reporting a dead end.
 */
class GeneratePostTool extends WorkspaceWriteTool
{
    use ResolvesContentType;

    /**
     * Upper bound on generated images. A format may allow fewer — see
     * ContentType::maxMediaCount().
     */
    private const MAX_IMAGE_COUNT = 10;

    public function name(): string
    {
        return 'generate_post';
    }

    public function description(): Stringable|string
    {
        return 'Generate a post with AI in the current workspace, using the format, style and prompt the user chose. Generation follows the workspace brand (variant plus photo references) on its own. Call start_post_generation first to learn which formats, styles and brand languages this workspace supports, and confirm those choices with the user before calling this. Pass the language_code the user chose for a brand language variant, and set use_brand_references to false only when the user asked to leave their brand reference photos out. Generation runs in the background: this tool returns as soon as it starts, with a creation id and the channel the finished post is announced on. Say one short sentence BEFORE the call naming what is being generated, and nothing after it: the result card above reports waiting, progress, readiness and failure on its own, and any narration added afterwards lands below it as stale text that can never update. When the user asks to retry only the images of a failed generation, call retry_post_images with that result\'s creation_id instead of generating a fresh post. Pass label_ids from list_labels to tag it; add a signature or library asset afterwards with update_post / attach_existing_asset.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()->required()->description('What the post should be about, in the user\'s own words. Between '.AiPromptRules::PROMPT_MIN_LENGTH.' and '.AiPromptRules::PROMPT_MAX_LENGTH.' characters.'),
            'format' => $schema->string()->required()->description('The format to generate for, taken from the formats start_post_generation returned for this workspace, e.g. "x_post" or "instagram_carousel".'),
            'style' => $schema->string()->required()->enum(app(AiTemplateRegistry::class)->keys())->description('The visual style key, taken from the styles start_post_generation returned.'),
            'social_account_id' => $schema->string()->description('The id of the connected account the post is for, taken from the chosen format\'s accounts. Required for a style whose needs_account is true.'),
            'image_count' => $schema->integer()->min(0)->max(self::MAX_IMAGE_COUNT)->description('How many images to generate. 0 for a text-only post. Defaults to 0.'),
            'date' => $schema->string()->description('Optional date the post is meant for, as Y-m-d.'),
            'apply_brand_visuals' => $schema->boolean()->description('Whether the generated images use the workspace brand palette. Defaults to true.'),
            'use_brand_references' => $schema->boolean()->description('Whether the generated images follow the workspace brand reference photos. Defaults to true when references exist.'),
            'language_code' => $schema->string()->enum(ContentLanguage::values())->description('The language the post should be written in, when the user picked a brand language variant. Omit to use the workspace default.'),
            'label_ids' => $schema->array()->items($schema->string())->description('Optional. Label ids from list_labels to tag the generated post with.'),
        ];
    }

    protected function run(Request $request): string
    {
        $format = $request->string('format')->trim()->value();
        $style = $request->string('style')->trim()->value();
        $socialAccountId = $request->filled('social_account_id')
            ? $request->string('social_account_id')->trim()->value()
            : null;

        $languageCode = $request->filled('language_code')
            ? $request->string('language_code')->trim()->value()
            : null;

        $error = $this->aiAccessError() ?? $this->argumentError($request);

        if ($error !== null) {
            return $this->error($error);
        }

        $catalog = PostGenerationCatalog::forWorkspace($this->workspace);

        $error = $this->formatError($catalog, $format)
            ?? $this->styleError($style, $socialAccountId)
            ?? $this->socialAccountError($catalog, $format, $socialAccountId, $request->integer('image_count'))
            ?? $this->imageCountError($format, $request->integer('image_count'))
            ?? $this->languageCodeError($catalog, $languageCode);

        if ($error !== null) {
            return $this->error($error);
        }

        $labelIds = $this->validLabelIds($request);

        if (is_string($labelIds)) {
            return $this->error($labelIds);
        }

        $creationId = $this->creationIdFor($request);

        StreamPostCreation::dispatch(
            userId: $this->user->id,
            creationId: $creationId,
            workspaceId: $this->workspace->id,
            format: $format,
            socialAccountId: $socialAccountId,
            imageCount: $request->integer('image_count'),
            prompt: $request->string('prompt')->trim()->value(),
            date: $request->filled('date') ? $request->string('date')->trim()->value() : null,
            template: $style,
            applyBrandVisuals: $request->boolean('apply_brand_visuals', true),
            useBrandReferences: $request->boolean('use_brand_references', true),
            languageCode: $languageCode,
            referenceMediaIds: $this->selectedReferenceMediaIds(),
            labelIds: $labelIds,
        );

        return $this->json([
            'data' => [
                'creation_id' => $creationId,
                'channel' => $this->channelFor($creationId),
            ],
        ]);
    }

    /**
     * The `useAi` gate, run before dispatching. Usage itself is recorded by
     * StreamPostCreation, so nothing is metered here — doing it in both
     * places would bill the account twice.
     */
    private function aiAccessError(): ?string
    {
        $gate = Gate::forUser($this->user)->inspect('useAi', $this->workspace->account);

        return $gate->denied() ? (string) $gate->message() : null;
    }

    /**
     * Shape rules for the model-supplied arguments, including the shared
     * prompt bounds. The framework's own messages already name the offending
     * argument and the bound it broke.
     */
    private function argumentError(Request $request): ?string
    {
        $validator = Validator::make($request->toArray(), [
            'prompt' => AiPromptRules::generationPromptRule(),
            'social_account_id' => ['nullable', 'uuid'],
            'image_count' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_IMAGE_COUNT],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'use_brand_references' => ['nullable', 'boolean'],
            'language_code' => ['nullable', 'string'],
        ], attributes: [
            'social_account_id' => 'social_account_id',
            'image_count' => 'image_count',
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    /**
     * The format must be one the catalog offers for THIS workspace, not merely
     * a format the platform knows: a format whose platform has no connected
     * account cannot produce a publishable post.
     *
     * @param  array{
     *     formats: list<array{value: string, platform: string, accounts: list<array{id: string, label: string, username: ?string, platform: string}>}>,
     *     styles: list<array{key: string, name: string, description: string, preview: string, needs_account: bool, supported_formats: list<string>, applies_brand_visuals: bool}>,
     *     applies_brand_visuals_default: bool,
     * }  $catalog
     */
    private function formatError(array $catalog, string $format): ?string
    {
        $available = array_values(array_unique(
            array_column(data_get($catalog, 'formats', []), 'value')
        ));

        if ($available === []) {
            $connected = $this->workspace->socialAccounts()->active()->get()
                ->map(fn ($account): string => $account->platform->value)
                ->unique()
                ->values()
                ->all();

            if ($connected !== []) {
                $names = array_map(
                    fn (string $platform): string => Platform::tryFrom($platform)?->label() ?? $platform,
                    $connected,
                );

                return 'This workspace has connected '.implode(', ', $names).' account(s), but AI generation does not support those platforms yet. Ask the user to connect an account on a supported platform first.';
            }

            return 'This workspace has no connected social accounts, so there is no format to generate a post for. Ask the user to connect an account first.';
        }

        if (in_array($format, $available, true)) {
            return null;
        }

        $options = implode(', ', $available);

        if ($format === '') {
            return "The \"format\" argument is required. Call start_post_generation and pass one of: {$options}.";
        }

        return "The format \"{$format}\" isn't available in this workspace. Call start_post_generation and pass one of: {$options}.";
    }

    /**
     * The style key must exist in the registry — validated here rather than by
     * catching AiTemplateRegistry::find()'s InvalidArgumentException, which
     * WorkspaceTool::handle() would flatten into the generic error the model
     * cannot act on.
     */
    private function styleError(string $style, ?string $socialAccountId): ?string
    {
        $registry = app(AiTemplateRegistry::class);
        $keys = $registry->keys();
        $options = implode(', ', $keys);

        if ($style === '') {
            return "The \"style\" argument is required. Valid styles are: {$options}.";
        }

        if (! in_array($style, $keys, true)) {
            return "The style \"{$style}\" doesn't exist. Valid styles are: {$options}.";
        }

        if ($registry->find($style)->needsAccount() && blank($socialAccountId)) {
            return "The \"{$style}\" style renders the post as the account's own card, so it needs a connected account. Pass social_account_id using one of the account ids start_post_generation returned for this format.";
        }

        return null;
    }

    /**
     * The account must be one the catalog itself offers for the chosen format,
     * which is stricter than mere workspace ownership in two ways that matter:
     * the catalog lists only ACTIVE accounts, and it lists them per format, so
     * an account on a platform the format cannot post to is refused here
     * rather than producing a post bound to the wrong account.
     *
     * @param  array{
     *     formats: list<array{value: string, platform: string, accounts: list<array{id: string, label: string, username: ?string, platform: string}>}>,
     *     styles: list<array{key: string, name: string, description: string, preview: string, needs_account: bool, supported_formats: list<string>, applies_brand_visuals: bool}>,
     *     applies_brand_visuals_default: bool,
     * }  $catalog
     */
    private function socialAccountError(array $catalog, string $format, ?string $socialAccountId, int $imageCount = 0): ?string
    {
        if ($socialAccountId === null) {
            // Every template renders images as the account's own card, so an
            // image request without an account could never produce media —
            // refuse loudly instead of delivering a text-only surprise.
            if ($imageCount > 0) {
                return "The image_count asks for {$imageCount} image(s) but no social_account_id was given. Pass social_account_id using one of the account ids start_post_generation returned for this format, or set image_count to 0 for a text-only post.";
            }

            return null;
        }

        $accounts = $this->accountsForFormat($catalog, $format);

        if (in_array($socialAccountId, array_column($accounts, 'id'), true)) {
            return null;
        }

        $options = implode(', ', array_map(
            fn (array $account): string => data_get($account, 'id').' ('.self::describeAccount($account).')',
            $accounts,
        ));

        return "The social account \"{$socialAccountId}\" can't be used for the \"{$format}\" format — it belongs to another workspace, is disconnected, or is on a different platform. Use one of these account ids instead: {$options}.";
    }

    /**
     * The catalog's active accounts for one format, flattened across the
     * platforms that format is compatible with. Never empty for a format that
     * passed formatError(): the catalog only lists a format once at least one
     * active account can post it.
     *
     * @param  array{
     *     formats: list<array{value: string, platform: string, accounts: list<array{id: string, label: string, username: ?string, platform: string}>}>,
     *     styles: list<array{key: string, name: string, description: string, preview: string, needs_account: bool, supported_formats: list<string>, applies_brand_visuals: bool}>,
     *     applies_brand_visuals_default: bool,
     * }  $catalog
     * @return list<array{id: string, label: string, username: ?string, platform: string}>
     */
    private function accountsForFormat(array $catalog, string $format): array
    {
        $accounts = [];

        foreach (data_get($catalog, 'formats', []) as $entry) {
            if (data_get($entry, 'value') !== $format) {
                continue;
            }

            foreach (data_get($entry, 'accounts', []) as $account) {
                $accounts[data_get($account, 'id')] = $account;
            }
        }

        return array_values($accounts);
    }

    /**
     * A human-readable name for one catalog account. The display label alone
     * is not enough: two Instagram connections for the same brand share it, so
     * the handle is appended whenever the account has one.
     *
     * @param  array{id: string, label: string, username: ?string, platform: string}  $account
     */
    private static function describeAccount(array $account): string
    {
        $label = (string) data_get($account, 'label');
        $username = data_get($account, 'username');

        if (! is_string($username) || $username === '') {
            return $label;
        }

        return "{$label} @{$username}";
    }

    /**
     * The generation's id, and with it the uniqueness lock on
     * {@see StreamPostCreation} (`ShouldBeUnique`, keyed on
     * `{userId}:{creationId}`).
     *
     * The provider's tool call id is the right key: an SDK-level retry of the
     * SAME tool call carries the same id, so the second dispatch is swallowed
     * by the lock instead of generating — and billing — twice, while two
     * deliberate calls carry different ids and both run. A minted uuid is the
     * fallback for a caller that supplies no id (a direct handle() call, or a
     * provider that omits one), where nothing can be deduplicated anyway.
     */
    private function creationIdFor(Request $request): string
    {
        return $request->toolCallId() ?? Str::uuid()->toString();
    }

    /**
     * Beyond the global 0-10 bound, a format never accepts more media than the
     * platform itself does, so a generation that asked for more could never be
     * published.
     *
     * Runs only after formatError() has accepted the format, so the format is
     * always one the catalog offers — every one of which resolves to a
     * ContentType (the carousel pseudo-format through ResolvesContentType).
     */
    private function imageCountError(string $format, int $imageCount): ?string
    {
        // The pipeline renders at most one image for a single-image format, so
        // accepting more would silently truncate the request.
        if (in_array($format, [ContentType::TelegramPost->value, ContentType::DiscordMessage->value], true) && $imageCount > 1) {
            return "The format \"{$format}\" accepts at most 1 image per generation. Call generate_post again with image_count set to 1 or 0.";
        }

        // A carousel without images can never be published, and the pipeline
        // would otherwise render one image unasked.
        if ($format === ContentType::CAROUSEL_FORMAT && $imageCount < 1) {
            return 'The format "instagram_carousel" needs at least 1 image. Call generate_post again with image_count set to 1 or more, or pick a single-image format for a text-only post.';
        }

        $max = self::resolveContentType($format)->maxMediaCount();

        if ($imageCount <= $max) {
            return null;
        }

        return "The format \"{$format}\" accepts at most {$max} images. Call generate_post again with image_count set to {$max} or fewer.";
    }

    private function languageCodeError(array $catalog, ?string $languageCode): ?string
    {
        if ($languageCode === null || $languageCode === '') {
            return null;
        }

        $available = array_values(array_filter(
            array_column(data_get($catalog, 'languages', []), 'language_code'),
            fn ($code): bool => is_string($code) && $code !== '',
        ));

        if ($available === []) {
            return null;
        }

        if (in_array($languageCode, $available, true)) {
            return null;
        }

        $options = implode(', ', $available);

        return "The language \"{$languageCode}\" isn't offered in this workspace. Call start_post_generation and pass one of: {$options}.";
    }

    /**
     * The private channel the finished post is announced on, taken from the
     * event itself so the name is never spelled out a second time. Echo
     * subscribes with `private()`, which adds the prefix back.
     */
    private function channelFor(string $creationId): string
    {
        $channel = (new PostCreationReady($this->user->id, $creationId))->broadcastOn();

        return Str::after($channel->name, 'private-');
    }

    /**
     * Reference-photo ids the user picked in the chat generation card. These
     * arrive as a STRUCTURED field on the chat HTTP request, not through the
     * model's tool arguments — passing UUIDs through the prompt is unreliable,
     * so the frontend sends them out-of-band and the tool reads them here. Only
     * ids that really belong to this workspace's brand_references survive, so a
     * spoofed or stale id is silently dropped. An empty result leaves
     * generation on its default (all references when use_brand_references).
     *
     * @return array<int, string>
     */
    private function selectedReferenceMediaIds(): array
    {
        $raw = request()->input('reference_media_ids');

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $requested = array_values(array_unique(array_filter(
            array_map(fn ($id): string => (string) $id, $raw),
            fn (string $id): bool => $id !== '',
        )));

        if ($requested === []) {
            return [];
        }

        return $this->workspace->getMedia('brand_references')
            ->whereIn('id', $requested)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }
}
