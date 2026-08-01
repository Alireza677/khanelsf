<?php

namespace Tests\Feature;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ActionResolutionStatus;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoreValueActionResolverTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('validActions')]
    public function test_value_actions_resolve_to_safe_runtime_urls(
        array $input,
        string $url,
        ?string $target,
        ?string $rel,
    ): void {
        $destination = app(ActionDestinationNormalizer::class)->normalize($input);
        $result = app(ActionResolver::class)->resolve($destination, new ResolutionContext);

        $this->assertSame(ActionResolutionStatus::Resolved, $result->status);
        $this->assertSame($url, $result->url);
        $this->assertSame($target, $result->target);
        $this->assertSame($rel, $result->rel);
        $this->assertTrue($result->shouldRender());
        $this->assertSame($destination->toArray(), ActionDestination::fromArray(
            $destination->toArray(),
        )->toArray());
    }

    public static function validActions(): array
    {
        return [
            'custom URL' => [[
                'type' => 'custom_url',
                'value' => 'https://example.com/contact',
                'open_in_new_tab' => true,
            ], 'https://example.com/contact', '_blank', 'noopener noreferrer'],
            'temporary custom URL placeholder' => [[
                'type' => 'custom_url',
                'value' => ' # ',
            ], '#', null, null],
            'anchor' => [
                ['type' => 'anchor', 'value' => '#contact'],
                '#contact',
                null,
                null,
            ],
            'email' => [
                ['type' => 'email', 'value' => 'mailto:info@example.com'],
                'mailto:info@example.com',
                null,
                null,
            ],
            'phone' => [
                ['type' => 'phone', 'value' => 'tel:+98 912 123 4567'],
                'tel:+989121234567',
                null,
                null,
            ],
        ];
    }

    #[DataProvider('invalidActions')]
    public function test_invalid_value_actions_fail_closed(array $input): void
    {
        $result = app(ActionResolver::class)->resolve(
            app(ActionDestinationNormalizer::class)->normalize($input),
            new ResolutionContext,
        );

        $this->assertSame(ActionResolutionStatus::Invalid, $result->status);
        $this->assertFalse($result->shouldRender());
        $this->assertNull($result->url);
    }

    public static function invalidActions(): array
    {
        return [
            'unsafe URL' => [['type' => 'custom_url', 'value' => 'javascript:alert(1)']],
            'invalid anchor' => [['type' => 'anchor', 'value' => 'bad anchor']],
            'invalid email' => [['type' => 'email', 'value' => 'not-email']],
            'invalid phone' => [['type' => 'phone', 'value' => 'phone']],
        ];
    }
}
