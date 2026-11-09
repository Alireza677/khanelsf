<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductTag;
use App\Services\ProductCatalogService;
use App\Services\ProductPricingService;
use App\Services\ProductQueryService;
use App\Services\ShopArchiveService;
use App\Services\ShopCategoryTree;
use App\Services\ShopFilterParser;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedCommerceMySql;
use Tests\TestCase;

class ShopFilterMySqlTest extends TestCase
{
    public function test_real_mariadb_runs_catalog_migrations_and_combined_exists_price_search_and_pagination_queries(): void
    {
        if (! getenv('KING_P2_INNODB_TEST_PORT') || ! getenv('KING_P2_INNODB_TEST_DIRECTORY')) {
            $this->markTestSkipped('An explicitly isolated temporary MySQL/MariaDB instance is required.');
        }
        $database = 'king_p2_'.bin2hex(random_bytes(8)).'_testing';
        $pdo = IsolatedCommerceMySql::connect($database);
        $original = config('database');
        $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            config([
                'database.default' => 'mysql', 'database.connections.mysql.url' => null,
                'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => getenv('KING_P2_INNODB_TEST_PORT'),
                'database.connections.mysql.database' => $database, 'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '', 'database.connections.mysql.unix_socket' => '', 'database.connections.mysql.strict' => true,
            ]);
            DB::purge('mysql');
            foreach (['2026_06_12_000000_create_shop_tables.php', '2026_11_08_000000_add_product_price_freshness.php', '2026_11_09_000001_add_universal_product_catalog.php'] as $file) {
                (require database_path('migrations/'.$file))->up();
            }
            $parent = ProductCategory::create(['name' => 'Parent', 'slug' => 'parent', 'status' => 'active']);
            $child = ProductCategory::create(['name' => 'Child', 'slug' => 'child', 'status' => 'active', 'parent_id' => $parent->id]);
            $brand = ProductBrand::create(['name' => 'Acme', 'slug' => 'acme']);
            $tag = ProductTag::create(['name' => 'Shared', 'slug' => 'shared']);
            $material = ProductAttribute::create(['name' => 'Material', 'slug' => 'material', 'is_multiple' => true, 'is_filterable' => true]);
            $steel = $material->values()->create(['name' => 'Steel', 'slug' => 'steel']);
            $alloy = $material->values()->create(['name' => 'Alloy', 'slug' => 'alloy']);
            $size = ProductAttribute::create(['name' => 'Size', 'slug' => 'size', 'is_filterable' => true]);
            $small = $size->values()->create(['name' => 'Small', 'slug' => 'small']);
            $target = Product::create(['title' => 'Target %_ item', 'slug' => 'target', 'status' => 'published', 'price' => '200.00', 'sale_price' => '80.25',
                'product_category_id' => $child->id, 'product_brand_id' => $brand->id, 'has_stock' => true, 'stock_status' => 'in_stock']);
            $target->tags()->sync([$tag->id]);
            app(ProductCatalogService::class)->syncAttributes($target, [
                ['attribute_id' => $material->id, 'value_ids' => [$steel->id, $alloy->id]],
                ['attribute_id' => $size->id, 'value_ids' => [$small->id]],
            ]);
            $other = Product::create(['title' => 'Other', 'slug' => 'other', 'status' => 'published', 'price' => '100.10', 'sale_price' => '100.10',
                'product_category_id' => $parent->id, 'has_stock' => true, 'stock_status' => 'in_stock']);
            $filters = app(ShopFilterParser::class)->parse(['category' => 'parent', 'brands' => ['acme'], 'tags' => ['shared'],
                'attributes' => ['material' => ['steel', 'alloy'], 'size' => ['small']], 'q' => '%_', 'min_price' => '80.25', 'max_price' => '80.25', 'on_sale' => '1']);
            $tree = app(ShopCategoryTree::class);
            app(ShopArchiveService::class)->validate($filters, $tree);
            $products = app(ProductQueryService::class);
            $query = $products->sortArchive($products->archive($filters, $tree), 'price_asc');
            $page = (clone $query)->paginate(1);
            $this->assertSame(1, $page->total());
            $this->assertSame([$target->id], $page->pluck('id')->all());
            $this->assertNotEmpty(DB::select('EXPLAIN '.$query->toSql(), $query->getBindings()));
            $all = app(ShopFilterParser::class)->parse(['category' => 'parent']);
            $this->assertSame([$target->id, $other->id], $products->sortArchive($products->archive($all, $tree), 'price_asc')->pluck('products.id')->all());
            $pricing = app(ProductPricingService::class);
            foreach (Product::query()->select('products.*')->selectRaw($pricing->effectivePriceSql().' AS catalog_price')->get() as $product) {
                $this->assertSame($pricing->exactPrice($product), $product->catalog_price);
            }
            fwrite(STDERR, "\nIsolated SQL verification: ".DB::selectOne('SELECT VERSION() AS version')->version."\n");
        } finally {
            DB::disconnect('mysql');
            $pdo->exec('DROP DATABASE `'.$database.'`');
            config(['database' => $original]);
            DB::purge('mysql');
        }
    }
}
