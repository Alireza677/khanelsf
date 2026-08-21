<?php

namespace App\Http\Controllers;

use App\Services\PublicSearchService;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PublicSearchController extends Controller
{
    public function __invoke(Request $request, PublicSearchService $search, SeoService $seo): View
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['sometimes', 'array', 'min:1'],
            'types.*' => ['string', 'distinct', Rule::in(PublicSearchService::TYPES)],
        ]);
        $query = trim($validated['q']);
        $requestedTypes = array_key_exists('types', $validated)
            ? array_values($validated['types'])
            : null;
        $sources = $search->availableSources();
        $selectedTypes = $requestedTypes ?? array_keys($sources);

        return view('search.index', [
            'query' => $query,
            'sources' => $sources,
            'selectedTypes' => $selectedTypes,
            'results' => $search->search($query, $requestedTypes),
            'seo' => $seo->forPublicSearch($query),
        ]);
    }
}
