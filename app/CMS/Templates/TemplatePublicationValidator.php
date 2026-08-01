<?php

namespace App\CMS\Templates;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\Models\Template;

final class TemplatePublicationValidator
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly CTADataNormalizer $ctas,
    ) {}

    /** @return array<string> */
    public function validate(array $attributes): array
    {
        $errors = [];
        $target = is_string($attributes['type'] ?? null) ? $attributes['type'] : '';
        $templateBlocks = is_array($attributes['blocks'] ?? null) ? $attributes['blocks'] : [];

        if (! array_key_exists($target, Template::TYPES)) {
            $errors[] = "Template target [{$target}] is not supported.";
        }

        if ($templateBlocks === []) {
            $errors[] = 'A published template must contain at least one block.';
        }

        $blockIds = [];

        foreach ($templateBlocks as $position => $block) {
            $type = is_array($block) && is_string($block['type'] ?? null)
                ? $block['type']
                : '';
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if ($type === '' || ! $this->blocks->has($type)) {
                $errors[] = "Template block at position [{$position}] is not registered.";

                continue;
            }

            $definition = $this->blocks->find($type);
            $expectedContext = $this->contextCapabilityFor($target);
            $entityContexts = array_intersect(
                $definition->capabilities(),
                ['project_context', 'product_context', 'service_context'],
            );

            if ($entityContexts !== [] && ! in_array($expectedContext, $entityContexts, true)) {
                $errors[] = "Block [{$type}] is not compatible with target [{$target}].";
            }

            $blockId = is_scalar($data['block_id'] ?? null)
                ? trim((string) $data['block_id'])
                : '';

            if ($blockId === '') {
                $errors[] = "Block [{$type}] requires a block_id.";
            } elseif (in_array($blockId, $blockIds, true)) {
                $errors[] = "Block id [{$blockId}] must be unique.";
            } else {
                $blockIds[] = $blockId;
            }

            if ((int) ($data['schema_version'] ?? 0) !== $definition->version()) {
                $errors[] = "Block [{$type}] schema version must equal [{$definition->version()}].";
            }

            $template = $data['template'] ?? null;

            if (! is_string($template) || ! array_key_exists($template, $definition->templates())) {
                $errors[] = "Block [{$type}] uses an unsupported renderer template.";
            } elseif (! view()->exists($definition->templates()[$template]->view)) {
                $errors[] = "Block [{$type}] renderer view is missing.";
            }

            $normalizer = $definition instanceof BlockNormalizer
                ? $definition
                : ($type === 'cta' ? $this->ctas : null);

            if ($normalizer && $normalizer->normalize($data) !== $data) {
                $errors[] = "Block [{$type}] data is not canonical for the installed schema.";
            }
        }

        return array_values(array_unique($errors));
    }

    private function contextCapabilityFor(string $target): ?string
    {
        return match ($target) {
            'project_single' => 'project_context',
            'product_single' => 'product_context',
            'service_single' => 'service_context',
            default => null,
        };
    }
}
