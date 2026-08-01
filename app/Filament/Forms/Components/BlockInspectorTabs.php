<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Component;

final class BlockInspectorTabs
{
    public const CONTENT = 'content';

    public const DESIGN = 'design';

    public const ADVANCED = 'advanced';

    /**
     * Presentation-only grouping. Components are returned unchanged, so their
     * state paths, hydration, validation, and dehydration remain owned by the
     * original Filament Builder block container.
     *
     * @return array<string, array<Component>>
     */
    public static function components(ComponentContainer $container): array
    {
        $groups = [
            self::CONTENT => [],
            self::DESIGN => [],
            self::ADVANCED => [],
        ];

        foreach ($container->getComponents(withHidden: true) as $component) {
            $groups[self::groupFor($component)][] = $component;
        }

        return $groups;
    }

    private static function groupFor(Component $component): string
    {
        $names = self::stateNames($component);

        if ($names === []) {
            return self::CONTENT;
        }

        $scores = [
            self::CONTENT => 0,
            self::DESIGN => 0,
            self::ADVANCED => 0,
        ];

        foreach ($names as $name) {
            $scores[self::groupForName($name)]++;
        }

        $highest = max($scores);

        foreach ([self::ADVANCED, self::DESIGN, self::CONTENT] as $group) {
            if ($scores[$group] === $highest) {
                return $group;
            }
        }

        return self::CONTENT;
    }

    /** @return array<string> */
    private static function stateNames(Component $component): array
    {
        if (method_exists($component, 'getName')) {
            $name = $component->getName();

            if (is_string($name) && $name !== '') {
                return [$name];
            }
        }

        if (! method_exists($component, 'getChildComponents')) {
            return [];
        }

        $names = [];

        foreach ($component->getChildComponents() as $child) {
            if ($child instanceof Component) {
                $names = [...$names, ...self::stateNames($child)];
            }
        }

        return array_values(array_unique($names));
    }

    private static function groupForName(string $name): string
    {
        $name = strtolower($name);

        if (preg_match('/(^|\.)(block_id|schema_version|anchor|anchor_id|visibility|tracking|analytics|custom_attributes|css_id|css_class|code|context_note)$/', $name)) {
            return self::ADVANCED;
        }

        if (in_array($name, ['settings.title', 'settings.description'], true)
            || str_starts_with($name, 'content.')) {
            return self::CONTENT;
        }

        if (str_starts_with($name, 'settings.')
            || preg_match('/(^|_)(template|layout|theme|style|alignment|animation|spacing|background|overlay|container|columns|heading_tag)(_|$)/', $name)
            || preg_match('/(_width|_height|_fit|_unit|_size|_opacity|_radius|_position|_aspect_ratio)(_|$)/', $name)) {
            return self::DESIGN;
        }

        return self::CONTENT;
    }
}
