<?php

namespace Tests\Unit;

use App\Services\PersianPdfHtml;
use Tests\TestCase;

class PersianPdfHtmlTest extends TestCase
{
    public function test_it_shapes_persian_text_and_preserves_western_numbers(): void
    {
        $shaper = app(PersianPdfHtml::class);
        $result = $shaper->shapeText('نتیجه ارزیابی پروژه 123 متر');

        $this->assertMatchesRegularExpression('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $result);
        $this->assertStringContainsString('123', $result);
        $this->assertStringNotContainsString('نتیجه', $result);
    }

    public function test_it_shapes_only_html_text_nodes(): void
    {
        $html = <<<'HTML'
        <!doctype html><html dir="rtl"><head><style>.title { direction: rtl; }</style></head>
        <body><strong class="title">نتیجه محاسبه 42</strong></body></html>
        HTML;

        $result = app(PersianPdfHtml::class)->shape($html);

        $this->assertStringContainsString('.title { direction: rtl; }', $result);
        $this->assertStringContainsString('class="title"', $result);
        $this->assertStringContainsString('42', $result);
        $this->assertMatchesRegularExpression(
            '/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u',
            html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }
}
