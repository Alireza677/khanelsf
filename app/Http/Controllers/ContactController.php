<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Page;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'],
        ], [
            'website.prohibited' => 'Please try submitting the form again.',
        ]);

        unset($validated['website']);

        ContactMessage::query()->create([
            ...$validated,
            'status' => 'new',
        ]);

        return back()->with('success', 'Thanks, your message has been sent.');
    }
}
