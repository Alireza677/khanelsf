<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientProjectWorkItem;
use App\Services\ClientDeliveryAccess;
use App\Services\ClientDeliveryFileDownload;
use App\Services\ClientDeliveryPresenter;
use App\Services\ClientProjectAccess;
use App\Services\ClientWorkItemDeliveryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceDeliveryController extends Controller
{
    private function item(Request $request, int $project, int $workItem): ClientProjectWorkItem
    {
        $project = app(ClientProjectAccess::class)->findFor($request->attributes->get('portalCustomer'), $project);
        Gate::forUser($request->user('client'))->authorize('view', $project);
        $item = $project->workItems()->where('visibility', 'client')->findOrFail($workItem);
        app(ClientDeliveryAccess::class)->authorize($request->user('client'), $item);

        return $item;
    }

    public function show(Request $request, int $project, int $workItem): View
    {
        $item = $this->item($request, $project, $workItem);

        return view('client.projects.work-item', ['item' => app(ClientDeliveryPresenter::class)->item($item, $request->user('client')), 'projectId' => $project]);
    }

    public function review(Request $request, int $project, int $workItem, int $delivery): RedirectResponse
    {
        $item = $this->item($request, $project, $workItem);
        $delivery = $item->deliveries()->where('visibility', 'client')->findOrFail($delivery);
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'decision' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:10000']]);
        app(ClientWorkItemDeliveryManager::class)->review($delivery, $request->user('client'), $data['lock_version'], $data['decision'], $data['reason'] ?? null);

        return redirect()->route('account.delivery.show', ['project' => $project, 'workItem' => $workItem, 'customer' => $request->attributes->get('portalCustomer')->id])->with('status', 'بررسی شما ثبت شد.');
    }

    public function download(Request $request, int $project, int $workItem, int $delivery, int $file): StreamedResponse
    {
        $item = $this->item($request, $project, $workItem);
        $delivery = $item->deliveries()->where('visibility', 'client')->findOrFail($delivery);
        $file = $delivery->files()->where('visibility', 'client')->findOrFail($file);

        return app(ClientDeliveryFileDownload::class)->response($file);
    }
}
