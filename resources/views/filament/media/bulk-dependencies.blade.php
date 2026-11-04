<div class="space-y-4">
    @foreach ($records as $record)
        @include('filament.media.dependencies', ['record' => $record, 'dependencies' => app(\App\Media\MediaDependencyService::class)->report($record)])
    @endforeach
</div>
