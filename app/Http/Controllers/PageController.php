<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $slug, SeoService $seoService): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return view('page', [
            'page' => $page,
            'seo' => $seoService->forPage($page),
        ]);
    }
}
