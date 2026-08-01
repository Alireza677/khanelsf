<?php

namespace Tests\Unit\InternalLinks;

use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Exceptions\DuplicateInternalLinkSource;
use App\CMS\InternalLinks\Exceptions\InvalidInternalLinkSource;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class InternalLinkRegistryAndResultTest extends TestCase
{
    public function test_registry_has_get_all_and_keys_preserve_registration_order(): void
    {
        $registry = new InternalLinkSearchRegistry;
        $page = new RegistrySearchSource('page', 'برگه');
        $service = new RegistrySearchSource('service', 'خدمت');

        $registry->register($page);
        $registry->register($service);
        $exposed = $registry->all();
        unset($exposed['page']);

        $this->assertTrue($registry->has('page'));
        $this->assertSame($page, $registry->get('page'));
        $this->assertSame(['page', 'service'], $registry->keys());
        $this->assertSame(['page', 'service'], array_keys($registry->all()));
        $this->assertTrue($registry->has('page'));
    }

    public function test_duplicate_source_fails_fast(): void
    {
        $registry = new InternalLinkSearchRegistry;
        $registry->register(new RegistrySearchSource('page', 'برگه'));

        $this->expectException(DuplicateInternalLinkSource::class);
        $this->expectExceptionMessage('source [page] is already registered');

        $registry->register(new RegistrySearchSource('page', 'Another'));
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_source_key_is_rejected(string $key): void
    {
        $registry = new InternalLinkSearchRegistry;

        $this->expectException(InvalidInternalLinkSource::class);

        $registry->register(new RegistrySearchSource($key, 'Invalid'));
    }

    public static function invalidKeys(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['Page'],
            'whitespace' => [' page'],
            'dash' => ['custom-url'],
            'dot' => ['custom.url'],
        ];
    }

    public function test_result_has_deterministic_reference_and_legacy_serialization(): void
    {
        $result = new InternalLinkSearchResult(
            targetKey: 'service',
            referenceId: 9,
            title: 'LSF Design',
            subtitle: '/services/lsf-design',
            url: '/services/lsf-design',
        );

        $this->assertTrue((new ReflectionClass($result))->isReadOnly());
        $this->assertSame([
            'target_key' => 'service',
            'reference_id' => 9,
            'title' => 'LSF Design',
            'subtitle' => '/services/lsf-design',
            'url' => '/services/lsf-design',
        ], $result->toArray());
        $this->assertSame([
            'title' => 'LSF Design',
            'type' => 'خدمت',
            'url' => '/services/lsf-design',
            'subtitle' => '/services/lsf-design',
            'target_key' => 'service',
            'reference_id' => 9,
        ], $result->toLegacyArray('خدمت'));
        $this->assertArrayNotHasKey('model', $result->toArray());
        $this->assertArrayNotHasKey('class', $result->toArray());
    }

    #[DataProvider('invalidResults')]
    public function test_invalid_result_is_rejected(
        string $key,
        int $referenceId,
        string $title,
        string $url,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new InternalLinkSearchResult($key, $referenceId, $title, '', $url);
    }

    public static function invalidResults(): array
    {
        return [
            'invalid key' => ['Service', 1, 'Title', '/service'],
            'zero ID' => ['service', 0, 'Title', '/service'],
            'empty title' => ['service', 1, '', '/service'],
            'empty URL' => ['service', 1, 'Title', ''],
            'unsafe URL' => ['service', 1, 'Title', 'javascript:alert(1)'],
            'protocol relative' => ['service', 1, 'Title', '//evil.example'],
        ];
    }
}

final class RegistrySearchSource implements InternalLinkSearchSource
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $query, int $limit): array
    {
        return [];
    }
}
