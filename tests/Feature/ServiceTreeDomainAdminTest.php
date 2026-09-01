<?php

namespace Tests\Feature;

use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\ServiceResource\Pages\EditService;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Models\Service;
use App\Services\ServiceQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceTreeDomainAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_child_grandchild_relations_and_legacy_root_are_supported(): void
    {
        $root = $this->service('Root', 2);
        $child = $this->service('Child', 1, $root);
        $grandchild = $this->service('Grandchild', 1, $child);
        $legacy = $this->service('Legacy', 1);

        $this->assertNull($root->parent);
        $this->assertTrue($child->parent->is($root));
        $this->assertTrue($root->children->first()->is($child));
        $this->assertTrue($child->children->first()->is($grandchild));
        $this->assertNull($legacy->parent_id);
    }

    public function test_self_parent_and_indirect_cycle_are_rejected_by_domain(): void
    {
        $root = $this->service('Root', 1);
        $child = $this->service('Child', 1, $root);
        $grandchild = $this->service('Grandchild', 1, $child);

        try {
            $root->update(['parent_id' => $root->id]);
            $this->fail('Self parent was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
        }

        try {
            $root->update(['parent_id' => $grandchild->id]);
            $this->fail('Indirect cycle was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
        }
        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_children_and_admin_tree_preserve_sibling_order(): void
    {
        $root = $this->service('Root', 1);
        $later = $this->service('Zeta', 2, $root);
        $alpha = $this->service('Alpha', 1, $root);
        $beta = $this->service('Beta', 1, $root);

        $this->assertSame([$alpha->id, $beta->id, $later->id], $root->children()->pluck('id')->all());
        $labels = ServiceResource::hierarchicalLabels();
        $this->assertSame([$root->id, $alpha->id, $beta->id, $later->id], array_keys($labels));
        $this->assertSame('— Alpha', $labels[$alpha->id]);
        $this->assertSame([$root->id, $alpha->id, $beta->id, $later->id], ServiceResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_deleting_parent_promotes_direct_children_to_roots_without_deleting_subtree(): void
    {
        $root = $this->service('Root', 1);
        $child = $this->service('Child', 1, $root);
        $grandchild = $this->service('Grandchild', 1, $child);

        $root->delete();

        $this->assertNull($child->fresh()->parent_id);
        $this->assertSame($child->id, $grandchild->fresh()->parent_id);
        $this->assertDatabaseHas('services', ['id' => $child->id]);
        $this->assertDatabaseHas('services', ['id' => $grandchild->id]);
    }

    public function test_parent_selector_excludes_self_and_descendants_and_edit_preserves_parent(): void
    {
        $root = $this->service('Root', 1);
        $child = $this->service('Child', 1, $root);
        $grandchild = $this->service('Grandchild', 1, $child);
        $other = $this->service('Other', 2);

        $options = ServiceResource::treeOptions($child);
        $this->assertArrayHasKey($root->id, $options);
        $this->assertArrayHasKey($other->id, $options);
        $this->assertArrayNotHasKey($child->id, $options);
        $this->assertArrayNotHasKey($grandchild->id, $options);

        $pickerItems = collect(ServiceResource::parentPickerItems($child))->keyBy('id');
        $this->assertSame('Root ← Child ← Grandchild', $pickerItems[$grandchild->id]['path']);
        $this->assertSame(1, $pickerItems[$root->id]['children_count']);
        $this->assertSame(1, $pickerItems[$child->id]['children_count']);
        $this->assertSame(0, $pickerItems[$grandchild->id]['children_count']);
        $this->assertSame([], $pickerItems[$root->id]['ancestor_ids']);
        $this->assertSame([$root->id], $pickerItems[$child->id]['ancestor_ids']);
        $this->assertSame([$root->id, $child->id], $pickerItems[$grandchild->id]['ancestor_ids']);
        $this->assertFalse($pickerItems[$root->id]['disabled']);
        $this->assertTrue($pickerItems[$child->id]['disabled']);
        $this->assertTrue($pickerItems[$grandchild->id]['disabled']);
        $this->assertFalse($pickerItems[$other->id]['disabled']);

        Livewire::test(EditService::class, ['record' => $child->id])
            ->assertFormSet(['parent_id' => $root->id])
            ->assertSee('انتخاب خدمت والد')
            ->assertSee('بدون والد / خدمت اصلی')
            ->set('data.name', 'Child edited')
            ->set('data.sort_order', 9)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($root->id, $child->fresh()->parent_id);
        $this->assertSame(9, $child->fresh()->sort_order);
        Livewire::test(ListServices::class)->assertSee('— Child edited');
    }

    public function test_parent_picker_markup_separates_branch_expansion_search_and_selection(): void
    {
        $markup = file_get_contents(resource_path('views/filament/forms/components/service-parent-picker.blade.php'));

        $this->assertStringContainsString('x-on:click="toggle(item.id)"', $markup);
        $this->assertStringContainsString("item.has_children && ! query ? toggle(item.id) : choose(item)", $markup);
        $this->assertStringContainsString('x-on:click.stop="choose(item)"', $markup);
        $this->assertStringContainsString('item.ancestor_ids.every(id => this.isExpanded(id))', $markup);
        $this->assertStringContainsString('if (this.query)', $markup);
        $this->assertStringContainsString('return `${item.name} ${item.path}`', $markup);
        $this->assertStringContainsString("this.open = false", $markup);
    }

    public function test_public_service_queries_remain_flat_and_keep_existing_order(): void
    {
        $root = $this->service('Root', 3, status: Service::STATUS_PUBLISHED);
        $child = $this->service('Child', 1, $root, Service::STATUS_PUBLISHED);
        $other = $this->service('Other', 2, status: Service::STATUS_PUBLISHED);

        $ids = app(ServiceQueryService::class)->archiveQuery()->pluck('id')->all();
        $this->assertSame([$child->id, $other->id, $root->id], $ids);
        $this->assertTrue(app(ServiceQueryService::class)->findPublishedBySlug($child->slug)?->is($child));
    }

    private function service(
        string $name,
        int $sortOrder,
        ?Service $parent = null,
        string $status = Service::STATUS_DRAFT,
    ): Service {
        return Service::query()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => $status,
            'sort_order' => $sortOrder,
            'parent_id' => $parent?->id,
        ]);
    }
}
