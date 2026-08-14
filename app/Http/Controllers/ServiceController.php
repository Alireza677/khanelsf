<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceTemplateUnavailable;
use App\Services\SeoService;
use App\Services\ServiceQueryService;
use App\Services\ServiceSettings;
use App\Services\ServiceTemplateRuntime;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;

final class ServiceController extends Controller
{
    public function index(
        ServiceQueryService $services,
        SettingsService $settings,
        SeoService $seo,
        ServiceSettings $serviceSettings,
    ): View {
        abort_unless($serviceSettings->publicEnabled(), 404);
        $heading = (string) $settings->get('services_index_title', 'خدمات');
        $description = (string) $settings->get(
            'services_index_description',
            'خدمات تخصصی طراحی، مهندسی و اجرای پروژه‌های ساختمانی را مشاهده کنید.',
        );

        return view('services.index', [
            'services' => $services->paginateArchive((int) $settings->get('services_per_page', 12)),
            'heading' => $heading,
            'description' => $description,
            'seo' => $seo->forServiceIndex($heading, $description),
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
