<?php

namespace App\Services\EditorHistory;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use RuntimeException;

final class CacheEditorHistoryStore implements EditorHistoryStore
{
    public function __construct(private readonly CacheFactory $cache) {}

    public function get(int $userId, int $pageId, string $sessionId): ?array
    {
        $value = $this->cache->store($this->store())->get($this->key($userId, $pageId, $sessionId));

        if (! is_array($value)) {
            return null;
        }

        if (($value['owner_user_id'] ?? null) !== $userId
            || ($value['page_id'] ?? null) !== $pageId
            || ! hash_equals((string) ($value['session_id'] ?? ''), $sessionId)) {
            return null;
        }

        return $value;
    }

    public function put(int $userId, int $pageId, string $sessionId, array $history): void
    {
        $history['owner_user_id'] = $userId;
        $history['page_id'] = $pageId;
        $history['session_id'] = $sessionId;

        $written = $this->cache->store($this->store())->put(
            $this->key($userId, $pageId, $sessionId),
            $history,
            now()->addSeconds(max(300, (int) config('cms.page_editor_history_ttl_seconds', 14_400))),
        );

        if ($written === false) {
            throw new RuntimeException('The editor history cache rejected the write.');
        }
    }

    public function forget(int $userId, int $pageId, string $sessionId): void
    {
        $this->cache->store($this->store())->forget($this->key($userId, $pageId, $sessionId));
    }

    private function key(int $userId, int $pageId, string $sessionId): string
    {
        return "cms:editor-history:user:{$userId}:page:{$pageId}:session:{$sessionId}";
    }

    private function store(): ?string
    {
        $store = config('cms.page_editor_history_store');

        return is_string($store) && $store !== '' ? $store : null;
    }
}
