<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SeoService $seoService): View
    {
        $page = Page::query()
            ->where('slug', 'home')
            ->published()
            ->first();

        return view('home', [
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
