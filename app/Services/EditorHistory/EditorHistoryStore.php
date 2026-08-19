<?php

namespace App\Services\EditorHistory;

interface EditorHistoryStore
{
    public function get(int $userId, int $pageId, string $sessionId): ?array;

    public function put(int $userId, int $pageId, string $sessionId, array $history): void;

    public function forget(int $userId, int $pageId, string $sessionId): void;
}
