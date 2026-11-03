<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderPaymentService;
use App\Services\ZarinpalConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PDOException;
use Tests\Support\IsolatedCommerceMySql;

class CommerceInnoDbConcurrencyTest extends CommerceConcurrencyTest
{
    protected function configureDatabase(string $base): void
    {
        if (! getenv('KING_P2_INNODB_TEST_PORT') || ! getenv('KING_P2_INNODB_TEST_DIRECTORY')) {
            $this->markTestSkipped('An explicitly isolated local InnoDB instance was not supplied.');
        }
        $this->database = 'king_p2_'.bin2hex(random_bytes(8)).'_testing';
        $pdo = IsolatedCommerceMySql::connect($this->database);
        $pdo->exec('CREATE DATABASE `'.$this->database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        config([
            'database.default' => 'mysql', 'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => getenv('KING_P2_INNODB_TEST_PORT'),
            'database.connections.mysql.database' => $this->database,
            'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '',
            'database.connections.mysql.unix_socket' => '', 'database.connections.mysql.engine' => 'InnoDB',
        ]);
        DB::purge('mysql');
    }

    protected function cleanupDatabase(): void
    {
        if (isset($this->database)) {
            DB::disconnect('mysql');
            IsolatedCommerceMySql::connect($this->database)->exec('DROP DATABASE `'.$this->database.'`');
        }
    }

    protected function workerEnvironment(): array
    {
        return [...parent::workerEnvironment(), 'KING_P2_INNODB_WORKER' => '1',
            'KING_P3_MOCK_MERCHANT' => (string) config('payments.zarinpal.sandbox_merchant_id')];
    }

    public function test_p1_product_lock_blocks_another_connection_until_commit(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->string('status');
            $table->timestamp('published_at')->nullable();
            $table->boolean('has_stock');
            $table->string('stock_status');
        });
        DB::table('products')->insert(['id' => 1, 'title' => 'Locked product', 'slug' => 'locked', 'price' => '0.70',
            'status' => 'published', 'published_at' => now(), 'has_stock' => true, 'stock_status' => 'in_stock']);
        $engines = DB::select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$this->database]);
        $this->assertTrue(collect($engines)->every(fn ($row) => $row->ENGINE === 'InnoDB'));
        session([CartService::SESSION_KEY => [['product_id' => 1, 'quantity' => 1]]]);
        $other = IsolatedCommerceMySql::connect($this->database);
        $other->exec('USE `'.$this->database.'`');
        $other->exec('SET innodb_lock_wait_timeout = 1');
        DB::beginTransaction();
        try {
            $items = app(CartService::class)->authoritativeItems(lock: true);
            $this->assertSame('0.70', $items->sole()['total']);
            try {
                $other->exec('UPDATE products SET price = 1.00 WHERE id = 1');
                $this->fail('P1 product row lock did not block a concurrent writer');
            } catch (PDOException $exception) {
                $this->assertSame(1205, $exception->errorInfo[1]);
            }
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $this->assertSame(1, $other->exec('UPDATE products SET price = 1.00 WHERE id = 1'));
    }

    public function test_innodb_enforces_financial_unique_constraints(): void
    {
        $service = app(OrderPaymentService::class);
        foreach (['authority', 'provider_reference', 'initiation_key', 'active_order_id', 'settlement_order_id'] as $field) {
            $attempts = [];
            for ($i = 0; $i < 2; $i++) {
                $order = Order::query()->create([
                    'order_number' => 'INDEX-'.bin2hex(random_bytes(8)), 'customer_name' => 'Index fixture',
                    'customer_phone' => '09120000000', 'subtotal' => '0.70', 'total' => '0.70',
                    'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'zarinpal',
                ]);
                $attempts[] = $service->prepare($order, bin2hex(random_bytes(16)));
            }
            $value = str_ends_with($field, '_order_id') ? $attempts[0]->order_id : 'collision-'.$field;
            DB::table('payment_attempts')->where('id', $attempts[0]->id)->update([$field => $value]);
            try {
                DB::table('payment_attempts')->where('id', $attempts[1]->id)->update([$field => $value]);
                $this->fail('InnoDB accepted duplicate '.$field);
            } catch (QueryException $exception) {
                $this->assertSame(1062, $exception->errorInfo[1]);
            }
        }
        $indexes = collect(Schema::getIndexes('checkout_intents'));
        $this->assertTrue($indexes->contains(fn ($index) => $index['unique'] && $index['columns'] === ['key_hash']));
        $this->assertTrue($indexes->contains(fn ($index) => $index['unique'] && $index['columns'] === ['order_id']));
    }

    public function test_parallel_callbacks_claim_one_http_verification_and_settle_once(): void
    {
        [$order, $attempt] = $this->gatewayFixture();
        $this->race('verify', (string) $attempt->id);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('gateway_verified', $attempt->refresh()->source);
        $this->assertSame(1, count(file($this->barrier.'_calls')));
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame($order->id, $attempt->settlement_order_id);
    }

    public function test_callback_and_manual_confirmation_race_preserves_only_gateway_evidence(): void
    {
        [$order, $attempt] = $this->gatewayFixture();
        $actor = User::query()->create(['name' => 'Race admin', 'is_admin' => true, 'status' => 'active']);
        $this->race(['verify', 'manual_reject'], (string) $attempt->id, (string) $actor->id);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('gateway_verified', $attempt->refresh()->source);
        $this->assertNull($attempt->confirmed_by);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame(1, count(file($this->barrier.'_calls')));
    }

    private function gatewayFixture(): array
    {
        URL::forceRootUrl('https://king.test');
        URL::forceScheme('https');
        config(['payments.zarinpal.enabled' => true, 'payments.zarinpal.environment' => 'sandbox',
            'payments.zarinpal.sandbox_merchant_id' => (string) Str::uuid(), 'payments.zarinpal.production_enabled' => false]);
        $order = Order::query()->create(['order_number' => 'VERIFY-'.Str::uuid(), 'customer_name' => 'Gateway race fixture',
            'customer_phone' => '09120000000', 'status' => 'pending', 'payment_status' => 'unpaid',
            'payment_method' => 'zarinpal', 'subtotal' => '10000.00', 'total' => '10000.00']);
        $service = app(OrderPaymentService::class);
        $attempt = $service->prepareGateway($order, 'initial', app(ZarinpalConfiguration::class));
        $token = $service->initiateGateway($attempt);
        $attempt = $service->finishGatewayInitiation($attempt, $token, 'S'.str_repeat('0', 35), null);

        return [$order, $attempt];
    }
}
