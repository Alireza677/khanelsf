<?php

namespace App\Http\Controllers;

use App\CMS\Collections\Video\VideoCollectionAdapter;
use App\Services\SeoService;
use App\Services\VideoLibrarySettings;
use App\Services\VideoQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class VideoController extends Controller
{
    public function __construct(private readonly VideoCollectionAdapter $collections) {}

    public function index(Request $request, VideoLibrarySettings $settings, VideoQueryService $videos, SeoService $seo): View
    {
        abort_unless($settings->enabled(), 404);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:1000000']]);
        $published = $videos->archiveQuery()->paginate($settings->perPage());

        return view('videos.index', [
            'heading' => $settings->title(),
            'description' => $settings->description(),
            'videos' => $published,
            'archive' => $this->collections->adapt($published, $settings->title(), $settings->description()),
            'seo' => $seo->make([
                'title' => $settings->title(),
                'description' => strip_tags((string) $settings->description()),
                'canonical_url' => route('videos.index'),
            ]),
        ]);
    }
}
