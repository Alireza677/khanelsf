<?php

namespace App\Services\EditorHistory;

use App\CMS\Blocks\BlockRegistry;
use Illuminate\Support\Str;
use Throwable;

final class SessionHistory
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly EditorHistoryStore $store,
    ) {}

    public function initial(int $userId, int $pageId, string $sessionId, array $state): array
    {
        $history = ['pointer' => 0, 'entries' => [[
            'id' => (string) Str::uuid(),
            'label' => 'شروع جلسه ویرایش',
            'kind' => 'initial',
            'field' => null,
            'at' => now()->getTimestampMs(),
            'state' => $this->encode($state),
        ]]];
        $this->store->put($userId, $pageId, $sessionId, $history);

        return $this->clientState($history);
    }

    public function capture(
        int $userId,
        int $pageId,
        string $sessionId,
        array $state,
        ?string $field,
        int $limit,
        int $maxBytes,
    ): ?array {
        $history = $this->store->get($userId, $pageId, $sessionId);

        if ($history === null) {
            return null;
        }

        $entries = is_array($history['entries'] ?? null) ? $history['entries'] : [];
        $pointer = min(max(0, (int) ($history['pointer'] ?? 0)), max(0, count($entries) - 1));
        $current = $this->decode($entries[$pointer]['state'] ?? null);

        if ($this->editorState($current) === $this->editorState($state)) {
            $this->store->put($userId, $pageId, $sessionId, $history);

            return $this->clientState($history);
        }

        $entries = array_slice($entries, 0, $pointer + 1);
        [$kind, $label] = $this->describe(is_array($current) ? $current : [], $state, $field);
        $now = now()->getTimestampMs();
        $last = array_key_last($entries);

        if ($kind === 'field'
            && $last !== null
            && ($entries[$last]['kind'] ?? null) === 'field'
            && ($entries[$last]['field'] ?? null) === $field
            && $now - (int) ($entries[$last]['at'] ?? 0) <= 5000) {
            $entries[$last] = compact('label', 'kind', 'field') + [
                'id' => $entries[$last]['id'],
                'at' => $now,
                'state' => $this->encode($state),
            ];
            $pointer = $last;
        } else {
            $entries[] = compact('label', 'kind', 'field') + [
                'id' => (string) Str::uuid(),
                'at' => $now,
                'state' => $this->encode($state),
            ];
            $pointer = count($entries) - 1;
        }

        while (count($entries) > 1 && (
            count($entries) > $limit
            || array_sum(array_map(static fn (array $entry): int => strlen($entry['state']), $entries)) > $maxBytes
        )) {
            array_shift($entries);
            $pointer--;
        }

        $history = ['entries' => $entries, 'pointer' => max(0, $pointer)];
        $this->store->put($userId, $pageId, $sessionId, $history);

        return $this->clientState($history);
    }

    public function state(int $userId, int $pageId, string $sessionId, string $checkpointId): ?array
    {
        $history = $this->store->get($userId, $pageId, $sessionId);

        if ($history === null) {
            return null;
        }

        foreach ($history['entries'] ?? [] as $entry) {
            if (is_array($entry) && hash_equals((string) ($entry['id'] ?? ''), $checkpointId)) {
                $this->store->put($userId, $pageId, $sessionId, $history);

                return $this->decode($entry['state'] ?? null);
            }
        }

        return null;
    }

    public function movePointer(int $userId, int $pageId, string $sessionId, string $checkpointId): ?array
    {
        $history = $this->store->get($userId, $pageId, $sessionId);

        if ($history === null) {
            return null;
        }

        foreach ($history['entries'] ?? [] as $index => $entry) {
            if (is_array($entry) && hash_equals((string) ($entry['id'] ?? ''), $checkpointId)) {
                $history['pointer'] = $index;
                $this->store->put($userId, $pageId, $sessionId, $history);

                return $this->clientState($history);
            }
        }

        return null;
    }

    public function relabelLatest(int $userId, int $pageId, string $sessionId, string $label, string $kind): ?array
    {
        $history = $this->store->get($userId, $pageId, $sessionId);
        $pointer = (int) ($history['pointer'] ?? -1);

        if ($history === null || ! isset($history['entries'][$pointer])) {
            return null;
        }

        $history['entries'][$pointer]['label'] = $label;
        $history['entries'][$pointer]['kind'] = $kind;
        $this->store->put($userId, $pageId, $sessionId, $history);

        return $this->clientState($history);
    }

    private function clientState(array $history): array
    {
        return [
            'entries' => array_map(static fn (array $entry): array => [
                'id' => $entry['id'],
                'label' => $entry['label'],
                'kind' => $entry['kind'],
                'at' => $entry['at'],
            ], $history['entries'] ?? []),
            'pointer' => (int) ($history['pointer'] ?? 0),
        ];
    }

    private function describe(array $before, array $after, ?string $field): array
    {
        $beforeBlocks = $this->blocksById($before['blocks'] ?? []);
        $afterBlocks = $this->blocksById($after['blocks'] ?? []);
        $added = array_values(array_diff(array_keys($afterBlocks), array_keys($beforeBlocks)));
        $deleted = array_values(array_diff(array_keys($beforeBlocks), array_keys($afterBlocks)));

        if (count($added) === 1) {
            $block = $afterBlocks[$added[0]];
            $duplicate = collect($beforeBlocks)->contains(fn (array $candidate): bool => $this->withoutIdentity($candidate) === $this->withoutIdentity($block));

            return [$duplicate ? 'duplicate' : 'add', ($duplicate ? 'تکثیر' : 'افزودن').' بلوک «'.$this->label($block).'»'];
        }

        if (count($deleted) === 1) {
            return ['delete', 'حذف بلوک «'.$this->label($beforeBlocks[$deleted[0]]).'»'];
        }

        if (array_keys($beforeBlocks) !== array_keys($afterBlocks)
            && array_diff(array_keys($beforeBlocks), array_keys($afterBlocks)) === []
            && array_diff(array_keys($afterBlocks), array_keys($beforeBlocks)) === []) {
            return ['reorder', 'جابجایی بلوک‌ها'];
        }

        if (is_string($field) && str_starts_with($field, 'blocks.')) {
            $key = explode('.', $field)[1] ?? null;
            $block = is_string($key) ? ($after['blocks'][$key] ?? null) : null;

            return ['field', 'ویرایش بلوک «'.$this->label(is_array($block) ? $block : []).'»'];
        }

        return ['field', 'ویرایش تنظیمات برگه'];
    }

    private function blocksById(mixed $blocks): array
    {
        $result = [];
        $anonymousByType = [];

        foreach (is_array($blocks) ? $blocks : [] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $id = data_get($block, 'data.block_id');
            $type = is_string($block['type'] ?? null) ? $block['type'] : 'unknown';
            $anonymousByType[$type] = ($anonymousByType[$type] ?? 0) + 1;
            $key = is_string($id) && $id !== ''
                ? $id
                : "anonymous:{$type}:{$anonymousByType[$type]}";
            $result[$key] = $block;
        }

        return $result;
    }

    private function withoutIdentity(array $block): array
    {
        unset($block['data']['block_id']);

        return $block;
    }

    private function editorState(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->editorState($item);
        }

        if ($normalized !== [] && collect(array_keys($normalized))->every(
            fn (int|string $key): bool => is_string($key)
                && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key) === 1,
        )) {
            return array_values($normalized);
        }

        return $normalized;
    }

    private function encode(array $state): string
    {
        return json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decode(mixed $state): array
    {
        if (! is_string($state) || $state === '') {
            return [];
        }

        $decoded = json_decode($state, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function label(array $block): string
    {
        $type = is_string($block['type'] ?? null) ? $block['type'] : 'نامشخص';

        try {
            return $this->blocks->has($type) ? $this->blocks->find($type)->label() : $type;
        } catch (Throwable) {
            return $type;
        }
    }
}
