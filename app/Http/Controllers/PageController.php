<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SeoService;
use App\Support\AdminLoginPath;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login as FilamentLogin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function show(string $slug, SeoService $seoService): View|Response
    {
        if ($slug === app(AdminLoginPath::class)->current()) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            Filament::bootCurrentPanel();

            return app(FilamentLogin::class)();
        }

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
