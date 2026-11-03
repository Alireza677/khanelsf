<?php

namespace App\CMS\Blocks\SiteFooter;

use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Footer-owned data contract only: no registry, queries, ID generation, or rendering. */
final class SiteFooterCompositionNormalizer
{
    public const MAX_COLUMNS = 8;

    public const MAX_ELEMENTS_PER_COLUMN = 12;

    public const MAX_ELEMENTS = 48;

    public const MAIN_TYPES = ['branding', 'navigation', 'contact', 'social_links', 'image', 'map', 'rich_text', 'form'];

    public const BOTTOM_TYPES = ['copyright', 'legal_links'];

    private const CONTACT_FIELDS = ['phone', 'mobile', 'email', 'address', 'working_hours'];

    public function __construct(
        private readonly ActionDestinationNormalizer $actions,
        private readonly ActionDestinationValidator $validator,
    ) {}

    public function normalize(mixed $value): array
    {
        $path = 'content.composition';
        $composition = $this->object($value, ['main', 'bottom'], $path);
        $main = $this->object($composition['main'] ?? null, ['columns'], $path.'.main');
        $bottom = $this->object($composition['bottom'] ?? null, ['elements'], $path.'.bottom');
        $columns = $this->items($main['columns'] ?? null, self::MAX_COLUMNS, $path.'.main.columns');
        $seen = [];
        $count = 0;
        $normalized = [];

        foreach ($columns as $index => $value) {
            $columnPath = $path.'.main.columns.'.$index;
            $column = $this->object($value, ['id', 'title', 'settings', 'elements'], $columnPath);
            $settings = $this->object($column['settings'] ?? [], ['span'], $columnPath.'.settings');
            $span = $this->integer($settings['span'] ?? 1, 1, 8, $columnPath.'.settings.span');
            $id = $this->identity($column['id'] ?? null, $seen, $columnPath.'.id');
            $normalized[] = [
                'id' => $id,
                'title' => $this->text($column['title'] ?? null, $columnPath.'.title'),
                'settings' => ['span' => $span],
                'elements' => $this->elements($column['elements'] ?? null, self::MAIN_TYPES, self::MAX_ELEMENTS_PER_COLUMN, $seen, $count, $columnPath.'.elements'),
            ];
        }

        return [
            'main' => ['columns' => $normalized],
            'bottom' => ['elements' => $this->elements($bottom['elements'] ?? null, self::BOTTOM_TYPES, self::MAX_ELEMENTS, $seen, $count, $path.'.bottom.elements')],
        ];
    }

    public function settings(mixed $value): array
    {
        $path = 'settings';
        $settings = $this->object($value, ['container', 'spacing', 'grid'], $path);
        $grid = $this->object($settings['grid'] ?? [], ['desktop', 'tablet', 'mobile'], $path.'.grid');
        $settings = [
            'container' => $settings['container'] ?? 'default',
            'spacing' => $settings['spacing'] ?? 'comfortable',
            'grid' => [
                'desktop' => $grid['desktop'] ?? 'auto',
                'tablet' => $grid['tablet'] ?? 2,
                'mobile' => $grid['mobile'] ?? 1,
            ],
        ];
        $this->validate($settings, [
            'container' => 'required|in:default,narrow,full',
            'spacing' => 'required|in:compact,comfortable,spacious',
        ], $path);

        return [
            ...$settings,
            'grid' => [
                'desktop' => $settings['grid']['desktop'] === 'auto' ? 'auto' : $this->integer($settings['grid']['desktop'], 1, 8, $path.'.grid.desktop'),
                'tablet' => $this->integer($settings['grid']['tablet'], 1, 4, $path.'.grid.tablet'),
                'mobile' => $this->integer($settings['grid']['mobile'], 1, 2, $path.'.grid.mobile'),
            ],
        ];
    }

    private function elements(mixed $value, array $types, int $max, array &$seen, int &$count, string $path): array
    {
        $elements = [];
        foreach ($this->items($value, $max, $path) as $index => $value) {
            $elementPath = $path.'.'.$index;
            $element = $this->object($value, ['id', 'type', 'enabled', 'content'], $elementPath);
            if (! in_array($element['type'] ?? null, $types, true)) {
                $this->fail($elementPath.'.type', 'Unsupported footer element type or region. No element was removed.');
            }
            if (++$count > self::MAX_ELEMENTS) {
                $this->fail($path, 'The footer may contain at most 48 elements.');
            }

            $elements[] = [
                'id' => $this->identity($element['id'] ?? null, $seen, $elementPath.'.id'),
                'type' => $element['type'],
                'enabled' => $this->boolean($element['enabled'] ?? true, $elementPath.'.enabled'),
                'content' => $this->content($element['content'] ?? [], $element['type'], $elementPath.'.content'),
            ];
        }

        return $elements;
    }

    private function content(mixed $value, string $type, string $path): array
    {
        $defaults = match ($type) {
            'branding' => ['source' => 'site', 'name' => null, 'logo_media_id' => null, 'logo_alt' => null],
            'navigation' => ['title' => null, 'menu_id' => null, 'depth' => 1],
            'contact' => ['title' => null, 'source' => 'site', 'fields' => self::CONTACT_FIELDS, 'overrides' => []],
            'social_links' => ['source' => 'site', 'items' => []],
            'image' => ['title' => null, 'media_id' => null, 'alt' => null, 'action' => null],
            'map' => ['mode' => 'static', 'title' => null, 'media_id' => null, 'alt' => null, 'directions_action' => null],
            'rich_text' => ['title' => null, 'source' => 'site', 'text' => null],
            'form' => ['title' => null, 'form_id' => null],
            'copyright' => ['source' => 'site', 'text' => null, 'show_year' => true],
            'legal_links' => ['items' => []],
        };
        $content = array_replace($defaults, $this->object($value, array_keys($defaults), $path));

        foreach ($content as $key => $value) {
            if (in_array($key, ['title', 'name', 'logo_alt', 'alt', 'text'], true)) {
                $content[$key] = $this->text($value, $path.'.'.$key, $key === 'text' ? 20000 : 255);
            } elseif (in_array($key, ['menu_id', 'logo_media_id', 'media_id', 'form_id'], true)) {
                $content[$key] = $this->reference($value, $path.'.'.$key);
            } elseif (in_array($key, ['action', 'directions_action'], true)) {
                $content[$key] = $this->action($value, $path.'.'.$key);
            }
        }

        if (array_key_exists('source', $content)) {
            $this->validate($content, ['source' => 'required|in:site,manual,override'], $path);
        }
        if ($type === 'navigation') {
            $content['depth'] = $this->integer($content['depth'], 1, 3, $path.'.depth');
        } elseif ($type === 'map') {
            $this->validate($content, ['mode' => 'required|in:static'], $path);
        } elseif ($type === 'copyright') {
            $content['show_year'] = $this->boolean($content['show_year'], $path.'.show_year');
        } elseif ($type === 'contact') {
            $fields = $this->items($content['fields'], count(self::CONTACT_FIELDS), $path.'.fields');
            if (count(array_unique($fields, SORT_REGULAR)) !== count($fields)) {
                $this->fail($path.'.fields', 'Contact fields must be unique.');
            }
            foreach ($fields as $field) {
                if (! in_array($field, self::CONTACT_FIELDS, true)) {
                    $this->fail($path.'.fields', 'Unsupported contact field.');
                }
            }
            $content['fields'] = $fields;
            $content['overrides'] = $this->object($content['overrides'], self::CONTACT_FIELDS, $path.'.overrides');
            foreach ($content['overrides'] as $field => $value) {
                $fieldPath = $path.'.overrides.'.$field;
                $value = $this->text($value, $fieldPath, $field === 'address' ? 2000 : 255);
                if ($value !== null && in_array($field, ['phone', 'mobile', 'email'], true)) {
                    $this->action(['type' => $field === 'email' ? 'email' : 'phone', 'value' => $value], $fieldPath);
                }
                $content['overrides'][$field] = $value;
            }
        } elseif (in_array($type, ['social_links', 'legal_links'], true)) {
            $items = [];
            foreach ($this->items($content['items'], 20, $path.'.items') as $index => $value) {
                $itemPath = $path.'.items.'.$index;
                $item = $this->object($value, $type === 'social_links' ? ['label', 'icon', 'action'] : ['label', 'action'], $itemPath);
                $normalized = ['label' => $this->text($item['label'] ?? null, $itemPath.'.label')];
                if ($type === 'social_links') {
                    $icon = $this->text($item['icon'] ?? null, $itemPath.'.icon', 100);
                    if ($icon !== null && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $icon) !== 1) {
                        $this->fail($itemPath.'.icon', 'Use an icon identifier, not HTML or SVG.');
                    }
                    $normalized['icon'] = $icon;
                }
                $items[] = [...$normalized, 'action' => $this->action($item['action'] ?? null, $itemPath.'.action')];
            }
            $content['items'] = $items;
        }

        return $content;
    }

    private function identity(mixed $value, array &$seen, string $path): string
    {
        if (! is_string($value) || preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/iD', $value) !== 1) {
            $this->fail($path, 'A valid stable ULID is required. IDs are only created by explicit editor actions.');
        }
        $id = strtoupper($value);
        if (isset($seen[$id])) {
            $this->fail($path, 'Column and element IDs must be unique within the footer.');
        }
        $seen[$id] = true;

        return $id;
    }

    private function action(mixed $value, string $path): ?array
    {
        if ($value === null) {
            return null;
        }
        $value = $this->object($value, ['schema_version', 'type', 'reference_id', 'value', 'display', 'open_in_new_tab'], $path);
        if (array_key_exists('schema_version', $value) && ! in_array($value['schema_version'], [1, '1'], true)) {
            $this->fail($path.'.schema_version', 'Unsupported action schema version.');
        }
        $destination = $this->actions->normalize($value);
        if (! $this->validator->validate($destination)->isValid()) {
            $this->fail($path, 'Invalid or unsupported action destination. The action was not removed.');
        }

        return $destination->toArray();
    }

    private function reference(mixed $value, string $path): ?int
    {
        if ($value === null) {
            return null;
        }
        if ((! is_int($value) && (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1))
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->fail($path, 'A positive integer reference or null is required.');
        }

        return (int) $value;
    }

    private function integer(mixed $value, int $min, int $max, string $path): int
    {
        $integer = $this->reference($value, $path);
        if ($integer === null || $integer < $min || $integer > $max) {
            $this->fail($path, 'An integer between '.$min.' and '.$max.' is required.');
        }

        return $integer;
    }

    private function text(mixed $value, string $path, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > $max) {
            $this->fail($path, 'A text value of at most '.$max.' characters or null is required.');
        }

        return trim($value) !== '' ? trim($value) : null;
    }

    private function boolean(mixed $value, string $path): bool
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (! is_scalar($value) || $boolean === null) {
            $this->fail($path, 'A boolean value is required.');
        }

        return $boolean;
    }

    private function object(mixed $value, array $keys, string $path): array
    {
        if (! is_array($value) || array_diff(array_keys($value), $keys) !== []) {
            $this->fail($path, 'Invalid footer object or unsupported fields. Data was not discarded.');
        }

        return $value;
    }

    private function items(mixed $value, int $max, string $path): array
    {
        if (! is_array($value) || count($value) > $max) {
            $this->fail($path, 'An ordered array with at most '.$max.' items is required.');
        }

        // Filament may key repeaters by UUID; iteration order, not those keys, is canonical.
        return array_values($value);
    }

    private function validate(array $data, array $rules, string $path): void
    {
        $errors = Validator::make($data, $rules)->errors()->messages();
        if ($errors !== []) {
            $messages = [];
            foreach ($errors as $field => $errors) {
                $messages[$path.'.'.$field] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
    }

    private function fail(string $path, string $message): never
    {
        throw ValidationException::withMessages([$path => $message]);
    }
}
