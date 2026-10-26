<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SeoService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SeoService $seoService, SettingsService $settings): View
    {
        $homepageId = $settings->homepagePageId();
        $query = Page::query()->published();
        $page = $homepageId !== null
            ? $query->whereKey($homepageId)->firstOrFail()
            : $query->where('slug', 'home')->first();

        return view($homepageId !== null ? 'page' : 'home', [
            'page' => $page,
            'seo' => $page
                ? $seoService->forPage($page, route('home'))
                : $seoService->make([
                    'canonical_url' => route('home'),
                    'schema' => [
                        '@context' => 'https://schema.org',
                        '@type' => 'WebSite',
                        'name' => config('app.name'),
                        'url' => route('home'),
                    ],
                ]),
        ]);
    }
}
