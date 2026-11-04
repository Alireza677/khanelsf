<?php

namespace App\Media;

use App\CMS\Blocks\BlockRegistry;
use App\Models\Media;

final class BlockMediaReferences
{
    private array $contracts = [
        'text_image' => ['content.media_id' => null],
        'template_shop_complete' => ['background_media_id' => null, 'all_categories_media_id' => null],
        'hero' => ['content.media.source_id' => 'content.media.url', 'content.media.poster_source_id' => 'content.media.poster_url'],
    ];

    public function __construct(private readonly BlockRegistry $blocks) {}

    public function register(string $blockType, array $references): void
    {
        $this->contracts[$blockType] = $references;
    }

    public function referenceIds(array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $data = $block['data'] ?? $block;
            $paths = array_keys($this->contracts[$block['type'] ?? ''] ?? []);
            foreach ($paths as $path) {
                $id = data_get($data, $path);
                if (is_numeric($id) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
            if (($block['type'] ?? '') === 'site_footer' && is_array($data)) {
                $this->walk($data, '', function ($path, $value) use (&$ids): void {
                    if (in_array(last(explode('.', $path)), ['media_id', 'logo_media_id'], true) && is_numeric($value) && (int) $value > 0) {
                        $ids[] = (int) $value;
                    }
                });
            }
        }

        return array_values(array_unique($ids));
    }

    /** Explicit, audited contracts. Other references are reported, never blindly rewritten. */
    public function inspect(array $blocks, Media $media): array
    {
        $found = [];
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }
            $type = $block['type'] ?? '';
            $data = $block['data'] ?? $block;
            if (! is_array($data)) {
                continue;
            }
            $base = array_key_exists('data', $block) ? "$index.data" : (string) $index;
            $label = $this->blocks->has($type) ? $this->blocks->find($type)->label() : (string) $type;
            $heading = data_get($data, 'content.title');
            if (is_string($heading) && trim(strip_tags($heading)) !== '') {
                $label .= ' · '.trim(strip_tags($heading));
            }
            $contracts = $this->contracts[$type] ?? [];
            $handled = [];
            foreach ($contracts as $path => $urlPath) {
                if ((string) data_get($data, $path) !== (string) $media->id) {
                    continue;
                }
                $supported = $urlPath === null || $this->containsUrl(data_get($data, $urlPath), $media);
                $found[] = [
                    'path' => "$base.$path", 'paired_path' => $urlPath ? "$base.$urlPath" : null,
                    'label' => 'بلوک '.$label.' · '.match ($path) {
                        'background_media_id' => 'تصویر پس‌زمینه',
                        'all_categories_media_id' => 'تصویر همه دسته‌ها',
                        'content.media.poster_source_id' => 'تصویر جایگزین ویدئو',
                        default => 'تصویر اصلی',
                    }, 'supported' => $supported,
                    'identity' => [$type, $data['block_id'] ?? null, data_get($data, $path), $urlPath ? data_get($data, $urlPath) : null],
                ];
                $handled[] = $path;
                if ($supported && $urlPath) {
                    $handled[] = $urlPath;
                }
            }
            $this->walk($data, '', function (string $path, mixed $value) use (&$found, $handled, $base, $label, $media, $type): void {
                if (in_array($path, $handled, true)) {
                    return;
                }
                $key = last(explode('.', $path));
                $idReference = in_array($key, ['media_id', 'logo_media_id', 'background_media_id', 'all_categories_media_id', 'source_id', 'poster_source_id'], true)
                    && (string) $value === (string) $media->id;
                if ($idReference || $this->containsUrl($value, $media)) {
                    $found[] = ['path' => "$base.$path", 'paired_path' => null, 'label' => 'بلوک '.$label.' · ارجاع رسانه', 'supported' => false, 'identity' => [$type, $value]];
                }
            });
        }

        return $found;
    }

    public function containsUrl(mixed $value, Media $media): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }
        $value = html_entity_decode(str_replace('\\/', '/', $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = $media->getUrl();
        if ($value === $url) {
            return true;
        }
        // Detection only: rich text and legacy URLs require an editor-owned manual replacement.
        $path = parse_url($url, PHP_URL_PATH);
        foreach (array_filter([$url, $path]) as $candidate) {
            if (preg_match('~(?:^|[\s\x22\x27(=])'.preg_quote($candidate, '~').'(?:$|[\s\x22\x27)<>?#])~u', $value)) {
                return true;
            }
        }

        return false;
    }

    public function manualReferences(mixed $value, Media $media): array
    {
        if (! is_array($value)) {
            return $this->containsUrl($value, $media) ? ['' => $value] : [];
        }
        $found = [];
        $this->walk($value, '', function ($path, $item) use (&$found, $media): void {
            if ($this->containsUrl($item, $media)
                || (in_array(last(explode('.', $path)), ['media_id', 'hero_media_id', 'logo_media_id'], true) && (string) $item === (string) $media->id)) {
                $found[$path] = $item;
            }
        });

        return $found;
    }

    private function walk(array $data, string $prefix, callable $visit): void
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "$prefix.$key";
            if (is_array($value)) {
                $this->walk($value, $path, $visit);
            } elseif (is_scalar($value)) {
                $visit($path, $value);
            }
        }
    }
}
