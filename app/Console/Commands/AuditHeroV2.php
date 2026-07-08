<?php

namespace App\Console\Commands;

use App\CMS\Blocks\Hero\HeroV2AuditService;
use Illuminate\Console\Command;

final class AuditHeroV2 extends Command
{
    protected $signature = 'cms:hero-v2:audit {--json : Emit machine-readable JSON}';

    protected $description = 'Audit Page and Template Hero blocks for v2 migration readiness without writing data';

    public function handle(HeroV2AuditService $audit): int
    {
        $report = $audit->audit();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['rollout_ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info('Hero v2 Audit');
        $this->table(['Metric', 'Count'], [
            ['Total Hero blocks', $report['total']],
            ['Legacy Hero blocks', $report['versions']['legacy']],
            ['v2 Hero blocks', $report['versions']['v2']],
            ['Unknown version Hero blocks', $report['versions']['unknown']],
            ['Ready', $report['ready']],
            ['Warnings', $report['warnings']],
            ['Critical', $report['critical']],
        ]);
        $this->table(['Template', 'Count'], collect($report['templates'])->map(fn ($count, $template): array => [$template, $count])->values()->all());

        if ($report['issue_counts'] !== []) {
            $this->table(['Issue', 'Count'], collect($report['issue_counts'])->sortKeys()->map(fn ($count, $issue): array => [$issue, $count])->values()->all());
        }

        if ($report['issues'] !== []) {
            $this->table(['Source', 'Record', 'Block', 'Severity', 'Issue'], collect($report['issues'])->take(20)->map(fn (array $issue): array => [
                $issue['source'], $issue['record_id'], $issue['block_index'], $issue['severity'], $issue['code'],
            ])->all());
        }

        $this->line("Processed {$report['total']} Hero blocks with {$report['query_count']} queries in {$report['duration_ms']} ms.");
        $report['rollout_ready']
            ? $this->components->info('ROLLOUT READY')
            : $this->components->error('ROLLOUT BLOCKED');

        return $report['rollout_ready'] ? self::SUCCESS : self::FAILURE;
    }
}
