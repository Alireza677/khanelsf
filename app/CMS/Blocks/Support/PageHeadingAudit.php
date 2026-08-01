<?php

namespace App\CMS\Blocks\Support;

final class PageHeadingAudit
{
    /**
     * @return array<int, array{index: int, type: string}>
     */
    public function h1Blocks(array $blocks): array
    {
        $matches = [];

        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : $block;
            $type = is_string($block['type'] ?? null) ? $block['type'] : 'unknown';
            $level = HeadingLevel::normalize(
                data_get($data, 'settings.heading_tag', data_get($data, 'heading_tag')),
            );

            if ($level !== 'h1' || ! $this->hasVisibleTitle($type, $data)) {
                continue;
            }

            $matches[] = ['index' => $index, 'type' => $type];
        }

        return $matches;
    }

    public function hasMultipleH1(array $blocks): bool
    {
        return count($this->h1Blocks($blocks)) > 1;
    }

    private function hasVisibleTitle(string $type, array $data): bool
    {
        foreach ([
            'content.title',
            'content.section_title',
            'settings.title',
            'title',
            'section_title',
        ] as $path) {
            $value = data_get($data, $path);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        if ($type === 'form') {
            return is_numeric(data_get($data, 'content.form_id'))
                && (int) data_get($data, 'content.form_id') > 0;
        }

        return in_array($type, [
            'product_header',
            'project_header',
            'service_header',
            'template_archive_header',
            'template_shop_complete',
            'template_single_header',
        ], true);
    }
}
