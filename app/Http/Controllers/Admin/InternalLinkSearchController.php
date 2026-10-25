<?php

namespace App\Http\Controllers\Admin;

use App\CMS\Actions\Filament\ActionPickerService;
use App\Http\Controllers\Controller;
use App\Services\InternalLinkSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InternalLinkSearchController extends Controller
{
    public function __invoke(Request $request, InternalLinkSearchService $links): JsonResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'target' => ['nullable', 'string', 'in:page,project,product,service,form,video'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'selected' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['target'])) {
            abort_unless(Auth::user()->isActive(), 403);

            $picker = app(ActionPickerService::class);

            if (isset($validated['selected'])) {
                return response()->json([
                    'label' => $picker->selectedOptionLabel($validated['target'], $validated['selected']),
                ])->header('Cache-Control', 'private, no-store');
            }

            $results = $picker->browseTargets($validated['target'], (string) ($validated['q'] ?? ''), (int) ($validated['page'] ?? 1), $validated);

            return response()->json([
                'items' => $results->items(),
                'total' => $results->total(),
                'page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'categories' => $picker->browseFilters($validated['target']),
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->json(
            $links->search((string) ($validated['q'] ?? ''))
        );
    }
}
