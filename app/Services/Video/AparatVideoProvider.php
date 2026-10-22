<?php

namespace App\Services\Video;

use App\Contracts\VideoProviderAdapter;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

final class AparatVideoProvider implements VideoProviderAdapter
{
    private const MAX_RESPONSE_BYTES = 1048576;

    public function parseUrl(string $url): ?array
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! $this->safeHttpsUrl($url, $parts)
            || ! in_array(strtolower($parts['host']), ['aparat.com', 'www.aparat.com'], true)
            || ! preg_match('~^/v/([A-Za-z0-9_-]{1,100})/?$~D', $parts['path'] ?? '', $matches)) {
            return null;
        }

        return [
            'provider' => 'aparat',
            'external_id' => $matches[1],
            'source_url' => 'https://www.aparat.com/v/'.$matches[1],
        ];
    }

    public function embedUrl(string $externalId): ?string
    {
        return $this->validId($externalId)
            ? 'https://www.aparat.com/video/video/embed/videohash/'.$externalId.'/vt/frame'
            : null;
    }

    public function trustedThumbnailUrl(?string $url): ?string
    {
        $parts = is_string($url) ? parse_url($url) : false;
        if (! $this->safeHttpsUrl($url, $parts) || strlen($url) > 2048) {
            return null;
        }

        $host = strtolower($parts['host']);

        return $host === 'aparat.com' || str_ends_with($host, '.aparat.com') ? $url : null;
    }

    public function fetchMetadata(string $externalId): array
    {
        if (! $this->validId($externalId) || ! extension_loaded('curl') || ! function_exists('curl_exec')) {
            return [];
        }

        try {
            // Fetch only the canonical public page. No undocumented API or embed HTML is trusted.
            // Pin a validated public address to prevent DNS rebinding. Fail closed without DNS/cURL.
            $address = $this->publicAddress();
            if ($address === null) {
                return [];
            }

            $response = Http::accept('text/html')
                ->setHandler(new CurlHandler)
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->connectTimeout(2)
                ->timeout(5)
                ->withoutRedirecting()
                ->withOptions([
                    'proxy' => '',
                    'verify' => true,
                    'decode_content' => false,
                    'curl' => [CURLOPT_RESOLVE => ['www.aparat.com:443:'.$address]],
                    'on_headers' => static function (ResponseInterface $response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_RESPONSE_BYTES) {
                            throw new RuntimeException('Video metadata response is too large.');
                        }
                    },
                    'progress' => static function ($total, $downloaded): void {
                        if ($downloaded > self::MAX_RESPONSE_BYTES) {
                            throw new RuntimeException('Video metadata response is too large.');
                        }
                    },
                ])
                ->get('https://www.aparat.com/v/'.$externalId);

            if (! $response->successful()
                || ! str_contains(strtolower($response->header('Content-Type')), 'text/html')
                || strlen($response->body()) > self::MAX_RESPONSE_BYTES) {
                return [];
            }

            return $this->metadataFromHtml($response->body());
        } catch (Throwable) {
            // Provider availability must never prevent saving an editorial record.
            return [];
        }
    }

    /** @return array{thumbnail_url?: string, duration_seconds?: int} */
    public function metadataFromHtml(string $html): array
    {
        if ($html === '' || strlen($html) > self::MAX_RESPONSE_BYTES) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $image = $xpath->evaluate('string(//meta[@property="og:image"]/@content)');
            $duration = $xpath->evaluate('string(//meta[@property="video:duration"]/@content)');
            $metadata = [];

            if ($thumbnail = $this->trustedThumbnailUrl($image)) {
                $metadata['thumbnail_url'] = $thumbnail;
            }
            if (ctype_digit($duration) && (float) $duration <= VideoDuration::MAX_SECONDS) {
                $metadata['duration_seconds'] = (int) $duration;
            }

            return $metadata;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function publicAddress(): ?string
    {
        $records = dns_get_record('www.aparat.com', DNS_A);
        if (! is_array($records) || $records === []) {
            return null;
        }

        $addresses = array_column($records, 'ip');
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::isPrivateIp($address) || IpUtils::checkIp($address, '224.0.0.0/4')) {
                return null;
            }
        }

        return $addresses[0] ?? null;
    }

    private function validId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id) === 1;
    }

    private function safeHttpsUrl(?string $url, array|false $parts): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && ! preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            && is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && isset($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443);
    }
}
