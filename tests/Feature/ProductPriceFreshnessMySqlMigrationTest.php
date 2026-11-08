<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedCommerceMySql;
use Tests\TestCase;

class ProductPriceFreshnessMySqlMigrationTest extends TestCase
{
    public function test_strict_mysql_migration_supports_fresh_install_and_partial_failure_recovery(): void
    {
        if (! getenv('KING_P2_INNODB_TEST_PORT') || ! getenv('KING_P2_INNODB_TEST_DIRECTORY')) {
            $this->markTestSkipped('An explicitly isolated temporary MySQL/MariaDB instance is required.');
        }
        $database = 'king_p2_'.bin2hex(random_bytes(8)).'_testing';
        $pdo = IsolatedCommerceMySql::connect($database);
        $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        try {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.url' => null,
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => getenv('KING_P2_INNODB_TEST_PORT'),
                'database.connections.mysql.database' => $database,
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
                'database.connections.mysql.unix_socket' => '',
                'database.connections.mysql.strict' => true,
            ]);
            DB::purge('mysql');
            // Reproduce the reported default failure without changing server-wide settings.
            $this->assertSame(0, (int) DB::selectOne('SELECT @@explicit_defaults_for_timestamp AS enabled')->enabled);
            try {
                DB::statement('CREATE TABLE invalid_history_fixture (price_verified_at TIMESTAMP NULL, occurred_at TIMESTAMP NOT NULL)');
                $this->fail('The original strict-mode TIMESTAMP failure was not reproduced.');
            } catch (QueryException $exception) {
                $this->assertSame(1067, $exception->errorInfo[1]);
            }
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->decimal('price', 12, 2);
                $table->decimal('sale_price', 12, 2)->nullable();
            });
            Schema::create('product_categories', fn (Blueprint $table) => $table->id());
            DB::table('products')->insert(['price' => '123.45', 'sale_price' => '100.10']);
            $migration = require database_path('migrations/2026_11_08_000000_add_product_price_freshness.php');
            $migration->up();
            $this->assertTrue(Schema::hasTable('product_price_histories'));
            $this->assertNull(DB::table('products')->value('price_verified_at'));
            $this->assertSame('123.45', DB::table('products')->value('price'));
            $this->assertSame('100.10', DB::table('products')->value('sale_price'));

            // Match the user's partial DDL: product columns exist, history table does not.
            Schema::drop('product_price_histories');
            DB::table('products')->update(['price_validity_mode' => 'custom', 'price_validity_days' => 7]);
            $before = (array) DB::table('products')->first();
            $migration->up();
            $this->assertSame($before, (array) DB::table('products')->first());
            DB::table('product_price_histories')->insert([
                'product_id' => 1, 'new_price' => '123.45', 'event' => 'price_changed', 'source' => 'other',
            ]);
            $this->assertNotNull(DB::table('product_price_histories')->value('occurred_at'));
            $migration->up();
            $this->assertSame(1, DB::table('product_price_histories')->count());
            $this->assertSame($before, (array) DB::table('products')->first());
        } finally {
            DB::disconnect('mysql');
            // Only the database created above on the validated temporary instance is removed.
            $pdo->exec('DROP DATABASE `'.$database.'`');
        }
    }
}
