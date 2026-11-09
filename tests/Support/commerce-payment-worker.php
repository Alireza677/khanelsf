<?php

// Independent process for explicitly isolated race tests; never use the configured site DB.
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\CheckoutIdempotency;
use App\Services\OrderPaymentService;
use App\Services\ZarinpalConfiguration;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedCommerceMySql;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$script, $mode, $database, $barrier, $identifier, $actorId] = $argv;
$path = realpath($database);
$tempRoot = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR;
$mysql = getenv('KING_P2_INNODB_WORKER') === '1';
if ($mysql) {
    IsolatedCommerceMySql::connect($database); // Validate actual server datadir before any write.
} elseif (! $path || ! str_starts_with(strtolower($path), strtolower($tempRoot)) || ! str_ends_with($path, '_testing.sqlite')) {
    throw new RuntimeException('Only dedicated temporary race-test databases are allowed.');
}
if (! in_array($mode, ['checkout', 'manual', 'verify', 'manual_reject'], true)) {
    throw new RuntimeException('Invalid race-test mode.');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => $mysql ? 'mysql' : 'sqlite', 'DB_DATABASE' => $mysql ? $database : $path,
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => getenv('KING_P2_INNODB_TEST_PORT') ?: '3306',
    'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_SOCKET' => '',
    'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.'.($mysql ? 'mysql' : 'sqlite').'.database') !== ($mysql ? $database : $path)) {
    throw new RuntimeException('Unsafe race-test environment.');
}
Http::preventStrayRequests();
Mail::fake();
if (in_array($mode, ['verify', 'manual_reject'], true)) {
    URL::forceRootUrl('https://king.test');
    URL::forceScheme('https');
    config(['payments.zarinpal.enabled' => true, 'payments.zarinpal.environment' => 'sandbox',
        'payments.zarinpal.sandbox_merchant_id' => getenv('KING_P3_MOCK_MERCHANT'), 'payments.zarinpal.production_enabled' => false]);
    Http::fake(function ($request) use ($barrier) {
        if (DB::transactionLevel() !== 0 || ! str_ends_with($request->url(), '/verify.json')) {
            throw new RuntimeException('Verification must run outside the financial transaction.');
        }
        file_put_contents($barrier.'_calls', "verify\n", FILE_APPEND | LOCK_EX);
        usleep(200000);

        return Http::response(['data' => ['code' => 100, 'ref_id' => 123456789], 'errors' => []]);
    });
}
if (! $mysql) {
    DB::statement('PRAGMA busy_timeout = 5000');
} else {
    // Force overlap after the first row lock, so the other process contends for it.
    DB::listen(function ($query): void {
        if (str_contains(strtolower($query->sql), 'for update')) {
            usleep(200000);
        }
    });
}
$session = new Store('race', new ArraySessionHandler(120));
$session->start();
$session->put('shop_checkout_scope', str_repeat('s', 64));
$session->put('shop_cart', [['product_id' => 1, 'quantity' => 1]]);
$request = Request::create('/checkout', 'POST', ['_checkout_intent' => $identifier]);
$request->setLaravelSession($session);
echo "READY\n";
flush();
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Race barrier timed out.');
    }
    usleep(1000);
}
// SQLite may reject a read-to-write lock upgrade; retry the identical intent.
// This outer harness retry is explicitly not evidence of InnoDB row-lock semantics.
for ($retry = 0; ; $retry++) {
    try {
        if ($mode === 'checkout') {
            $owner = User::query()->findOrFail($actorId);
            if (! $owner->isClient() || ! $owner->isActive()) {
                throw new RuntimeException('Checkout race requires an active client owner.');
            }
            [$order, $created] = app(CheckoutIdempotency::class)->execute($request, $owner->id,
                ['customer_name' => 'Race customer', 'customer_phone' => '09120000000'], 'manual', function () use ($owner): Order {
                    $order = Order::query()->create([
                        'user_id' => $owner->id,
                        'order_number' => 'RACE-'.Str::uuid(),
                        'customer_name' => 'Race customer', 'customer_phone' => '09120000000',
                        'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'manual',
                        'subtotal' => '0.70', 'total' => '0.70',
                    ]);
                    $order->items()->create(['product_title' => 'Snapshot', 'quantity' => 1, 'unit_price' => '0.70', 'total' => '0.70']);

                    return $order;
                });
            echo json_encode(['order' => $order->id, 'created' => $created, 'busy_retries' => $retry], JSON_THROW_ON_ERROR);
        } elseif ($mode === 'verify') {
            $attempt = PaymentAttempt::query()->findOrFail($identifier);
            $result = app(ZarinpalPaymentGateway::class)->verifyPayment($attempt->order, [
                'Authority' => $attempt->authority, 'Status' => 'OK', 'ctx' => app(ZarinpalConfiguration::class)->callbackContext($attempt),
            ]);
            echo json_encode(['verified' => $result['verified']], JSON_THROW_ON_ERROR);
        } elseif ($mode === 'manual_reject') {
            $attempt = PaymentAttempt::query()->findOrFail($identifier);
            try {
                app(OrderPaymentService::class)->confirmManual($attempt->order, User::query()->findOrFail($actorId), 'Concurrent manual claim');
                throw new RuntimeException('Manual confirmation bypassed an online payment.');
            } catch (ValidationException) {
                echo json_encode(['manual_rejected' => true], JSON_THROW_ON_ERROR);
            }
        } else {
            $attempt = app(OrderPaymentService::class)->confirmManual(Order::query()->findOrFail($identifier),
                User::query()->findOrFail($actorId), 'Concurrent receipt confirmation', 'RACE-RECEIPT');
            echo json_encode(['attempt' => $attempt->id, 'busy_retries' => $retry], JSON_THROW_ON_ERROR);
        }
        break;
    } catch (QueryException $exception) {
        if ($mysql || $retry >= 9 || ! str_contains(strtolower($exception->getMessage()), 'database is locked')) {
            throw $exception;
        }
        usleep(50000);
    }
}
