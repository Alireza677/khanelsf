<?php

namespace App\CMS\Blocks\Service;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;

final class ServiceHeaderRuntime
{
    public function __construct(
        private readonly ServiceHeaderBlock $block,
        private readonly RuntimeActionResolver $resolver,
        private readonly ActionPresentation $presentation,
    ) {}

    public function prepare(array $data, array $context = [], bool $preview = false): array
    {
        $normalized = $this->block->normalize($data);
        $content = is_array($context['content'] ?? null) ? $context['content'] : [];
        $featured = is_array(data_get($context, 'media.featured')) ? data_get($context, 'media.featured') : [];
        $settings = $normalized['settings'];

        return [
            'eyebrow' => $settings['variant'] === 'modern-split' ? 'خدمت تخصصی' : null,
            'icon' => $this->text($content['icon'] ?? null),
            'title' => $this->text($content['name'] ?? null),
            'heading_tag' => $settings['heading_tag'],
            'description' => $settings['show_excerpt'] ? $this->text($content['excerpt'] ?? null) : null,
            'image' => $settings['show_image'] && filled($featured['url'] ?? null) ? [
                'url' => $featured['url'],
                'alt' => $this->text($featured['name'] ?? null) ?? $this->text($content['name'] ?? null),
                'loading' => 'eager',
            ] : null,
            'primary_action' => $this->action($settings['primary_action'], $normalized, $context, $preview),
            'secondary_action' => $this->action($settings['secondary_action'], $normalized, $context, $preview),
            'meta_items' => [],
            'variant' => $settings['variant'],
            'alignment' => $settings['alignment'],
            'image_position' => $settings['image_position'],
        ];
    }

    private function action(array $action, array $block, array $context, bool $preview): ?array
    {
        $label = $this->text($action['label'] ?? null);
        if ($label === null || ! is_array($action['action'] ?? null)) {
            return null;
        }

        $resolved = $this->resolver->resolve(
            ActionDestination::fromArray($action['action']),
            new ResolutionContext($preview ? ResolutionMode::Preview : ResolutionMode::Production),
        );
        $presentation = $this->presentation->present($resolved, [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? null,
            'block_id' => $block['block_id'],
        ]);

        return $presentation ? ['label' => $label, 'presentation' => $presentation] : null;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
