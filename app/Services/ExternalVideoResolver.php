<?php

namespace App\Services;

final class ExternalVideoResolver
{
    /**
     * @return array{provider: ?string, embed_url: ?string, external_url: string}
     */
    public function resolve(string $url): array
    {
        $url = trim($url);

        if (! $this->isSafeExternalUrl($url)) {
            return ['provider' => null, 'embed_url' => null, 'external_url' => $url];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($this->hostMatches($host, 'youtu.be') && ($videoId = $this->safeSegment($path))) {
            return $this->result('youtube', "https://www.youtube.com/embed/{$videoId}", $url);
        }

        if ($this->hostMatches($host, 'youtube.com')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $videoId = $this->safeSegment((string) ($query['v'] ?? ''));

            if (! $videoId && str_starts_with($path, 'embed/')) {
                $videoId = $this->safeSegment(substr($path, 6));
            }

            if ($videoId) {
                return $this->result('youtube', "https://www.youtube.com/embed/{$videoId}", $url);
            }
        }

        if ($this->hostMatches($host, 'vimeo.com') && preg_match('/(?:^|\/)(\d+)(?:$|\/)/', $path, $matches)) {
            return $this->result('vimeo', 'https://player.vimeo.com/video/'.$matches[1], $url);
        }

        if ($this->hostMatches($host, 'aparat.com') && preg_match('~(?:^|/)v/([A-Za-z0-9_-]+)(?:$|/)~', $path, $matches)) {
            return $this->result(
                'aparat',
                'https://www.aparat.com/video/video/embed/videohash/'.$matches[1].'/vt/frame',
                $url,
            );
        }

        return $this->result(null, null, $url);
    }

    public function isSafeExternalUrl(?string $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            && filled(parse_url($url, PHP_URL_HOST));
    }

    /** @return array{provider: ?string, embed_url: ?string, external_url: string} */
    private function result(?string $provider, ?string $embedUrl, string $externalUrl): array
    {
        return ['provider' => $provider, 'embed_url' => $embedUrl, 'external_url' => $externalUrl];
    }

    private function hostMatches(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    private function safeSegment(string $value): ?string
    {
        $value = trim($value, '/');

        return preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1 ? $value : null;
    }
}
