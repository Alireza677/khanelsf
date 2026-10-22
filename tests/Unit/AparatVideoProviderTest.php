<?php

namespace Tests\Unit;

use App\Services\Video\AparatVideoProvider;
use App\Services\Video\VideoDuration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AparatVideoProviderTest extends TestCase
{
    public function test_urls_are_canonicalized_and_unsafe_sources_are_rejected(): void
    {
        $provider = new AparatVideoProvider;
        $this->assertSame([
            'provider' => 'aparat', 'external_id' => 'Ab12_x', 'source_url' => 'https://www.aparat.com/v/Ab12_x',
        ], $provider->parseUrl('https://aparat.com/v/Ab12_x/?utm_source=test#fragment'));

        foreach ([
            'http://aparat.com/v/a', 'https://aparat.com.evil.test/v/a',
            'https://evil.aparat.com/v/a', 'https://aparat.com@evil.test/v/a',
            'https://user@aparat.com/v/a', 'https://aparat.com:8080/v/a',
            'https://127.0.0.1/v/a', 'https://aparat.com/v/../a',
            'https://aparat.com/video/video/embed/videohash/a/vt/frame',
            '<iframe src="https://aparat.com/v/a"></iframe>', 'https://aparat.com/v/a%2fb',
            'https://aparat.com/v/a\\b',
        ] as $url) {
            $this->assertNull($provider->parseUrl($url), $url);
        }
        $this->assertStringContainsString('/videohash/Ab12_x/', $provider->embedUrl('Ab12_x'));
        $this->assertNull($provider->embedUrl('a/../b'));
    }

    public function test_metadata_accepts_only_safe_provider_thumbnails_and_numeric_duration(): void
    {
        $provider = new AparatVideoProvider;
        $this->assertSame([
            'thumbnail_url' => 'https://static.cdn.asset.aparat.com/avt/example.jpg', 'duration_seconds' => 65,
        ], $provider->metadataFromHtml('<html><meta property="og:image" content="https://static.cdn.asset.aparat.com/avt/example.jpg"><meta property="video:duration" content="65"></html>'));
        $this->assertSame([], $provider->metadataFromHtml('<meta property="og:image" content="javascript:alert(1)"><meta property="video:duration" content="-1">'));
        foreach (['https://evil.test/image.jpg', 'https://aparat.com.evil.test/x', 'http://aparat.com/x', 'https://user@aparat.com/x', 'https://aparat.com:8080/x'] as $url) {
            $this->assertNull($provider->trustedThumbnailUrl($url));
        }
    }

    public function test_duration_round_trips_and_rejects_invalid_input(): void
    {
        foreach ([0, 65, 3599, 3600, 90000, VideoDuration::MAX_SECONDS] as $seconds) {
            $this->assertSame($seconds, VideoDuration::parse(VideoDuration::format($seconds)));
        }
        $this->assertSame(65, VideoDuration::parse('۰۱:۰۵'));
        $this->assertNull(VideoDuration::parse(null));
        $this->expectException(InvalidArgumentException::class);
        VideoDuration::parse('01:99');
    }
}
