<?php

namespace App\Services;

use App\Models\ClientDeliveryFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ClientDeliveryFileDownload
{
    /** Authorization must happen before calling this storage-only adapter. */
    public function response(ClientDeliveryFile $file): StreamedResponse
    {
        abort_unless($file->disk === 'service_deliveries' && Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
