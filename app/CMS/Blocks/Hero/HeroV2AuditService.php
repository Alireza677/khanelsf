<?php

namespace App\CMS\Blocks\Hero;

use App\Models\Page;
use App\Models\Template;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class HeroV2AuditService
{
    private const TEMPLATES = ['default', 'hero_1', 'hero_2', 'hero_3'];

    private const LEGACY_PREFIXES = [
        'hero_1_', 'hero_2_', 'hero_3_', 'primary_button_', 'secondary_button_',
        'image_width_', 'animated_background_', 'paths_',
    ];

    public function audit(): array
    {
        $startedAt = hrtime(true);
        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });
        $media = Media::query()->where('collection_name', 'media_library')->get();
        [$mediaIds, $mediaUrls] = $this->mediaIndexes($media);
        $report = $this->emptyReport();
        $seenIds = [];

        foreach ([Page::class => 'page', Template::class => 'template'] as $model => $source) {
            $model::query()->select(['id', 'blocks'])->orderBy('id')->each(
                function ($record) use (&$report, &$seenIds, $source, $mediaIds, $mediaUrls): void {
                    $this->auditDocument($report, $seenIds, $source, $record->getKey(), $record->blocks, $mediaIds, $mediaUrls);
                },
            );
        }

        $report['duration_ms'] = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $report['query_count'] = $queryCount;
        ksort($report['issue_counts']);
        ksort($report['legacy_fields']);
        $report['rollout_ready'] = $report['critical'] === 0;
        $report['rollout_status'] = $report['rollout_ready'] ? 'ready' : 'blocked';
        $report['summary'] = [
            'records_scanned' => $report['records_scanned'],
            'hero_blocks_scanned' => $report['total'],
            'critical' => $this->findingCount($report['issues'], 'critical'),
            'warning' => $this->findingCount($report['issues'], 'warning'),
            'info' => $this->findingCount($report['issues'], 'info'),
        ];
        $report['findings'] = $report['issues'];

        return $report;
    }

    private function auditDocument(array &$report, array &$seenIds, string $source, int|string $recordId, mixed $blocks, array $mediaIds, array $mediaUrls): void
    {
        $report['records_scanned']++;

        if (! is_array($blocks)) {
            return;
        }

        foreach ($blocks as $index => $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'hero') {
                continue;
            }

            $issues = [];
            $rawData = $block['data'] ?? null;
            $data = is_array($rawData) ? $rawData : [];

            if (! is_array($rawData)) {
                $issues[] = $this->issue('unexpected_contract_violation', 'critical');
            }
            $report['total']++;
            $this->countVersion($report, $data);
            $this->countTemplate($report, $data, $issues);
            $this->inspectIdentity($data, $seenIds, $issues);
            $this->inspectContent($data, $issues);
            $this->inspectMedia($data, $mediaIds, $mediaUrls, $issues);
            $this->inspectTemplateData($data, $issues);
            if ($this->inspectLegacyFields($report, $data)) {
                $issues[] = $this->issue('legacy_fields_present', 'info');
            }

            $severity = collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'critical')
                ? 'critical'
                : (collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'warning') ? 'warnings' : 'ready');
            $report[$severity]++;

            foreach ($issues as $issue) {
                $report['issue_counts'][$issue['code']] = ($report['issue_counts'][$issue['code']] ?? 0) + 1;
                $report['issues'][] = [
                    'source' => $source,
                    'record_id' => $recordId,
                    'block_index' => $index,
                    ...$issue,
                ];
            }
        }
    }

    private function countVersion(array &$report, array $data): void
    {
        if (! array_key_exists('schema_version', $data)) {
            $report['versions']['legacy']++;

            return;
        }

        if ($this->isV2($data)) {
            $report['versions']['v2']++;

            return;
        }

        $report['versions']['unknown']++;
    }

    private function countTemplate(array &$report, array $data, array &$issues): void
    {
        $template = $data['template'] ?? null;

        if (! is_string($template) || $template === '') {
            $report['templates']['missing']++;
            $issues[] = $this->issue('missing_template', 'warning');

            return;
        }

        if (! in_array($template, self::TEMPLATES, true)) {
            $report['templates']['unknown']++;
            $issues[] = $this->issue('unknown_template', 'warning');

            return;
        }

        $report['templates'][$template]++;
    }

    private function inspectIdentity(array $data, array &$seenIds, array &$issues): void
    {
        $id = $data['block_id'] ?? null;

        if (! is_string($id) || $id === '') {
            $issues[] = $this->issue('missing_block_id', 'warning');

            return;
        }

        $canonical = strtoupper($id);

        if (preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $canonical) !== 1) {
            $issues[] = $this->issue('invalid_block_id', 'critical');

            return;
        }

        if (isset($seenIds[$canonical])) {
            $issues[] = $this->issue('duplicate_block_id', 'critical');
        }

        $seenIds[$canonical] = true;
    }

    private function inspectContent(array $data, array &$issues): void
    {
        $v2 = $this->isV2($data);
        $title = $v2 ? data_get($data, 'content.title') : ($data['title'] ?? null);

        if (! is_string($title) || trim($title) === '') {
            $issues[] = $this->issue('missing_title', 'critical');
        }

        if (array_key_exists('schema_version', $data) && ! $v2) {
            $issues[] = $this->issue('invalid_schema_version', 'critical');
        }

        foreach ($v2
            ? ['content.selector.items', 'content.stats', 'content.social_links']
            : ['selector_items', 'stats', 'hero_1_social_links'] as $path) {
            $value = data_get($data, $path);

            if ($value !== null && ! is_array($value)) {
                $issues[] = $this->issue('invalid_repeater_shape', 'critical');
            }
        }

        foreach ($v2
            ? [['content.primary_cta.label', 'content.primary_cta.url'], ['content.secondary_cta.label', 'content.secondary_cta.url']]
            : [['primary_button_label', 'primary_button_url'], ['secondary_button_label', 'secondary_button_url']] as [$labelPath, $urlPath]) {
            if (filled(data_get($data, $labelPath)) xor filled(data_get($data, $urlPath))) {
                $issues[] = $this->issue('malformed_cta', 'warning');
            }
        }
    }

    private function inspectMedia(array $data, array $mediaIds, array $mediaUrls, array &$issues): void
    {
        $v2 = $this->isV2($data);
        $imageUrl = $v2 ? data_get($data, 'content.media.url') : ($data['image'] ?? null);
        $sourceId = $v2 ? data_get($data, 'content.media.source_id') : null;
        $posterUrl = $v2 ? data_get($data, 'content.media.poster_url') : ($data['hero_2_video_poster'] ?? null);
        $posterSourceId = $v2 ? data_get($data, 'content.media.poster_source_id') : null;
        $videoUrl = $v2 ? data_get($data, 'content.media.video_url') : ($data['hero_2_video_url'] ?? null);

        $this->inspectMediaPair($imageUrl, $sourceId, 'image', $mediaIds, $mediaUrls, $issues);
        $this->inspectMediaPair($posterUrl, $posterSourceId, 'poster', $mediaIds, $mediaUrls, $issues);

        if (filled($videoUrl) && ! $this->isValidUrl($videoUrl)) {
            $issues[] = $this->issue('invalid_video_url', 'warning');
        }

        if (filled($videoUrl) && blank($posterUrl)) {
            $issues[] = $this->issue('missing_poster', 'warning');
        }
    }

    private function inspectMediaPair(mixed $url, mixed $sourceId, string $kind, array $mediaIds, array $mediaUrls, array &$issues): void
    {
        if (filled($sourceId) && ! isset($mediaIds[(string) $sourceId])) {
            $issues[] = $this->issue("invalid_{$kind}_source_id", 'warning');
        }

        if (! is_string($url) || trim($url) === '') {
            return;
        }

        $matches = $mediaUrls[$this->normalizedPath($url) ?? $url] ?? [];

        if (blank($sourceId)) {
            $issues[] = $this->issue("{$kind}_url_without_source_id", 'warning');
        }

        if (count($matches) > 1) {
            $issues[] = $this->issue("ambiguous_{$kind}_media_match", 'warning');
        }
    }

    private function inspectLegacyFields(array &$report, array $data): bool
    {
        $found = false;

        foreach (array_keys($data) as $key) {
            if (! is_string($key)) {
                continue;
            }

            foreach (self::LEGACY_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $report['legacy_fields'][$prefix] = ($report['legacy_fields'][$prefix] ?? 0) + 1;
                    $found = true;
                    break;
                }
            }
        }

        return $found;
    }

    private function inspectTemplateData(array $data, array &$issues): void
    {
        $template = $data['template'] ?? 'default';
        $unsupportedPrefixes = match ($template) {
            'hero_1' => ['hero_2_', 'hero_3_'],
            'hero_2' => ['hero_1_', 'hero_3_'],
            'hero_3' => ['hero_1_', 'hero_2_'],
            'default' => ['hero_1_', 'hero_2_', 'hero_3_'],
            default => [],
        };

        foreach (array_keys($data) as $key) {
            if (is_string($key) && collect($unsupportedPrefixes)->contains(fn (string $prefix): bool => str_starts_with($key, $prefix))) {
                $issues[] = $this->issue('unsupported_template_data', 'warning');

                return;
            }
        }
    }

    private function mediaIndexes(iterable $media): array
    {
        $ids = [];
        $urls = [];

        foreach ($media as $item) {
            $ids[(string) $item->getKey()] = true;
            $url = $item->getUrl();
            $urls[$this->normalizedPath($url) ?? $url][] = (int) $item->getKey();
        }

        return [$ids, $urls];
    }

    private function normalizedPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? '/'.ltrim(rawurldecode($path), '/') : null;
    }

    private function isValidUrl(mixed $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        return str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function issue(string $code, string $severity): array
    {
        return compact('code', 'severity');
    }

    private function isV2(array $data): bool
    {
        return ($data['schema_version'] ?? null) === HeroDataNormalizer::SCHEMA_VERSION;
    }

    private function findingCount(array $issues, string $severity): int
    {
        return count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === $severity));
    }

    private function emptyReport(): array
    {
        return [
            'generated_at' => Carbon::now('UTC')->toIso8601String(),
            'records_scanned' => 0, 'total' => 0, 'ready' => 0, 'warnings' => 0, 'critical' => 0,
            'versions' => ['legacy' => 0, 'v2' => 0, 'unknown' => 0],
            'templates' => ['default' => 0, 'hero_1' => 0, 'hero_2' => 0, 'hero_3' => 0, 'unknown' => 0, 'missing' => 0],
            'issue_counts' => [], 'legacy_fields' => [], 'issues' => [], 'duration_ms' => 0.0, 'query_count' => 0,
        ];
    }
}
