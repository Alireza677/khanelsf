<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientDeliveryFile;
use App\Services\ClientDeliveryFileDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientDeliveryFileDownloadController extends Controller
{
    public function __invoke(Request $request, ClientDeliveryFile $file): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('update', $file->delivery->workItem->project);

        return app(ClientDeliveryFileDownload::class)->response($file);
    }
}
