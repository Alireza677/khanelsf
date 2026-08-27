<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;

class ContactController extends Controller
{
    public function create(SeoService $seoService): View
    {
        $page = Page::query()
            ->where('slug', 'contact')
            ->published()
            ->first();

        return view('contact', [
            'page' => $page,
            'seo' => $seoService->forContact($page),
        ]);
    }
}
