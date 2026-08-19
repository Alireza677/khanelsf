<?php

namespace Tests\Feature;

use App\CMS\Collections\Data\CollectionAction;
use App\CMS\Collections\Data\CollectionEmptyState;
use App\CMS\Collections\Data\CollectionImage;
use App\CMS\Collections\Data\CollectionItem;
use App\CMS\Collections\Data\CollectionPresentation;
use App\CMS\Collections\Service\ServiceCollectionAdapter;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceCollectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_maps_service_to_model_free_nullable_item_without_queries(): void
    {
        $service = Service::query()->create([
            'name' => 'خدمت بدون رسانه', 'slug' => 'service-without-media',
            'status' => Service::STATUS_PUBLISHED, 'excerpt' => null, 'icon' => null,
        ])->load('media');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $item = app(ServiceCollectionAdapter::class)->item($service);

        $this->assertSame([], DB::getQueryLog());
        $this->assertInstanceOf(CollectionItem::class, $item);
        $this->assertNotInstanceOf(Service::class, $item);
        $this->assertSame('خدمت بدون رسانه', $item->title);
        $this->assertNull($item->image);
        $this->assertNull($item->icon);
        $this->assertNull($item->excerpt);
        $this->assertSame(route('services.show', 'service-without-media', absolute: false), $item->action?->href);
    }

    public function test_shared_presentation_handles_empty_and_one_two_three_five_items(): void
    {
        $empty = new CollectionPresentation(
            title: 'خدمات', items: [], emptyState: new CollectionEmptyState('خالی'), variant: 'clean_grid', columns: 3,
        );
        $emptyHtml = view('partials.presentations.collection', ['collection' => $empty])->render();
        $this->assertStringContainsString('shared-collection__empty', $emptyHtml);
        $this->assertStringContainsString('خالی', $emptyHtml);

        foreach ([1, 2, 3, 5] as $count) {
            $items = array_map(fn (int $index): CollectionItem => new CollectionItem(
                title: "خدمت {$index}",
                excerpt: $index % 2 === 0 ? null : str_repeat('توضیح طولانی ', 20),
                action: new CollectionAction('مشاهده خدمت', "/services/{$index}"),
            ), range(1, $count));
            $collection = new CollectionPresentation(title: 'خدمات', items: $items, variant: 'clean_grid', columns: 3);
            $html = view('partials.presentations.collection', compact('collection'))->render();

            $this->assertSame($count, substr_count($html, 'class="shared-collection-card"'));
            $this->assertStringContainsString('shared-collection__grid--3', $html);
            $this->assertStringNotContainsString('App\\Models\\Service', $html);
        }
    }

    public function test_card_gracefully_renders_image_or_standard_icon_without_key_leakage(): void
    {
        $collection = new CollectionPresentation(title: 'خدمات', items: [
            new CollectionItem(title: 'تصویری', image: new CollectionImage('/image.jpg', 'تصویر خدمت')),
            new CollectionItem(title: 'آیکن', icon: 'icon-activity'),
            new CollectionItem(title: 'متنی'),
        ]);
        $html = view('partials.presentations.collection', compact('collection'))->render();

        $this->assertStringContainsString('src="/image.jpg"', $html);
        $this->assertStringContainsString('<i class="icon-activity"', $html);
        $this->assertStringNotContainsString('>icon-activity<', $html);
        $this->assertSame(2, substr_count($html, 'shared-collection-card__media'));
    }

    public function test_adapter_exposes_presentation_ready_pagination(): void
    {
        $services = collect(range(1, 13))->map(fn (int $index): Service => tap(new Service([
            'name' => "Service {$index}", 'slug' => "service-{$index}", 'status' => Service::STATUS_PUBLISHED,
        ]), fn (Service $service) => $service->setRelation('media', collect())));
        $paginator = new LengthAwarePaginator($services->take(12), 13, 12, 1, ['path' => '/services']);
        $collection = app(ServiceCollectionAdapter::class)->adapt($paginator, 'خدمات', null);

        $this->assertSame('clean_grid', $collection->variant);
        $this->assertSame(1, $collection->pagination?->currentPage);
        $this->assertSame(2, $collection->pagination?->lastPage);
        $this->assertSame('/services?page=2', $collection->pagination?->nextUrl);
        $this->assertNotEmpty($collection->pagination?->links);
    }

    public function test_service_archive_applies_the_scoped_premium_presentation_without_changing_collection_contracts(): void
    {
        Service::query()->create([
            'name' => 'طراحی تجربه کاربری',
            'slug' => 'user-experience-design',
            'status' => Service::STATUS_PUBLISHED,
            'excerpt' => 'طراحی رابط‌های ساده، زیبا و کاربردی برای رشد تجربه مشتری.',
            'icon' => 'icon-brush-2',
        ]);

        $response = $this->get(route('services.index'));

        $response
            ->assertOk()
            ->assertSee('class="services-archive"', false)
            ->assertSee('خدمات حرفه‌ای برای رشد کسب‌وکار شما')
            ->assertSee('class="shared-collection__eyebrow">خدمات', false)
            ->assertSee('مشاهده جزئیات')
            ->assertSee('shared-collection__grid--3', false)
            ->assertSee('shared-collection-card__media', false);

        $this->assertSame(1, substr_count($response->getContent(), 'id="shared-collection-title"'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="shared-collection-card"'));
    }
}
