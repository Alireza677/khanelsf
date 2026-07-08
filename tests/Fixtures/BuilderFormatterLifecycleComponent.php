<?php

namespace Tests\Fixtures;

use Filament\Forms\Components\Builder;

class BuilderFormatterLifecycleComponent extends BuilderLifecycleComponent
{
    protected function builder(): Builder
    {
        return parent::builder()->formatStateUsing(function (?array $state): array {
            foreach ($state ?? [] as &$item) {
                if (isset($item['data']['title'])) {
                    $item['data']['content']['title'] = $item['data']['title'];
                    unset($item['data']['title']);
                }
            }

            return $state ?? [];
        });
    }
}
