<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\FormSubmissionResource;
use App\Http\Controllers\Controller;
use App\Models\FormSubmissionAttachment;
use Filament\Facades\Filament;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FormSubmissionAttachmentDownloadController extends Controller
{
    use AuthorizesRequests;

    private const DISK = 'local';

    public function __invoke(FormSubmissionAttachment $attachment): StreamedResponse
    {
        return $this->respond($attachment, 'attachment');
    }

    public function view(FormSubmissionAttachment $attachment): StreamedResponse
    {
        return $this->respond($attachment, 'inline');
    }

    private function respond(FormSubmissionAttachment $attachment, string $disposition): StreamedResponse
    {
        $attachment->loadMissing('submission');
        abort_unless(auth()->user()?->canAccessPanel(Filament::getPanel('admin')), 403);
        abort_unless(FormSubmissionResource::canView($attachment->submission), 403);
        $disk = Storage::disk(self::DISK);
        $exists = $disk->exists($attachment->stored_path);

        if (! $exists) {
            Log::warning('Form submission attachment file is missing.', [
                'attachment_id' => $attachment->getKey(),
                'disk' => self::DISK,
                'stored_path' => $attachment->stored_path,
                'exists' => false,
            ]);

            abort(404);
        }

        return $disk->response(
            $attachment->stored_path,
            $attachment->original_name,
            ['Content-Type' => $this->mimeType($disk, $attachment)],
            $disposition,
        );
    }

    private function mimeType(FilesystemAdapter $disk, FormSubmissionAttachment $attachment): string
    {
        $detected = $disk->mimeType($attachment->stored_path);

        return is_string($detected) && $detected !== ''
            ? $detected
            : ($attachment->mime_type ?: 'application/octet-stream');
    }
}
