<?php

namespace App\Http\Controllers\Admin;

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
        ]);

        return response()->json(
            $links->search((string) ($validated['q'] ?? ''))
        );
    }
}
