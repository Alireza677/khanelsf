<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(SettingsService $settings): JsonResponse
    {
        if ((string) $settings->get('health_check_enabled', '1') === '0') {
            abort(404);
        }

        $databaseOk = rescue(
            fn (): bool => DB::select('select 1') !== [],
            false,
            report: false,
        );

        return response()->json([
            'status' => $databaseOk ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'database' => $databaseOk ? 'ok' : 'error',
        ], $databaseOk ? 200 : 503);
    }
}
