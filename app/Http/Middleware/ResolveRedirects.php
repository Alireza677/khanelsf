<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = Redirect::normalizePath('/'.$request->path());

        if ($this->shouldSkip($path)) {
            return $next($request);
        }

        $redirect = Redirect::query()
            ->active()
            ->where('source_path', $path)
            ->first();

        if (! $redirect || $redirect->pointsToItself()) {
            return $next($request);
        }

        $redirect->recordHit();

        return redirect()->to($redirect->target_url, $redirect->status_code);
    }

    private function shouldSkip(string $path): bool
    {
        if (in_array($path, ['/health', '/sitemap.xml', '/robots.txt'], true)) {
            return true;
        }

        foreach (['/admin', '/build', '/storage', '/assets', '/css', '/js', '/images', '/favicon.ico'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
