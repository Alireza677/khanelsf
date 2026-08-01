<?php

namespace Tests\Unit\InternalLinks;

use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use App\Services\InternalLinkSearchService;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

class InternalLinkSearchServiceTest extends TestCase
{
    public function test_it_normalizes_query_and_limits_and_deduplicates_by_reference(): void
    {
        $source = new RecordingSearchSource;
        $service = $this->service([$source]);

        $results = $service->searchResults('  target   query  ', 500);
        $limited = $service->searchResults('target query', 0);

        $this->assertSame([
            ['query' => 'target query', 'limit' => 50],
            ['query' => 'target query', 'limit' => 1],
        ], $source->calls);
        $this->assertCount(1, $results);
        $this->assertCount(1, $limited);
        $this->assertSame('page:12', $results[0]->targetKey.':'.$results[0]->referenceId);
    }

    public function test_short_query_returns_no_results_without_calling_sources(): void
    {
        $source = new RecordingSearchSource;
        $service = $this->service([$source]);

        $this->assertSame([], $service->searchResults(' x '));
        $this->assertNull($source->query);
    }

    public function test_source_exception_and_malformed_output_are_isolated_and_logged(): void
    {
        $logger = new RecordingInternalLinkLogger;
        $healthy = new RecordingSearchSource;
        $service = $this->service([
            new ThrowingSearchSource,
            new MismatchedSearchSource,
            $healthy,
        ], $logger);

        $results = $service->search('target');

        $this->assertCount(1, $results);
        $this->assertSame('page', $results[0]['target_key']);
        $this->assertSame(12, $results[0]['reference_id']);
        $this->assertSame('برگه', $results[0]['type']);
        $this->assertCount(1, $logger->records);
        $this->assertSame('broken', $logger->records[0]['context']['source_key']);
        $this->assertArrayNotHasKey('exception', $results[0]);
    }

    /**
     * @param  array<int, InternalLinkSearchSource>  $sources
     */
    private function service(
        array $sources,
        ?RecordingInternalLinkLogger $logger = null,
    ): InternalLinkSearchService {
        $registry = new InternalLinkSearchRegistry;

        foreach ($sources as $source) {
            $registry->register($source);
        }

        return new InternalLinkSearchService(
            $registry,
            $logger ?? new RecordingInternalLinkLogger,
        );
    }
}

final class RecordingSearchSource implements InternalLinkSearchSource
{
    public array $calls = [];

    public ?string $query = null;

    public ?int $limit = null;

    public function key(): string
    {
        return 'page';
    }

    public function label(): string
    {
        return 'برگه';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $query, int $limit): array
    {
        $this->query = $query;
        $this->limit = $limit;
        $this->calls[] = compact('query', 'limit');
        $result = new InternalLinkSearchResult(
            'page',
            12,
            'Target',
            '/target',
            '/target',
        );

        return [$result, $result];
    }
}

final class ThrowingSearchSource implements InternalLinkSearchSource
{
    public function key(): string
    {
        return 'broken';
    }

    public function label(): string
    {
        return 'Broken';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $query, int $limit): array
    {
        throw new RuntimeException('SQLSTATE private table data');
    }
}

final class MismatchedSearchSource implements InternalLinkSearchSource
{
    public function key(): string
    {
        return 'mismatch';
    }

    public function label(): string
    {
        return 'Mismatch';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $query, int $limit): array
    {
        return [
            new InternalLinkSearchResult('other', 4, 'Other', '/other', '/other'),
        ];
    }
}

final class RecordingInternalLinkLogger extends AbstractLogger
{
    public array $records = [];

    public function log(
        mixed $level,
        Stringable|string $message,
        array $context = [],
    ): void {
        $this->records[] = compact('level', 'message', 'context');
    }
}
