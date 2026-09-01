<?php

namespace App\Http\Controllers;

use App\Enums\BackupRestoreStatus;
use App\Models\BackupRestore;
use App\Services\BackupRestoreProgressAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupRestoreStatusController
{
    public function __invoke(Request $request, BackupRestore $restore, BackupRestoreProgressAccess $access): JsonResponse
    {
        $access->authorize($restore, $request->query('token'));
        $status = $restore->status;

        return response()->json([
            'status' => $status->value,
            'step' => $restore->current_step,
            'label' => $status->label(),
            'completed' => $status === BackupRestoreStatus::Completed,
            'failed' => $status === BackupRestoreStatus::Failed,
            'failure_message' => $status === BackupRestoreStatus::Failed
                ? $this->safeFailureMessage($restore->failure_code)
                : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function safeFailureMessage(?string $code): string
    {
        return match ($code) {
            'restore_database_failed' => 'بازیابی پایگاه داده ناموفق بود.',
            'restore_storage_failed', 'restore_storage_swap_failed', 'restore_storage_copy_failed' => 'بازیابی فایل‌های وب‌سایت ناموفق بود.',
            'restore_health_check_failed' => 'بررسی سلامت وب‌سایت ناموفق بود.',
            'restore_binary_missing' => 'ابزار موردنیاز برای بازیابی پایگاه داده روی سرور در دسترس نیست.',
            default => 'عملیات بازیابی کامل نشد.',
        };
    }
}
