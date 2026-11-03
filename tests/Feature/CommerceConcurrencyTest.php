<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutIdempotency;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CommerceConcurrencyTest extends TestCase
{
    protected string $database;

    protected string $barrier;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'king_p2_'.bin2hex(random_bytes(8));
        $this->barrier = $base.'_start';
        $this->configureDatabase($base);
        $this->createFixtureSchema();
    }

    protected function configureDatabase(string $base): void
    {
        $this->database = $base.'_testing.sqlite';
        touch($this->database);
        config(['database.connections.sqlite.database' => $this->database]);
        DB::purge('sqlite');
        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA busy_timeout = 5000');
    }

    private function createFixtureSchema(): void
    {
        // Minimal pre-P2 fixtures. The actual P2 migration/indexes run unmodified below.
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_admin');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('product_title');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_27_000000_create_commerce_payment_foundation.php'))->up();
        (require database_path('migrations/2026_10_27_000001_add_zarinpal_operations_to_payment_attempts.php'))->up();
    }

    protected function tearDown(): void
    {
        $this->cleanupDatabase();
        if (isset($this->barrier) && is_file($this->barrier)) {
            unlink($this->barrier);
        }
        if (isset($this->barrier) && is_file($this->barrier.'_calls')) {
            unlink($this->barrier.'_calls');
        }
        parent::tearDown();
    }

    protected function cleanupDatabase(): void
    {
        DB::disconnect('sqlite');
        // Delete only the exact files this test created in the OS temporary directory.
        foreach ([$this->database, $this->database.'-wal', $this->database.'-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_two_processes_create_one_order_and_one_set_of_items(): void
    {
        $session = new Store('race', new ArraySessionHandler(120));
        $session->start();
        $session->put('shop_checkout_scope', str_repeat('s', 64));
        $session->put('shop_cart', [['product_id' => 1, 'quantity' => 1]]);
        $request = Request::create('/checkout');
        $request->setLaravelSession($session);
        $key = app(CheckoutIdempotency::class)->issue($request, null);
        [$first, $second] = $this->race('checkout', $key);
        $this->assertSame($first['order'], $second['order']);
        $this->assertNotSame($first['created'], $second['created']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(1, DB::table('checkout_intents')->whereNotNull('order_id')->count());
    }

    public function test_two_administrative_processes_settle_only_once(): void
    {
        $actor = User::query()->create(['name' => 'Race admin', 'is_admin' => true, 'status' => 'active']);
        $order = Order::query()->create([
            'order_number' => 'RACE-MANUAL', 'customer_name' => 'Race customer', 'customer_phone' => '09120000000',
            'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'manual', 'subtotal' => '0.70', 'total' => '0.70',
        ]);
        [$first, $second] = $this->race('manual', (string) $order->id, (string) $actor->id);
        $this->assertSame($first['attempt'], $second['attempt']);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame(1, $order->paymentAttempts()->where('settlement_order_id', $order->id)->count());
    }

    protected function workerEnvironment(): array
    {
        return ['APP_KEY' => config('app.key')];
    }

    protected function race(string|array $mode, string $identifier, string $actorId = '0'): array
    {
        $processes = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/commerce-payment-worker.php'),
                    is_array($mode) ? $mode[$i] : $mode, $this->database, $this->barrier, $identifier, $actorId], base_path(), $this->workerEnvironment());
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while (! collect($processes)->every(fn (Process $process) => str_contains($process->getOutput(), 'READY'))) {
                if (microtime(true) > $deadline || collect($processes)->contains(fn (Process $process) => ! $process->isRunning())) {
                    $this->fail('Race worker startup failed: '.collect($processes)->map->getErrorOutput()->implode(' | '));
                }
                usleep(1000);
            }
            touch($this->barrier);

            return array_map(function (Process $process): array {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

                return json_decode(trim(str_replace('READY', '', $process->getOutput())), true, flags: JSON_THROW_ON_ERROR);
            }, $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
}
