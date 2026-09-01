<?php

namespace App\Providers;

use App\Support\BackupUploadLimit;
use Illuminate\Support\ServiceProvider;

class BackupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Livewire's temporary-upload gate runs before Filament field validation.
        // Keep it at least as large as the centrally configured CMS backup limit;
        // individual upload fields retain their own narrower validation rules.
        config()->set('livewire.temporary_file_upload.rules', [
            'required',
            'file',
            'max:'.BackupUploadLimit::kilobytes(),
        ]);
    }
}
