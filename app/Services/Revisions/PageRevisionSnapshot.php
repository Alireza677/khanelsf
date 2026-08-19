<?php

namespace App\Services\Revisions;

use App\Models\Page;

final class PageRevisionSnapshot
{
    public const FIELDS = [
        'title',
        'slug',
        'content',
        'blocks',
        'template',
        'seo_title',
        'seo_description',
        'seo_image',
        'seo_keywords',
        'robots_index',
        'robots_follow',
    ];

    public function fromPage(Page $page): array
    {
        return $this->canonical($page->only(self::FIELDS));
    }

    public function fromEditorData(array $data): array
    {
        return $this->canonical(array_intersect_key($data, array_flip(self::FIELDS)));
    }

    public function checksum(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function canonical(array $snapshot): array
    {
        $canonical = [];

        foreach (self::FIELDS as $field) {
            $canonical[$field] = $snapshot[$field] ?? null;
        }

        $canonical['blocks'] = array_values(is_array($canonical['blocks']) ? $canonical['blocks'] : []);
        $canonical['robots_index'] = (bool) $canonical['robots_index'];
        $canonical['robots_follow'] = (bool) $canonical['robots_follow'];

        return $canonical;
    }
}
