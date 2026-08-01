<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Form\FormBlock;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\Page;
use App\Models\User;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(['default'], array_keys($block->templates()));
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
                    'settings.title',
                    'settings.heading_tag',
                    'settings.description',
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
        $page = Page::factory()->published()->create([
            'slug' => 'safe-form-block',
            'blocks' => [
                $this->block($draft->getKey()),
                $this->block(999999),
            ],
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertDontSee('فرم غیرقابل نمایش')
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
