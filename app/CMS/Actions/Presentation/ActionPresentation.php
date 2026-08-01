<?php

namespace App\CMS\Actions\Presentation;

use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\CoreActionType;

final class ActionPresentation
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function present(ResolvedAction $action, array $context = []): ?array
    {
        if (! $action->shouldRender()) {
            return null;
        }

        if ($action->actionType !== CoreActionType::Form->value) {
            return [
                'kind' => 'link',
                'href' => $action->url,
                'target' => $action->target,
                'rel' => $action->rel,
                'prevent_default' => $action->actionType === CoreActionType::CustomUrl->value
                    && $action->url === '#',
            ];
        }

        return [
            'kind' => 'form',
            'action' => $action->url,
            'modal_url' => $action->metadata['modal_url'] ?? null,
            'page_url' => $action->metadata['page_url'] ?? null,
            'context' => [
                'page_id' => $context['page_id'] ?? null,
                'page_url' => $context['page_url'] ?? null,
                'block_id' => $context['block_id'] ?? null,
            ],
        ];
    }
}
