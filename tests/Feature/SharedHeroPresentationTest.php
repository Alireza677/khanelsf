<?php

namespace Tests\Feature;

use App\CMS\Blocks\Service\ServiceHeaderBlock;
use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class SharedHeroPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_header_has_one_canonical_variant_without_admin_selector(): void
    {
        $block = app(ServiceHeaderBlock::class);
        $normalizeLegacy = $block->normalize(['settings' => ['variant' => 'split']]);
        $normalizeUnknown = $block->normalize(['settings' => ['variant' => 'legacy-value']]);
        $schema = new ReflectionMethod($block, 'schema');
        $fields = collect($schema->invoke($block));

        $this->assertSame('modern-split', $normalizeLegacy['settings']['variant']);
        $this->assertSame('modern-split', $normalizeUnknown['settings']['variant']);
        $this->assertFalse($fields->contains(fn ($field): bool => method_exists($field, 'getName')
            && $field->getName() === 'settings.variant'));
    }

    public function test_shared_presentation_omits_empty_optional_regions(): void
    {
        $html = view('partials.presentations.hero', ['hero' => [
            'title' => 'فقط عنوان',
            'variant' => 'modern-split',
        ]])->render();

        $this->assertStringContainsString('فقط عنوان', $html);
        $this->assertStringContainsString('shared-hero--modern-split', $html);
        $this->assertStringNotContainsString('shared-hero__icon', $html);
        $this->assertStringNotContainsString('shared-hero__media', $html);
        $this->assertStringNotContainsString('shared-hero__actions', $html);
        $this->assertStringNotContainsString('shared-hero__meta', $html);
    }

    public function test_shared_presentation_renders_image_icon_actions_and_meta(): void
    {
        $html = view('partials.presentations.hero', ['hero' => [
            'title' => 'عنوان',
            'description' => 'شرح',
            'icon' => 'briefcase',
            'image' => ['url' => '/hero.jpg', 'alt' => 'تصویر'],
            'primary_action' => ['label' => 'اصلی', 'presentation' => ['kind' => 'link', 'href' => '/one']],
            'secondary_action' => ['label' => 'دوم', 'presentation' => ['kind' => 'link', 'href' => '/two']],
            'meta_items' => [['label' => 'موقعیت', 'value' => 'تهران']],
        ]])->render();

        $this->assertStringContainsString('/hero.jpg', $html);
        $this->assertStringContainsString('href="/one"', $html);
        $this->assertStringContainsString('href="/two"', $html);
        $this->assertStringContainsString('تهران', $html);
    }

    public function test_modern_split_groups_icon_with_eyebrow_without_affecting_default_markup(): void
    {
        $modern = view('partials.presentations.hero', ['hero' => [
            'title' => 'خدمت', 'eyebrow' => 'خدمت تخصصی', 'icon' => 'briefcase', 'variant' => 'modern-split',
        ]])->render();
        $default = view('partials.presentations.hero', ['hero' => [
            'title' => 'صفحه', 'eyebrow' => 'برچسب', 'icon' => 'briefcase', 'variant' => 'default',
        ]])->render();

        $this->assertStringContainsString('shared-hero__eyebrow-line', $modern);
        $this->assertMatchesRegularExpression('/shared-hero__eyebrow-line[\s\S]*shared-hero__icon[\s\S]*خدمت تخصصی[\s\S]*<\/div>/', $modern);
        $this->assertStringNotContainsString('shared-hero__eyebrow-line', $default);
    }

    public function test_modern_split_eyebrow_line_handles_each_optional_value_independently(): void
    {
        $eyebrowOnly = view('partials.presentations.hero', ['hero' => [
            'title' => 'خدمت', 'eyebrow' => 'خدمت تخصصی', 'variant' => 'modern-split',
        ]])->render();
        $iconOnly = view('partials.presentations.hero', ['hero' => [
            'title' => 'خدمت', 'icon' => 'briefcase', 'variant' => 'modern-split',
        ]])->render();

        $this->assertStringContainsString('shared-hero__eyebrow-line', $eyebrowOnly);
        $this->assertStringNotContainsString('shared-hero__icon', $eyebrowOnly);
        $this->assertStringContainsString('shared-hero__eyebrow-line', $iconOnly);
        $this->assertStringNotContainsString('shared-hero__eyebrow">', $iconOnly);
    }

    public function test_service_header_uses_canonical_action_and_drops_invalid_action(): void
    {
        $context = ['content' => ['name' => 'خدمت', 'excerpt' => 'شرح', 'icon' => null], 'media' => ['featured' => null]];
        $valid = view('partials.blocks.service_header', [
            'data' => ['settings' => [
                'variant' => 'modern-split',
                'primary_action' => ['label' => 'تماس', 'action' => ['schema_version' => 1, 'type' => 'custom_url', 'value' => '/contact']],
            ]],
            'context' => $context,
        ])->render();
        $invalid = view('partials.blocks.service_header', [
            'data' => ['settings' => ['primary_action' => ['label' => 'خراب', 'action' => ['schema_version' => 1, 'type' => 'page', 'reference_id' => 999999]]]],
            'context' => $context,
        ])->render();

        $this->assertStringContainsString('href="/contact"', $valid);
        $this->assertStringContainsString('shared-hero--modern-split', $valid);
        $this->assertStringContainsString('shared-hero--image-end', $valid);
        $this->assertStringContainsString('خدمت', $invalid);
        $this->assertStringNotContainsString('خراب', $invalid);
    }

    public function test_service_normalizer_preserves_canonical_reference(): void
    {
        $action = ['schema_version' => 1, 'type' => 'service', 'reference_id' => 42];
        $normalized = app(ServiceHeaderBlock::class)->normalize([
            'settings' => ['primary_action' => ['label' => 'جزئیات', 'action' => $action]],
        ]);

        $this->assertSame($action, $normalized['settings']['primary_action']['action']);
    }

    public function test_page_default_hero_keeps_legacy_urls_while_using_shared_presentation(): void
    {
        $html = view('partials.blocks.hero', ['data' => [
            'title' => 'Hero قدیمی',
            'primary_button_label' => 'ادامه',
            'primary_button_url' => '/legacy',
        ]])->render();

        $this->assertStringContainsString('shared-hero', $html);
        $this->assertStringContainsString('href="/legacy"', $html);
    }

    public function test_published_service_template_can_save_header_actions(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $template = Template::query()->create([
            'title' => 'Service template', 'slug' => 'service-template', 'type' => 'service_single',
            'status' => 'published', 'is_default' => true, 'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'service_header', 'data' => [
                'block_id' => 'service-header-test', 'schema_version' => 1, 'template' => 'default',
                'content' => [], 'settings' => ['show_excerpt' => true, 'show_image' => true, 'alignment' => 'start', 'variant' => 'modern-split', 'heading_tag' => 'h1'],
            ]]],
        ]);
        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $key = array_key_first($component->get('data')['blocks']);

        $component
            ->set("data.blocks.{$key}.data.settings.primary_action.label", 'تماس')
            ->set("data.blocks.{$key}.data.settings.primary_action.action.type", 'custom_url')
            ->set("data.blocks.{$key}.data.settings.primary_action.action.value", '#')
            ->set("data.blocks.{$key}.data.settings.secondary_action.label", 'درخواست مشاوره')
            ->set("data.blocks.{$key}.data.settings.secondary_action.action.type", 'phone')
            ->set("data.blocks.{$key}.data.settings.secondary_action.action.value", '۰۹۱۲۳۴۵۶۷۸۹')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('custom_url', data_get($template->fresh()->blocks, '0.data.settings.primary_action.action.type'));
        $this->assertSame('phone', data_get($template->fresh()->blocks, '0.data.settings.secondary_action.action.type'));
        $this->assertSame('09123456789', data_get($template->fresh()->blocks, '0.data.settings.secondary_action.action.value'));
    }

    public function test_full_service_recipe_can_publish_and_save_two_placeholder_links(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $template = app(TemplateRecipeInstantiator::class)->createDraft('service-professional-v1', [
            'title' => 'Full service template', 'slug' => 'full-service-template', 'conditions' => ['type' => 'all'],
        ]);
        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $blocks = $component->get('data')['blocks'];
        $key = collect($blocks)->search(fn (array $block): bool => $block['type'] === 'service_header');

        $component
            ->set('data.status', 'published')
            ->set("data.blocks.{$key}.data.settings.primary_action.label", 'dfdfdf')
            ->set("data.blocks.{$key}.data.settings.primary_action.action.type", 'custom_url')
            ->set("data.blocks.{$key}.data.settings.primary_action.action.value", '#')
            ->set("data.blocks.{$key}.data.settings.secondary_action.label", 'ffffffff')
            ->set("data.blocks.{$key}.data.settings.secondary_action.action.type", 'custom_url')
            ->set("data.blocks.{$key}.data.settings.secondary_action.action.value", '#')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('published', $template->fresh()->status);
    }
}
