<?php

namespace App\Console\Commands;

use App\Services\LegacyGalleryMigrationAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class AuditLegacyGalleryMigration extends Command
{
    protected $signature = 'cms:gallery-migration:audit {--gallery= : Audit one Gallery ID} {--class= : Filter by A, B, C, or D} {--json : Emit machine-readable JSON}';
    protected $description = 'Read-only legacy Gallery classification and migration-readiness audit';

    public function handle(LegacyGalleryMigrationAuditService $audit): int
    {
        $id = $this->option('gallery');
        if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) {
            $this->error('--gallery must be a positive integer.');
            return self::INVALID;
        }
        $class = strtoupper((string) $this->option('class'));
        if ($class !== '' && ! in_array($class, ['A', 'B', 'C', 'D'], true)) {
            $this->error('--class must be A, B, C, or D.');
            return self::INVALID;
        }

        $report = $audit->audit($id === null ? null : (int) $id);
        $items = collect($report['galleries']);
        if ($class !== '') {
            $items = $items->filter(fn (array $item): bool => $class === 'D' ? $item['media_class'] === 'D' : ($item['business_class'] === $class || ($item['business_class'] === null && $item['suggested_class'] === $class)))->values();
            $report['galleries'] = $items->all();
            $report['totals'] = $audit->totals($items);
            $report['filter'] = ['class' => $class];
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }

        $this->info('Legacy Gallery Migration Audit (READ ONLY)');
        $this->line('Discovery taxonomy schema: '.($report['schema']['project_discovery_taxonomy_available'] ? 'available' : 'not available'));
        $this->newLine();
        $this->table(['Metric', 'Count'], collect($report['totals'])->map(fn ($value, $key): array => [str_replace('_', ' ', ucfirst($key)), $value])->values()->all());
        $this->table(['ID', 'Gallery', 'Business', 'Media', 'Target', 'Readiness', 'Warnings / blockers'], $items->map(fn (array $item): array => [
            $item['gallery_id'], $item['title'], $item['business_class'] ?? 'suggested '.$item['suggested_class'], $item['media_class'] ?? '-',
            $item['project']['title'] ?? '-', $item['readiness'], implode(', ', [...$item['warnings'], ...$item['blockers']]) ?: '-',
        ])->all());
        $this->line(sprintf('Stored detail URL references: %d across %d slug(s).', $report['url_references']['detail_reference_count'], $report['url_references']['referenced_detail_slugs']));
        $this->line(sprintf('Legacy templates: %d. Gallery categories: %d.', count($report['templates']), count($report['categories'])));
        $this->comment('No records, media, taxonomy, SEO fields, redirects, templates, or routes were changed.');

        return self::SUCCESS;
    }
}
