<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BackupStatus;
use App\Exceptions\BackupOperationException;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(Backup $backup): StreamedResponse
    {
        abort_unless(Auth::user()?->isAdmin() && Auth::user()?->isActive(), 404);
        abort_unless($backup->status === BackupStatus::Completed, 404);

        $headers = ['Content-Type' => 'application/zip', 'X-Content-Type-Options' => 'nosniff'];
        if (! $backup->isAvailable() || ! Storage::disk($backup->local_disk)->exists($backup->local_path)) {
            throw new BackupOperationException('backup_file_missing', 'فایل نسخه پشتیبان در دسترس نیست.');
        }

        return Storage::disk($backup->local_disk)->download($backup->local_path, $backup->archive_name, $headers);
    }
}
