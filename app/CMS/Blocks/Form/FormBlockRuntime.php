<?php

namespace App\CMS\Blocks\Form;

use App\Models\Form;
use App\Services\FormSchema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class FormBlockRuntime
{
    /** @var array<int, Form|null> */
    private array $forms = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $fields = [];

    /** @var array<int, string|null> */
    private array $media = [];

    /** @var array<string, int> */
    private array $occurrences = [];

    public function __construct(
        private readonly FormBlock $block,
        private readonly FormSchema $schema,
    ) {}

    /** @param array<string, mixed> $data @param array<string, mixed> $context */
    public function prepare(array $data, array $context = [], bool $preview = false): array
    {
        $normalized = $this->block->normalize($data);
        $formId = $normalized['content']['form_id'];
        $form = $formId ? $this->form($formId) : null;
        $instanceToken = $this->instanceToken($normalized, $context, $formId);

        return [
            ...$normalized,
            'available' => $form instanceof Form,
            'preview' => $preview,
            'form' => $form,
            'fields' => $form ? $this->fields($form) : [],
            'instance_token' => $instanceToken,
            'attribution' => [
                'page_id' => $context['page_id'] ?? null,
                'page_url' => $context['page_url'] ?? request()->getRequestUri(),
                'block_id' => $normalized['block_id'],
            ],
            'content' => [
                ...$normalized['content'],
                'media' => [
                    ...$normalized['content']['media'],
                    'url' => $this->mediaUrl($normalized['content']['media']),
                ],
            ],
        ];
    }

    private function form(int $formId): ?Form
    {
        if (! array_key_exists($formId, $this->forms)) {
            $this->forms[$formId] = Form::query()->published()->find($formId);
        }

        return $this->forms[$formId];
    }

    private function fields(Form $form): array
    {
        return $this->fields[$form->getKey()] ??= $this->schema->fields($form);
    }

    /** @param array<string, mixed> $media */
    private function mediaUrl(array $media): ?string
    {
        $sourceId = $media['source_id'] ?? null;
        $url = $media['url'] ?? null;

        if (! is_int($sourceId)) {
            return is_string($url) ? $url : null;
        }

        if (! array_key_exists($sourceId, $this->media)) {
            $record = Media::query()
                ->where('collection_name', 'media_library')
                ->where('mime_type', 'like', 'image/%')
                ->find($sourceId);
            $this->media[$sourceId] = $record && file_exists($record->getPath())
                ? $record->getUrl()
                : null;
        }

        return $this->media[$sourceId];
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $context */
    private function instanceToken(array $data, array $context, ?int $formId): string
    {
        $blockId = $data['block_id'] ?? null;

        if (is_string($blockId) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $blockId) === 1) {
            return 'embedded-'.strtolower($blockId);
        }

        $scope = implode('|', [
            (string) ($context['page_id'] ?? ''),
            (string) ($context['page_url'] ?? request()->getRequestUri()),
            (string) $formId,
        ]);
        $occurrence = $this->occurrences[$scope] = ($this->occurrences[$scope] ?? 0) + 1;

        return 'embedded-'.Str::substr(hash('sha256', "{$scope}|{$occurrence}"), 0, 32);
    }
}
