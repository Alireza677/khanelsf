<?php

namespace Tests\Feature;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ActionResolutionStatus;
use App\CMS\Actions\Enums\ResolutionMode;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormActionResolverTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('resolutionModes')]
    public function test_published_form_resolves_page_and_modal_in_all_modes(
        ResolutionMode $mode,
    ): void {
        $form = $this->form('published', 'modal');
        $resolver = app(ActionResolver::class);

        $page = $resolver->resolve(
            new ActionDestination('form', $form->getKey(), display: 'page'),
            new ResolutionContext($mode),
        );
        $modal = $resolver->resolve(
            new ActionDestination('form', $form->getKey(), display: 'modal'),
            new ResolutionContext($mode),
        );

        $this->assertSame(ActionResolutionStatus::Resolved, $page->status);
        $this->assertSame(route('forms.context', $form->slug), $page->url);
        $this->assertSame('page', $page->metadata['display']);
        $this->assertNull($page->metadata['modal_url']);
        $this->assertSame('modal', $modal->metadata['display']);
        $this->assertSame(route('forms.modal', $form->slug), $modal->metadata['modal_url']);

        foreach ([$page, $modal] as $result) {
            $this->assertTrue($result->shouldRender());
            $this->assertNull($result->target);
            $this->assertNull($result->rel);
            $this->assertSame($form->getKey(), $result->metadata['form_id']);
            $this->assertSame(route('forms.show', $form->slug), $result->metadata['page_url']);
            $this->assertContainsOnly('scalar', array_filter(
                $result->metadata,
                fn (mixed $value): bool => $value !== null,
            ));
        }
    }

    public static function resolutionModes(): array
    {
        return [
            'production' => [ResolutionMode::Production],
            'preview' => [ResolutionMode::Preview],
        ];
    }

    public function test_null_display_uses_form_fallback(): void
    {
        $pageForm = $this->form('published', 'page', 'page-fallback');
        $modalForm = $this->form('published', 'modal', 'modal-fallback');
        $resolver = app(ActionResolver::class);

        $this->assertSame('page', $resolver->resolve(
            new ActionDestination('form', $pageForm->getKey()),
            new ResolutionContext,
        )->metadata['display']);
        $this->assertSame('modal', $resolver->resolve(
            new ActionDestination('form', $modalForm->getKey()),
            new ResolutionContext,
        )->metadata['display']);
    }

    public function test_missing_unpublished_and_invalid_forms_fail_closed(): void
    {
        $draft = $this->form('draft', 'page');
        $resolver = app(ActionResolver::class);

        $missing = $resolver->resolve(
            new ActionDestination('form', 999999, display: 'page'),
            new ResolutionContext,
        );
        $unpublished = $resolver->resolve(
            new ActionDestination('form', $draft->getKey(), display: 'page'),
            new ResolutionContext,
        );
        $invalidDisplay = $resolver->resolve(
            new ActionDestination('form', $draft->getKey(), display: 'popup'),
            new ResolutionContext,
        );

        $this->assertSame(ActionResolutionStatus::Unresolved, $missing->status);
        $this->assertSame('entity_not_found', $missing->reason);
        $this->assertSame(ActionResolutionStatus::Unavailable, $unpublished->status);
        $this->assertSame('entity_unpublished', $unpublished->reason);
        $this->assertSame(ActionResolutionStatus::Invalid, $invalidDisplay->status);
        $this->assertFalse($missing->shouldRender());
        $this->assertFalse($unpublished->shouldRender());
        $this->assertFalse($invalidDisplay->shouldRender());
    }

    private function form(
        string $status,
        string $displayMode,
        string $slug = 'resolver-form',
    ): Form {
        return Form::query()->create([
            'name' => 'Resolver Form',
            'slug' => $slug,
            'status' => $status,
            'display_mode' => $displayMode,
        ]);
    }
}
