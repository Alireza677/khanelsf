<?php

namespace App\Services;

use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

final class InternalLinkSearchService
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    public function __construct(
        private readonly InternalLinkSearchRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Backward-compatible Rich Text output.
     *
     * @return array<int, array{
     *     title: string,
     *     type: string,
     *     url: string,
     *     subtitle: string,
     *     target_key: string,
     *     reference_id: int
     * }>
     */
    public function search(string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        return array_map(function (InternalLinkSearchResult $result): array {
            $source = $this->registry->get($result->targetKey);

            return $result->toLegacyArray($source?->label() ?? $result->targetKey);
        }, $this->searchResults($query, $limit));
    }

    /**
     * Reference-based API for future Action Picker consumers.
     *
     * @return array<int, InternalLinkSearchResult>
     */
    public function searchResults(
        string $query,
        int $limit = self::DEFAULT_LIMIT,
    ): array {
        $query = trim(Str::squish($query));

        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min($limit, self::MAX_LIMIT));
        $results = [];
        $seen = [];

        foreach ($this->registry->all() as $source) {
            if (count($results) >= $limit) {
                break;
            }

            try {
                if (! $source->isAvailable()) {
                    continue;
                }

                $sourceResults = $source->search($query, $limit - count($results));

                foreach ($sourceResults as $result) {
                    if (! $result instanceof InternalLinkSearchResult
                        || $result->targetKey !== $source->key()) {
                        continue;
                    }

                    $identity = $result->targetKey.':'.$result->referenceId;

                    if (isset($seen[$identity])) {
                        continue;
                    }

                    $seen[$identity] = true;
                    $results[] = $result;

                    if (count($results) >= $limit) {
                        break;
                    }
                }
            } catch (Throwable $exception) {
                $this->logger->error('Internal link search source failed.', [
                    'source_key' => $source->key(),
                    'exception' => $exception,
                ]);
            }
        }

        return $results;
    }
}
