<article class=file-card>
@if($file->is_image)<img class='file-icon' src='{{ $file->view_url }}' alt='{{ $file->name }}'>@else<div class='file-icon'><x-heroicon-o-document /></div>@endif
<div class=file-info><strong>{{ $file->name }}</strong><span>{{ $file->size }} / {{ $file->extension }}</span></div>
@if($file->can_view)<a class='file-download' href='{{ $file->view_url }}' target='_blank' title='مشاهده'><x-heroicon-o-eye /></a>@endif
<a class='file-download' href='{{ $file->download_url }}' title='دانلود'><x-heroicon-o-arrow-down-tray /></a></article>
