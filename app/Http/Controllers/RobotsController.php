<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(SettingsService $settings): Response
    {
        $custom = trim((string) $settings->get('robots_txt', ''));
        $disallow = trim((string) $settings->get('robots_disallow', ''));

        $lines = $custom !== ''
            ? preg_split('/\r\n|\r|\n/', $custom)
            : ['User-agent: *', $disallow !== '' ? "Disallow: {$disallow}" : 'Allow: /'];

        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", array_filter($lines))."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
