<?php

namespace Tests\Feature;

use App\Services\ShopFilterParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ShopFilterParserTest extends TestCase
{
    public function test_normalization_is_idempotent_deduplicates_choices_and_keeps_exact_prices(): void
    {
        $parser = app(ShopFilterParser::class);
        $filters = $parser->parse([
            'q' => '  test  ', 'category' => 'clothing', 'brands' => ['z', 'a', 'z'],
            'attributes' => ['size' => ['large', 'small', 'large']],
            'min_price' => '20.25', 'max_price' => '10.10', 'featured' => 'true', 'page' => '02', 'ignored' => 'removed',
        ]);
        $this->assertSame('10.10', $filters->data['min_price']);
        $this->assertSame('20.25', $filters->data['max_price']);
        $this->assertSame(['a', 'z'], $filters->data['brands']);
        $this->assertSame(['large', 'small'], $filters->data['attributes']['size']);
        $again = $parser->parse($filters->query(withPage: true), 'clothing');
        $this->assertSame($filters->url(withPage: true), $again->url(withPage: true));
        $this->assertStringNotContainsString('ignored', $filters->url());
        $this->assertStringNotContainsString('page=', $filters->with(['q' => 'changed'])->url(withPage: true));
    }

    public function test_empty_category_control_can_leave_a_category_archive_and_clear_links_reset_page(): void
    {
        $parser = app(ShopFilterParser::class);
        $this->assertSame('', $parser->parse(['category' => null], 'clothing')->data['category']);
        $filters = $parser->parse(['q' => 'shirt', 'sort' => 'title', 'page' => '4'], 'clothing');
        $this->assertStringContainsString('/shop/category/clothing?', $filters->url());
        $this->assertStringNotContainsString('page', json_encode($filters->hiddenInputs()));
        $this->assertStringNotContainsString('page=', $filters->with(['sort' => 'default'])->url(withPage: true));
        $direct = $parser->parse(['include_children' => '0'], 'clothing');
        $this->assertSame(['include_children' => '0'], $direct->query());
        $this->assertFalse($parser->parse($direct->query(), 'clothing')->data['include_children']);
    }

    #[DataProvider('invalidQueries')]
    public function test_malformed_or_excessive_filter_inputs_are_rejected(array $query): void
    {
        $this->expectException(NotFoundHttpException::class);
        app(ShopFilterParser::class)->parse($query);
    }

    public static function invalidQueries(): array
    {
        return [
            'nested search' => [['q' => ['x']]], 'long search' => [['q' => str_repeat('a', 201)]],
            'price negative' => [['min_price' => '-1']], 'price precision' => [['max_price' => '1.234']],
            'price exponent' => [['min_price' => '1e3']], 'price nested' => [['min_price' => ['10']]],
            'price overflow' => [['max_price' => '10000000000']], 'stock invalid' => [['stock' => 'anything']],
            'sort injection' => [['sort' => 'price desc; SELECT']], 'sort nested' => [['sort' => ['price_asc']]],
            'brands scalar' => [['brands' => 'brand']], 'brands nested' => [['brands' => [['brand']]]],
            'brands excessive' => [['brands' => range(1, 31)]], 'sparse choices' => [['tags' => [4 => 'tag']]],
            'attribute scalar' => [['attributes' => 'x']], 'value scalar' => [['attributes' => ['color' => 'white']]],
            'groups excessive' => [['attributes' => array_fill_keys(range(1, 21), ['x'])]],
            'boolean invalid' => [['featured' => 'yes']], 'page zero' => [['page' => '0']],
            'page huge' => [['page' => '100001']], 'page decimal' => [['page' => '1.5']],
            'control character' => [['q' => "a\0b"]],
        ];
    }
}
