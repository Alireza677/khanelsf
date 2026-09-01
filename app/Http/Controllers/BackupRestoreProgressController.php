<?php

namespace App\Http\Controllers;

use App\Models\BackupRestore;
use App\Services\BackupRestoreProgressAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackupRestoreProgressController
{
    public function __invoke(Request $request, BackupRestore $restore, BackupRestoreProgressAccess $access): View
    {
        $access->authorize($restore, $request->query('token'));

        return view('backup-restore.progress', [
            'restore' => $restore,
            'statusUrl' => route('backup-restore.status', [
                'restore' => $restore->uuid,
                'token' => $request->query('token'),
            ]),
        ]);
    }
}
