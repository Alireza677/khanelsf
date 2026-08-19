<?php

namespace App\Http\Controllers;

use App\CMS\Collections\Service\ServiceCollectionAdapter;
use App\Exceptions\ServiceTemplateUnavailable;
use App\Services\SeoService;
use App\Services\ServiceQueryService;
use App\Services\ServiceSettings;
use App\Services\ServiceTemplateRuntime;
use App\Services\SettingsService;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;

final class ServiceController extends Controller
{
    public function index(
        ServiceQueryService $services,
        SettingsService $settings,
        SeoService $seo,
        ServiceSettings $serviceSettings,
        ServiceCollectionAdapter $collectionAdapter,
        TemplateService $templates,
    ): View {
        abort_unless($serviceSettings->publicEnabled(), 404);
        $heading = (string) $settings->get('services_index_title', 'خدمات حرفه‌ای برای رشد کسب‌وکار شما');
        $description = (string) $settings->get(
            'services_index_description',
            'با ترکیب تجربه، خلاقیت و فناوری‌های روز، خدماتی ارائه می‌دهیم که حضور دیجیتال شما را قدرتمندتر می‌کند، مشتریان بیشتری جذب می‌کند و مسیر رشد پایدار کسب‌وکارتان را هموار می‌سازد.',
        );

        $collection = $collectionAdapter->adapt(
            $services->paginateArchive((int) $settings->get('services_per_page', 12)),
            $heading,
            $description,
        );

        $template = $templates->findTemplateFor('service_index');

        return $templates->viewOrFallback($template, 'services.index', [
            'collection' => $collection,
            'seo' => $seo->forServiceIndex($heading, $description),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'services',
                'heading' => $heading,
                'description' => $description,
                'emptyMessage' => 'هنوز خدمتی منتشر نشده است.',
                'collection' => $collection,
            ],
        ]);
    }

    public function show(
        string $slug,
        ServiceQueryService $services,
        ServiceTemplateRuntime $runtime,
        ServiceSettings $serviceSettings,
    ): View {
        abort_unless($serviceSettings->publicEnabled(), 404);
        $service = $services->findPublishedBySlug($slug);

        abort_unless($service, 404);

        try {
            return $runtime->render($service);
        } catch (ServiceTemplateUnavailable) {
            abort(503, 'Service page is temporarily unavailable.');
        }
    }
}
