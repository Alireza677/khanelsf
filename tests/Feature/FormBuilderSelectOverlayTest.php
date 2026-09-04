<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\User;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormBuilderSelectOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_and_block_builders_enable_the_shared_select_overlay_scope(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateForm::class)
            ->assertOk()
            ->assertSeeHtml('class="form-builder-editor"')
            ->assertSeeHtml('data-form-builder-select-overlays');

        Livewire::test(CreatePage::class)
            ->assertOk()
            ->assertSeeHtml('class="block-builder-editor"')
            ->assertSeeHtml('data-form-builder-select-overlays');
    }

    public function test_shared_select_overlay_script_is_registered_as_a_filament_module(): void
    {
        $asset = collect(FilamentAsset::getScripts())
            ->first(fn ($script): bool => $script->getId() === 'form-builder-select-overlays');

        $this->assertNotNull($asset);
        $this->assertTrue($asset->isModule());
        $this->assertStringEndsWith(
            'resources/js/filament/form-builder-select-overlays.js',
            str_replace('\\', '/', $asset->getPath()),
        );
    }
}
