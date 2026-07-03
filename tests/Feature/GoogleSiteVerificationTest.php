<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use Tests\TestCase;

class GoogleSiteVerificationTest extends TestCase
{
    public function test_it_normalizes_a_verification_code_and_full_meta_tag(): void
    {
        $page = app(ManageSiteSettings::class);

        $this->assertSame('abc123_xyz-9', $page->normalizeGoogleSiteVerification('  abc123_xyz-9  '));
        $this->assertSame(
            'abc123',
            $page->normalizeGoogleSiteVerification('<meta name="google-site-verification" content="abc123" />'),
        );
        $this->assertSame(
            'xyz789',
            $page->normalizeGoogleSiteVerification("<meta content='xyz789' name='google-site-verification'>"),
        );
    }

    public function test_it_rejects_empty_or_invalid_verification_values(): void
    {
        $page = app(ManageSiteSettings::class);

        $this->assertNull($page->normalizeGoogleSiteVerification(''));
        $this->assertNull($page->normalizeGoogleSiteVerification('<script>alert(1)</script>'));
        $this->assertNull($page->normalizeGoogleSiteVerification('<meta name="other" content="abc123">'));
    }
}
