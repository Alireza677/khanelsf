<?php

namespace Tests\Feature;

use Tests\TestCase;

class HorizontalOverflowContractTest extends TestCase
{
    public function test_public_forms_use_a_safe_honeypot_without_large_negative_positioning(): void
    {
        $form = file_get_contents(resource_path('views/forms/_form.blade.php'));
        $contact = file_get_contents(resource_path('views/contact.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('class="form-honeypot"', $form);
        $this->assertStringContainsString('name="website"', $form);
        $this->assertStringContainsString('tabindex="-1"', $form);
        $this->assertStringContainsString('class="form-honeypot"', $contact);
        $this->assertStringNotContainsString('left: -9999px', $form.$contact);
        $this->assertMatchesRegularExpression('/\.form-honeypot\s*\{[^}]*clip-path:\s*inset\(50%\)[^}]*overflow:\s*hidden[^}]*position:\s*absolute[^}]*width:\s*1px/s', $css);
    }

    public function test_mobile_industrial_header_panel_is_anchored_without_multi_viewport_width(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString("html,\n.services-dashboard", $css);
        $this->assertDoesNotMatchRegularExpression('/body\s*\{[^}]*overflow-x:\s*(?:hidden|clip)/s', $css);

        preg_match_all('/\.industrial-header__panel\s*\{(?<rules>[^}]*)\}/s', $css, $matches);
        $mobileRules = collect($matches['rules'])
            ->first(fn (string $rules): bool => str_contains($rules, 'position: fixed'));

        $this->assertNotNull($mobileRules);
        $this->assertStringContainsString('inset-inline: 0', $mobileRules);
        $this->assertStringContainsString('max-width: 100%', $mobileRules);
        $this->assertStringContainsString('min-width: 0', $mobileRules);
        $this->assertStringContainsString('width: auto', $mobileRules);
        $this->assertStringNotContainsString('width: 100vw', $mobileRules);
        $this->assertStringNotContainsString('width: 400%', $mobileRules);
    }
}
