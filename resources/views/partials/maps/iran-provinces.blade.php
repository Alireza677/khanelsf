{{-- Trusted static asset; this file is the single source of truth for map geometry and province attributes. --}}
@php($iranMapSvg = file_get_contents(resource_path('assets/maps/iran-provinces-interactive.svg')))
{!! preg_replace('/^<\?xml[^?]*\?>\s*/', '', $iranMapSvg) !!}
