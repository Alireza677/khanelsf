<?php

namespace Tests\Unit;

use App\Support\FormChoicePresentation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormChoicePresentationTest extends TestCase
{
    #[DataProvider('labels')]
    public function test_unicode_label_length_maps_to_adaptive_grid_span(
        string $label,
        string $expectedClass,
    ): void {
        $this->assertSame($expectedClass, FormChoicePresentation::sizeClass($label));
    }

    public static function labels(): array
    {
        return [
            'short boundary' => [str_repeat('آ', 25), 'choice-grid-item--short'],
            'medium lower boundary' => [str_repeat('آ', 26), 'choice-grid-item--medium'],
            'medium upper boundary' => [str_repeat('آ', 50), 'choice-grid-item--medium'],
            'long boundary' => [str_repeat('آ', 51), 'choice-grid-item--long'],
        ];
    }
}
