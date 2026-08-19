<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Filament\Resources\ServiceResource\Pages\EditService;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceAdminStructuredContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_draft_service_with_canonical_structured_content(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Service Content Contract',
                'slug' => 'service-content-contract',
                'excerpt' => 'A short summary.',
                'overview' => '<p>Service overview.</p>',
                'benefits' => [
                    [
                        'title' => '  First benefit  ',
                        'description' => '  First description  ',
                        'icon' => '',
                        '_uuid' => 'temporary-benefit-state',
                    ],
                    [
                        'title' => 'Second benefit',
                        'description' => '',
                        'icon' => 'heroicon-o-check',
                    ],
                ],
                'process' => [
                    [
                        'title' => 'Discovery',
                        'description' => 'Review requirements.',
                        'step' => 50,
                        'temporary_state' => true,
                    ],
                    [
                        'title' => 'Delivery',
                        'description' => '',
                        'step' => 50,
                    ],
                ],
                'deliverables' => [
                    [
                        'title' => '  Final report  ',
                        'description' => '  Documented findings  ',
                        'presentation' => 'card',
                    ],
                    [
                        'title' => 'Implementation plan',
                        'description' => '',
                    ],
                ],
                'sort_order' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()->where('slug', 'service-content-contract')->firstOrFail();

        $this->assertSame(Service::STATUS_DRAFT, $service->status);
        $this->assertSame([
            [
                'title' => 'First benefit',
                'description' => 'First description',
                'icon' => null,
            ],
            [
                'title' => 'Second benefit',
                'description' => null,
                'icon' => 'heroicon-o-check',
            ],
        ], $service->benefits);
        $this->assertSame([
            [
                'title' => 'Discovery',
                'description' => 'Review requirements.',
                'step' => 1,
            ],
            [
                'title' => 'Delivery',
                'description' => null,
                'step' => 2,
            ],
        ], $service->process);
        $this->assertSame([
            [
                'title' => 'Final report',
                'description' => 'Documented findings',
            ],
            [
                'title' => 'Implementation plan',
                'description' => null,
            ],
        ], $service->deliverables);

        $rawContent = implode(' ', [
            $service->getRawOriginal('benefits'),
            $service->getRawOriginal('process'),
            $service->getRawOriginal('deliverables'),
        ]);

        $this->assertStringNotContainsString('_uuid', $rawContent);
        $this->assertStringNotContainsString('temporary_state', $rawContent);
        $this->assertStringNotContainsString('presentation', $rawContent);
    }

    public function test_service_icon_fields_reuse_iconsax_picker_and_keep_each_repeater_row_independent(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $component = Livewire::test(CreateService::class);
        $component->fillForm([
            'name' => 'Iconsax Service',
            'slug' => 'iconsax-service',
            'icon' => 'heroicon-o-star',
            'benefits' => [
                ['title' => 'First', 'icon' => 'icon-activity'],
                ['title' => 'Second', 'icon' => 'icon-airdrop'],
            ],
            'sort_order' => 0,
        ]);
        $fields = collect($component->instance()->form->getFlatComponents(withHidden: true));
        $benefits = $fields->first(fn (Component $field): bool => $field instanceof Field
            && $field->getName() === 'benefits');
        $benefitIcons = collect($benefits instanceof Repeater ? $benefits->getChildComponentContainers() : [])
            ->map(fn ($container) => collect($container->getFlatComponents())
                ->first(fn (Component $field): bool => $field instanceof ViewField && $field->getName() === 'icon'));

        $this->assertInstanceOf(
            ViewField::class,
            $fields->first(fn (Component $field): bool => $field->getStatePath() === 'data.icon'),
        );
        $this->assertCount(2, $benefitIcons);
        $this->assertTrue($benefitIcons->every(fn ($field): bool => $field instanceof ViewField
            && $field->getView() === 'filament.forms.components.iconsax-icon-picker'));
        $this->assertCount(2, $benefitIcons->map(fn ($field): string => $field->getStatePath())->unique());

        $component->call('create')->assertHasNoFormErrors();

        $service = Service::query()->where('slug', 'iconsax-service')->firstOrFail();

        $this->assertSame('heroicon-o-star', $service->icon);
        $this->assertSame(['icon-activity', 'icon-airdrop'], array_column($service->benefits, 'icon'));
    }

    public function test_slug_is_unique_on_create_and_ignores_the_current_record_on_edit(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $service = $this->service('existing-service');
        $other = $this->service('other-service');

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Duplicate Service',
                'slug' => $service->slug,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm(['slug' => $service->slug])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm(['slug' => $other->slug])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_legacy_lifecycle_statuses_are_available_in_the_service_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $status = collect(Livewire::test(CreateService::class)
            ->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn (Component $component): bool => $component instanceof Select
                && $component->getName() === 'status');

        $this->assertInstanceOf(Select::class, $status);
        $this->assertSame([
            Service::STATUS_DRAFT => 'پیش‌نویس',
            Service::STATUS_PUBLISHED => 'منتشرشده',
            Service::STATUS_ARCHIVED => 'بایگانی‌شده',
            Service::STATUS_ACTIVE => 'فعال قدیمی',
            Service::STATUS_INACTIVE => 'غیرفعال قدیمی',
        ], $status->getOptions());
        $this->assertSame(Service::STATUS_DRAFT, $status->getDefaultState());
    }

    public function test_service_table_supports_status_and_publication_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $draft = $this->service('draft-service', Service::STATUS_DRAFT);
        $published = $this->service('published-service', Service::STATUS_PUBLISHED);
        $scheduled = $this->service('scheduled-service', Service::STATUS_PUBLISHED, now()->addDay());

        Livewire::test(ListServices::class)
            ->assertCanSeeTableRecords([$draft, $published, $scheduled])
            ->filterTable('status', Service::STATUS_DRAFT)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$published, $scheduled])
            ->removeTableFilter('status')
            ->filterTable('publication_state', 'scheduled')
            ->assertCanSeeTableRecords([$scheduled])
            ->assertCanNotSeeTableRecords([$draft, $published]);
    }

    public function test_service_resource_synchronizes_featured_image_and_gallery_from_media_library(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $featured = $admin
            ->addMedia(UploadedFile::fake()->image('featured.jpg'))
            ->toMediaCollection('media_library', 'public');
        $gallery = $admin
            ->addMedia(UploadedFile::fake()->image('gallery.jpg'))
            ->toMediaCollection('media_library', 'public');

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Media Service',
                'slug' => 'media-service',
                'sort_order' => 0,
                'featured_media_id' => $featured->id,
                'gallery_media_ids' => [$gallery->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()->where('slug', 'media-service')->firstOrFail();

        $this->assertSame(
            $featured->id,
            $service->featuredImage()?->getCustomProperty('source_media_id'),
        );
        $this->assertSame(
            [$gallery->id],
            $service->galleryImages()
                ->map(fn ($media) => $media->getCustomProperty('source_media_id'))
                ->all(),
        );
    }

    public function test_service_resource_can_manage_existing_project_relations_without_touching_legacy_json(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create([
            'services' => [['name' => 'Legacy JSON Service']],
        ]);

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Related Project Service',
                'slug' => 'related-project-service',
                'projects' => [$project->id],
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()->where('slug', 'related-project-service')->firstOrFail();

        $this->assertSame([$project->id], $service->projects()->pluck('projects.id')->all());
        $this->assertSame([['name' => 'Legacy JSON Service']], $project->fresh()->services);
    }

    public function test_project_service_options_use_publication_contract_and_preserve_selected_records(): void
    {
        $legacyActive = $this->service('legacy-active', Service::STATUS_ACTIVE);
        $published = $this->service('published', Service::STATUS_PUBLISHED, now()->subMinute());
        $draft = $this->service('draft', Service::STATUS_DRAFT);
        $archived = $this->service('archived', Service::STATUS_ARCHIVED);
        $inactive = $this->service('inactive', Service::STATUS_INACTIVE);
        $future = $this->service('future', Service::STATUS_PUBLISHED, now()->addDay());
        $selectedDraft = $this->service('selected-draft', Service::STATUS_DRAFT);
        $project = Project::factory()->create();
        $project->relatedServices()->attach($selectedDraft);

        $createOptions = ProjectResource::serviceOptionsQuery(Service::query(), null)
            ->pluck('services.id');
        $editOptions = ProjectResource::serviceOptionsQuery(Service::query(), $project)
            ->pluck('services.id');

        $this->assertEqualsCanonicalizing(
            [$legacyActive->id, $published->id],
            $createOptions->all(),
        );
        $this->assertTrue($editOptions->contains($selectedDraft->id));

        foreach ([$draft, $archived, $inactive, $future] as $unavailable) {
            $this->assertFalse($createOptions->contains($unavailable->id));
            $this->assertFalse($editOptions->contains($unavailable->id));
        }
    }

    public function test_editing_a_project_does_not_silently_detach_a_previously_selected_draft_service(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();
        $selectedDraft = $this->service('selected-draft', Service::STATUS_DRAFT);
        $project->relatedServices()->attach($selectedDraft);

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('project_service', [
            'project_id' => $project->id,
            'service_id' => $selectedDraft->id,
        ]);
    }

    public function test_service_foundation_migration_is_applied(): void
    {
        foreach ([
            'excerpt',
            'overview',
            'benefits',
            'process',
            'deliverables',
            'published_at',
            'seo_title',
            'seo_description',
            'icon',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('services', $column), $column);
        }

        $this->assertDatabaseHas('migrations', [
            'migration' => '2026_07_28_000001_add_cms_foundation_fields_to_services_table',
        ]);
    }

    private function service(
        string $slug,
        string $status = Service::STATUS_DRAFT,
        mixed $publishedAt = null,
    ): Service {
        return Service::query()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'status' => $status,
            'published_at' => $publishedAt,
            'sort_order' => 0,
        ]);
    }
}
