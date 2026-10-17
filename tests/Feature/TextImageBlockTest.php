<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\TextImage\TextImageDataNormalizer;
use App\Filament\Forms\Components\BlockBuilder;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TextImageBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizer_is_query_free_idempotent_and_restricts_design_values(): void
    {
        $normalizer = app(TextImageDataNormalizer::class);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $data = $normalizer->normalize([
            'block_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'content' => ['media_id' => '42', 'description' => str_repeat('<p>متن طولانی</p>', 500)],
            'settings' => ['heading_tag' => 'h1', 'image_position' => 'invalid', 'column_ratio' => ['invalid']],
        ]);

        $this->assertSame([], $queries);
        $this->assertSame($data, $normalizer->normalize($data));
        $this->assertSame(1, $data['schema_version']);
        $this->assertSame(42, $data['content']['media_id']);
        $this->assertSame('h2', $data['settings']['heading_tag']);
        $this->assertSame('left', $data['settings']['image_position']);
        $this->assertSame('equal', $data['settings']['column_ratio']);
        $this->assertSame('center', $data['settings']['vertical_alignment']);
        $this->assertSame('natural', $data['settings']['image_ratio']);
        $this->assertGreaterThan(5000, strlen($data['content']['description']));
        $this->assertSame($normalizer->normalize([]), $normalizer->normalize(['content' => 'invalid', 'settings' => false]));
    }

    public function test_block_is_available_in_both_editors_and_round_trips_with_a_stable_identity(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $page = Page::factory()->create(['blocks' => []]);
        $template = Template::query()->create([
            'title' => 'Text image template', 'slug' => 'text-image-template',
            'type' => 'service_single', 'status' => 'draft', 'blocks' => [],
        ]);
        $registry = app(BlockRegistry::class);
        $this->assertSame('متن و تصویر', $registry->find('text_image')->label());
        foreach (array_diff(array_keys(Template::TYPES), ['site_header', 'site_footer']) as $target) {
            $this->assertContains('text_image', $registry->templateKeys($target));
        }
        $this->assertNotContains('text_image', $registry->templateKeys('site_header'));
        $this->assertNotContains('text_image', $registry->templateKeys('site_footer'));

        foreach ([[EditPage::class, $page], [EditTemplate::class, $template]] as [$editorClass, $record]) {
            $editor = Livewire::test($editorClass, ['record' => $record->getRouteKey()])->assertOk();
            $builder = collect($editor->instance()->form->getFlatComponents())
                ->first(fn ($component): bool => $component instanceof BlockBuilder);
            $this->assertContains('text_image', array_map(fn ($block): string => $block->getName(), $builder->getBlockPickerBlocks()));
            $editor->callFormComponentAction('blocks', 'add', arguments: ['block' => 'text_image']);
            $key = array_key_first($editor->get('data.blocks'));
            $path = "data.blocks.{$key}.data";
            $editor->set("{$path}.content.title", 'داستان شرکت')
                ->set("{$path}.content.description", '<p>پاراگراف اول</p><p>پاراگراف دوم</p>')
                ->set("{$path}.settings.heading_tag", 'h4')
                ->set("{$path}.settings.image_position", 'right')
                ->set("{$path}.settings.column_ratio", 'text_wide')
                ->set("{$path}.settings.image_ratio", '4:3')
                ->set("{$path}.settings.vertical_alignment", 'bottom')
                ->set("{$path}.settings.section_background", 'dark')
                ->call('save')->assertHasNoFormErrors();
            $saved = $record->fresh()->blocks[0]['data'];
            $this->assertNotEmpty($saved['block_id']);
            $this->assertSame('h4', $saved['settings']['heading_tag']);
            $this->assertSame('right', $saved['settings']['image_position']);
            $this->assertSame('text_wide', $saved['settings']['column_ratio']);
            $this->assertSame('4:3', $saved['settings']['image_ratio']);
            $this->assertSame('bottom', $saved['settings']['vertical_alignment']);
            $this->assertSame('dark', $saved['settings']['section_background']);

            $reloaded = Livewire::test($editorClass, ['record' => $record->getRouteKey()])->assertOk();
            $reloaded->call('save')->assertHasNoFormErrors();
            $this->assertSame($saved, $record->fresh()->blocks[0]['data']);

            $cloneKey = array_key_first($reloaded->get('data.blocks'));
            $reloaded->callFormComponentAction('blocks', 'clone', arguments: ['item' => $cloneKey])
                ->call('save')->assertHasNoFormErrors();
            $cloned = $record->fresh()->blocks;
            $this->assertCount(2, $cloned);
            $this->assertNotSame($cloned[0]['data']['block_id'], $cloned[1]['data']['block_id']);
        }
    }

    public function test_shared_rendering_uses_library_alt_safe_rich_text_and_action_presentation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $media = Media::query()->create([
            'model_type' => User::class, 'model_id' => $user->id,
            'collection_name' => 'media_library', 'name' => 'Company', 'file_name' => 'company.png',
            'mime_type' => 'image/png', 'disk' => 'public', 'size' => 100,
            'manipulations' => [], 'custom_properties' => ['alt_text' => 'کارگاه شرکت'],
            'generated_conversions' => [], 'responsive_images' => [],
        ]);
        Storage::disk('public')->put($media->getPathRelativeToRoot(), 'test-image');
        $data = [
            'content' => [
                'title' => 'درباره شرکت', 'eyebrow' => 'داستان ما', 'media_id' => $media->id,
                'description' => '<p>اول <strong>پررنگ</strong> <em>مورب</em><br>ادامه</p><p>دوم</p><ul><li>مورد</li></ul><ol><li>یک</li></ol><p><a href="/contact">تماس</a><a href="https://example.com">بیشتر</a></p><script>alert(1)</script><p onclick="alert(1)">امن</p><a href="javascript:alert(1)">خطر</a>',
                'button' => ['label' => 'خدمات', 'action' => ['type' => 'custom_url', 'value' => '/services', 'open_in_new_tab' => true]],
            ],
            'settings' => ['heading_tag' => 'h4', 'image_position' => 'right', 'column_ratio' => 'image_wide', 'image_ratio' => '16:9'],
        ];
        $public = $this->renderBlock($data);
        $this->assertSame($public, $this->renderBlock($data, true));
        foreach (['alt="کارگاه شرکت"', '<h4 class="block-title">درباره شرکت</h4>', '<strong>پررنگ</strong>', '<em>مورب</em>', '<ul><li>مورد</li></ul>', '<ol><li>یک</li></ol>', 'href="/contact"', 'href="https://example.com"', 'href="/services"', 'target="_blank"', 'rel="noopener noreferrer"', 'aspect-ratio: 16 / 9', 'block-text-image--image-right', 'block-text-image--image_wide'] as $expected) {
            $this->assertStringContainsString($expected, $public);
        }
        foreach (['<script', 'onclick=', 'javascript:'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $public);
        }
        $this->assertLessThan(strpos($public, 'block-text-image__content'), strpos($public, 'block-text-image__media'));
        $this->assertSame(1, Media::query()->count());
    }

    public function test_missing_image_and_unavailable_or_invalid_buttons_preserve_text(): void
    {
        $draft = Page::factory()->create(['status' => 'draft']);
        foreach ([null, ['type' => 'custom_url', 'value' => 'javascript:alert(1)'], ['type' => 'page', 'reference_id' => $draft->id], ['type' => 'page', 'reference_id' => 999999]] as $action) {
            $html = $this->renderBlock(['content' => [
                'title' => 'متن باقی می‌ماند', 'media_id' => 999999,
                'button' => ['label' => 'نباید نمایش داده شود', 'action' => $action],
            ]]);
            $this->assertStringContainsString('متن باقی می‌ماند', $html);
            $this->assertStringNotContainsString('نباید نمایش داده شود', $html);
            $this->assertStringNotContainsString('<img', $html);
        }
    }

    private function renderBlock(array $data, bool $preview = false): string
    {
        return view('partials.page-blocks', [
            'blocks' => [['type' => 'text_image', 'data' => $data]],
            'context' => ['preview' => $preview, 'page_url' => '/about'],
        ])->render();
    }
}
