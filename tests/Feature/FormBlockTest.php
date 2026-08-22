<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Form\FormBlock;
use App\CMS\Blocks\Form\FormBlockRuntime;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\Page;
use App\Models\User;
use App\Services\FormSchema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class FormBlockTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_form_block_is_registered_with_the_v1_contract(): void
    {
        $block = app(BlockRegistry::class)->find('form');

        $this->assertInstanceOf(FormBlock::class, $block);
        $this->assertSame('form', $block->key());
        $this->assertSame('فرم', $block->label());
        $this->assertSame(1, $block->version());
        $this->assertSame('default', $block->defaultTemplate());
        $this->assertSame(['default', 'split'], array_keys($block->templates()));
    }

    public function test_page_and_template_editors_offer_only_published_form_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $published = $this->form('فرم منتشرشده', 'published-form');
        $draft = $this->form('فرم پیش‌نویس', 'draft-form', 'draft');
        $page = Page::factory()->create();

        $pageBuilder = collect(Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn (Component $component): bool => $component instanceof Builder && $component->getName() === 'blocks');
        $pageFormBlock = $pageBuilder->getBlock('form');

        $method = new ReflectionMethod(TemplateResource::class, 'blockDefinitions');
        $templateFormBlock = collect($method->invoke(null))
            ->first(fn (Builder\Block $block): bool => $block->getName() === 'form');

        foreach ([$pageFormBlock, $templateFormBlock] as $formBlock) {
            $this->assertNotNull($formBlock);
            $this->assertSame('فرم', $formBlock->getLabel());

            $fields = collect($formBlock->getChildComponents())
                ->filter(fn (Component $component): bool => method_exists($component, 'getName'))
                ->keyBy(fn (Component $component): string => $component->getName());
            $select = $fields->get('content.form_id');

            $this->assertInstanceOf(Select::class, $select);
            $this->assertTrue($select->isRequired());
            $this->assertSame([$published->getKey() => $published->name], $select->getOptions());
            $this->assertArrayNotHasKey($draft->getKey(), $select->getOptions());
            $this->assertNotContains($published->slug, $select->getOptions());
            $this->assertSame(
                [
                    'block_id',
                    'schema_version',
                    'template',
                    'content.form_id',
                    'content.eyebrow',
                    'settings.title',
                    'settings.heading_tag',
                    'settings.description',
                    'content.media.source_id',
                    'content.media.url',
                    'content.media.alt',
                    'settings.style',
                    'settings.container',
                ],
                $fields->keys()->all(),
            );
            $this->assertSame(['default' => 'پیش‌فرض', 'card' => 'کارت'], $fields['settings.style']->getOptions());
            $this->assertSame('default', $fields['settings.style']->getDefaultState());
            $this->assertSame(
                ['default' => 'پیش‌فرض', 'narrow' => 'باریک', 'full' => 'تمام عرض'],
                $fields['settings.container']->getOptions(),
            );
            $this->assertSame('default', $fields['settings.container']->getDefaultState());
            $this->assertInstanceOf(RichEditor::class, $fields['settings.description']);
        }
    }

    public function test_published_form_block_renders_the_existing_form_partial_on_a_page(): void
    {
        $form = $this->form('فرم درخواست همکاری', 'cooperation-form');
        $page = $this->pageWithFormBlock($form->getKey(), 'form-block-render');

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('فرم درخواست همکاری')
            ->assertSee('name="email"', false)
            ->assertSee('class="form-card"', false)
            ->assertSee('name="_context_page_id" value="'.$page->getKey().'"', false)
            ->assertSee('name="_context_block_id" value="'.self::BLOCK_ID.'"', false);
    }

    public function test_form_block_applies_presentation_settings_without_changing_form_rendering(): void
    {
        $form = $this->form('نام اصلی فرم', 'styled-form');
        $page = Page::factory()->published()->create([
            'slug' => 'styled-form-block',
            'blocks' => [$this->block($form->getKey(), [
                'title' => 'عنوان سفارشی فرم',
                'description' => 'توضیحات کوتاه فرم',
                'style' => 'card',
                'container' => 'narrow',
            ])],
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('عنوان سفارشی فرم')
            ->assertSee('توضیحات کوتاه فرم')
            ->assertSee('block-form--card', false)
            ->assertSee('block-form--container-narrow', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="_context_block_id" value="'.self::BLOCK_ID.'"', false);
    }

    public function test_missing_and_unpublished_forms_fail_safely(): void
    {
        $draft = $this->form('فرم غیرقابل نمایش', 'hidden-form', 'draft');
        $archived = $this->form('فرم بایگانی‌شده', 'archived-form', 'archived');
        $page = Page::factory()->published()->create([
            'slug' => 'safe-form-block',
            'blocks' => [
                $this->block($draft->getKey()),
                $this->block($archived->getKey()),
                $this->block(999999),
            ],
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertDontSee('فرم غیرقابل نمایش')
            ->assertDontSee('فرم بایگانی‌شده')
            ->assertDontSee('class="block-form"', false)
            ->assertDontSee('class="form-card"', false);
    }

    public function test_embedded_form_submission_preserves_page_and_block_attribution(): void
    {
        $form = $this->form('فرم منتسب', 'attributed-form');
        $page = $this->pageWithFormBlock($form->getKey(), 'attributed-form-page');
        $pageUrl = '/'.$page->slug;

        $this->from(route('pages.show', $page->slug))->post(route('forms.submit', $form->slug), [
            'email' => 'embedded@example.com',
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => $pageUrl,
            '_context_block_id' => self::BLOCK_ID,
            '_display_mode' => 'page',
        ])->assertRedirect(route('pages.show', $page->slug));

        $submission = FormSubmission::query()->sole();
        $lead = Lead::query()->sole();

        foreach ([$submission, $lead] as $record) {
            $this->assertSame('website', $record->source);
            $this->assertSame($page->getKey(), $record->page_id);
            $this->assertSame($pageUrl, $record->page_url);
            $this->assertSame(self::BLOCK_ID, $record->block_id);
        }
    }

    public function test_split_form_renders_rich_content_and_optional_image_without_changing_form_ownership(): void
    {
        $form = $this->form('فرم دو ستونه', 'split-form');
        $block = $this->block($form->getKey(), [
            'title' => 'عنوان سفارشی',
            'description' => '<p>متن <strong>امن</strong> و <em>تأکیدی</em></p><p>بند دوم<br>ادامه</p><ul><li><a href="https://example.com">پیوند</a></li></ul><script>alert(1)</script>',
        ]);
        $block['data']['template'] = 'split';
        $block['data']['content']['eyebrow'] = 'شروع همکاری';
        $block['data']['content']['media'] = [
            'url' => '/split-image.jpg',
            'alt' => 'تصویر فرم',
        ];
        $page = Page::factory()->published()->create(['slug' => 'split-form-page', 'blocks' => [$block]]);

        $html = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();

        $this->assertStringContainsString('block-form--split', $html);
        $this->assertStringContainsString('شروع همکاری', $html);
        $this->assertStringContainsString('<p>متن <strong>امن</strong> و <em>تأکیدی</em></p>', $html);
        $this->assertStringContainsString('<em>تأکیدی</em>', $html);
        $this->assertStringContainsString('<p>بند دوم<br />ادامه</p>', $html);
        $this->assertStringContainsString('<ul><li><a href="https://example.com">پیوند</a></li></ul>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('src="/split-image.jpg"', $html);
        $this->assertStringContainsString('alt="تصویر فرم"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertArrayNotHasKey('fields', $page->fresh()->blocks[0]['data']);
    }

    public function test_split_form_handles_legacy_text_empty_media_and_missing_media_reference(): void
    {
        $form = $this->form('فرم محتوای قدیمی', 'legacy-split-form');
        $block = $this->block($form->getKey(), ['description' => "خط اول\n\nخط دوم"]);
        $block['data']['template'] = 'split';
        $block['data']['content']['media'] = [
            'source_id' => 999999,
            'url' => '/stale-media.jpg',
            'alt' => 'رسانه حذف‌شده',
        ];
        $page = Page::factory()->published()->create(['slug' => 'legacy-split-form', 'blocks' => [$block]]);

        $html = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<p>خط اول</p>', $html);
        $this->assertStringContainsString('<p>خط دوم</p>', $html);
        $this->assertStringNotContainsString('stale-media.jpg', $html);
        $this->assertSame(1, substr_count($html, 'فرم محتوای قدیمی'));
    }

    public function test_unavailable_form_is_visible_only_as_safe_admin_preview_placeholder(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = $this->form('نام حساس پیش‌نویس', 'preview-draft-form', 'draft');
        $page = Page::factory()->create([
            'slug' => 'preview-form-placeholder',
            'blocks' => [$this->block($draft->getKey())],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.preview.pages.show', $page))
            ->assertOk()
            ->assertSee('فرم منتشرشده‌ای برای این بلوک در دسترس نیست.')
            ->assertDontSee('نام حساس پیش‌نویس');
    }

    public function test_form_block_runtime_caches_same_form_lookup_and_blade_contains_no_query(): void
    {
        $form = $this->form('فرم کش‌شده', 'cached-form');
        $runtime = new FormBlockRuntime(app(FormBlock::class), app(FormSchema::class));
        DB::flushQueryLog();
        DB::enableQueryLog();

        $runtime->prepare($this->block($form->getKey())['data'], ['page_url' => '/cache']);
        $second = $this->block($form->getKey())['data'];
        $second['block_id'] = '01ARZ3NDEKTSV4RRFFQ69G5FAX';
        $runtime->prepare($second, ['page_url' => '/cache']);

        $formQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'from "forms"')
                || str_contains(strtolower($query['query']), 'from `forms`'),
        );
        $this->assertCount(1, $formQueries);
        $this->assertStringNotContainsString('Form::query', file_get_contents(resource_path('views/partials/blocks/form.blade.php')));
    }

    public function test_same_form_twice_has_unique_ids_and_instance_scoped_validation_and_success(): void
    {
        $form = $this->form('فرم تکراری', 'repeated-form');
        $secondBlockId = '01ARZ3NDEKTSV4RRFFQ69G5FAX';
        $page = Page::factory()->published()->create([
            'slug' => 'repeated-form-page',
            'blocks' => [$this->block($form->getKey()), $this->block($form->getKey())],
        ]);
        $blocks = $page->blocks;
        $blocks[1]['data']['block_id'] = $secondBlockId;
        $page->update(['blocks' => $blocks]);
        $firstToken = 'embedded-'.strtolower(self::BLOCK_ID);
        $secondToken = 'embedded-'.strtolower($secondBlockId);

        $initial = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();
        $this->assertStringContainsString('id="form-'.$form->getKey().'-'.$firstToken.'"', $initial);
        $this->assertStringContainsString('id="form-'.$form->getKey().'-'.$secondToken.'"', $initial);

        $this->from(route('pages.show', $page->slug))->post(route('forms.submit', $form->slug), [
            'email' => 'not-an-email',
            '_form_instance' => $secondToken,
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/'.$page->slug,
            '_context_block_id' => $secondBlockId,
        ])->assertRedirect(route('pages.show', $page->slug));

        $invalid = $this->get(route('pages.show', $page->slug))->getContent();
        $this->assertSame(1, substr_count($invalid, 'لطفا فرم را بررسی کنید'));
        $this->assertSame(1, substr_count($invalid, 'value="not-an-email"'));

        $this->from(route('pages.show', $page->slug))->post(route('forms.submit', $form->slug), [
            'email' => 'isolated@example.com',
            '_form_instance' => $secondToken,
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/'.$page->slug,
            '_context_block_id' => $secondBlockId,
        ])->assertRedirect(route('pages.show', $page->slug));

        $success = $this->get(route('pages.show', $page->slug))->getContent();
        $this->assertSame(1, substr_count($success, 'Thanks, your information has been received.'));
        $this->assertSame($secondBlockId, FormSubmission::query()->sole()->block_id);
    }

    public function test_embedded_and_same_form_modal_keep_attribution_isolated(): void
    {
        $form = $this->form('فرم مشترک', 'shared-embedded-modal-form');
        $page = $this->pageWithFormBlock($form->getKey(), 'shared-embedded-modal-page');
        $embeddedToken = 'embedded-'.strtolower(self::BLOCK_ID);
        $modalBlockId = '01ARZ3NDEKTSV4RRFFQ69G5FAX';
        $modalToken = 'action-'.strtolower($modalBlockId);

        $this->post(route('forms.modal', $form->slug), [
            '_form_instance' => $modalToken,
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/'.$page->slug,
            '_context_block_id' => $modalBlockId,
        ])->assertOk()->assertSee('name="_form_instance" value="'.$modalToken.'"', false);

        $this->from(route('pages.show', $page->slug))->post(route('forms.submit', $form->slug), [
            'email' => 'embedded-isolated@example.com',
            '_form_instance' => $embeddedToken,
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/'.$page->slug,
            '_context_block_id' => self::BLOCK_ID,
        ])->assertRedirect(route('pages.show', $page->slug));

        $this->post(route('forms.submit', $form->slug), [
            'email' => 'modal-isolated@example.com',
            '_form_instance' => $modalToken,
            '_display_mode' => 'modal',
        ])->assertRedirect(route('forms.show', $form->slug));

        $this->assertSame(
            [self::BLOCK_ID, $modalBlockId],
            FormSubmission::query()->orderBy('id')->pluck('block_id')->all(),
        );
    }

    public function test_normalizer_is_v1_idempotent_and_keeps_reference_only_contract(): void
    {
        $block = app(FormBlock::class);
        $normalized = $block->normalize([
            'block_id' => self::BLOCK_ID,
            'schema_version' => 1,
            'template' => 'split',
            'content' => [
                'form_id' => '42',
                'eyebrow' => '  برچسب  ',
                'media' => ['source_id' => '7', 'url' => '/image.jpg', 'alt' => '  تصویر  '],
            ],
            'settings' => ['description' => '<p>متن</p>', 'style' => 'card', 'container' => 'full'],
        ]);

        $this->assertSame($normalized, $block->normalize($normalized));
        $this->assertSame(1, $normalized['schema_version']);
        $this->assertSame(42, $normalized['content']['form_id']);
        $this->assertArrayNotHasKey('fields', $normalized);
    }

    private function form(string $name, string $slug, string $status = 'published'): Form
    {
        return Form::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [[
                'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                'key' => 'email',
                'label' => 'ایمیل',
                'type' => 'email',
                'required' => true,
            ]]],
            'settings' => [],
        ]);
    }

    private function pageWithFormBlock(int $formId, string $slug): Page
    {
        return Page::factory()->published()->create([
            'slug' => $slug,
            'blocks' => [$this->block($formId)],
        ]);
    }

    private function block(int $formId, array $settings = []): array
    {
        $block = [
            'type' => 'form',
            'data' => [
                'block_id' => self::BLOCK_ID,
                'content' => ['form_id' => $formId],
            ],
        ];

        if ($settings !== []) {
            $block['data']['settings'] = $settings;
        }

        return $block;
    }
}
