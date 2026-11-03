<?php

namespace App\Services;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CheckoutIdempotency
{
    public function issue(Request $request, ?int $ownerId): string
    {
        $cartHash = $this->cartHash($request);
        $scope = $this->scope($request, $ownerId);
        $key = $request->session()->get('shop_checkout_intent');
        $existing = is_string($key) ? DB::table('checkout_intents')->where('key_hash', hash('sha256', $key))->first() : null;
        if ($existing && $existing->scope_hash === $scope && $existing->cart_hash === $cartHash
            && $existing->order_id === null && now()->lt($existing->expires_at)) {
            return $key;
        }
        $key = Str::random(64);
        $this->insertIntent($key, $scope, $cartHash);
        $request->session()->put('shop_checkout_intent', $key);

        return $key;
    }

    /** Returns [Order, newlyCreated]. The entire creation callback shares the intent lock. */
    public function execute(Request $request, ?int $ownerId, array $customer, string $method, Closure $create): array
    {
        $key = $request->input('_checkout_intent');
        $scope = $this->scope($request, $ownerId);
        if ($key === null) {
            // Compatibility for older direct POST clients: server-derived, durable unique intent.
            // Reuse an issued form intent when present; omitting its review token still fails P1.
            $key = $request->session()->get('shop_checkout_intent');
            if (! is_string($key)) {
                $key = hash_hmac('sha256', $scope.$this->cartHash($request).$request->session()->get('shop_checkout_generation', ''), config('app.key'));
                $this->insertIntent($key, $scope, $this->cartHash($request));
            }
        }
        $payloadHash = hash('sha256', json_encode([
            'owner' => $ownerId, 'method' => $method,
            'customer' => array_map(fn ($field) => $customer[$field] ?? null,
                ['customer_name', 'customer_phone', 'customer_email', 'customer_address', 'notes']),
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $key, $scope, $payloadHash, $create, $ownerId): array {
            $intent = DB::table('checkout_intents')->where('key_hash', hash('sha256', $key))->lockForUpdate()->first();
            if (! $intent || ! hash_equals($intent->scope_hash, $scope)) {
                $this->reject();
            }
            if ($intent->order_id !== null) {
                if (! hash_equals($intent->payload_hash, $payloadHash)) {
                    $this->reject();
                }
                $order = Order::query()->findOrFail($intent->order_id);
                if ($order->user_id !== $ownerId) {
                    $this->reject();
                }

                return [$order, false];
            }
            if (now()->gte($intent->expires_at) || ! hash_equals($intent->cart_hash, $this->cartHash($request))) {
                $this->reject();
            }
            $order = $create();
            DB::table('checkout_intents')->where('id', $intent->id)->update([
                'order_id' => $order->id, 'payload_hash' => $payloadHash, 'updated_at' => now(),
            ]);

            return [$order, true];
        }, 3);
    }

    private function insertIntent(string $key, string $scope, string $cartHash): void
    {
        // Unique-key conflict is resolved by the database, including competing legacy POSTs.
        DB::table('checkout_intents')->upsert([[
            'key_hash' => hash('sha256', $key), 'scope_hash' => $scope, 'cart_hash' => $cartHash,
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]], ['key_hash'], ['key_hash']);
    }

    private function scope(Request $request, ?int $ownerId): string
    {
        // Persist a server-only scope across normal session-ID rotation. Derivation makes
        // simultaneous older POSTs without a GET converge before any session write wins.
        $seed = $request->session()->get('shop_checkout_scope');
        if (! is_string($seed)) {
            $seed = hash_hmac('sha256', $request->session()->getId(), config('app.key'));
            $request->session()->put('shop_checkout_scope', $seed);
        }

        return hash_hmac('sha256', $seed.'|'.($ownerId ?? 'guest'), config('app.key'));
    }

    private function cartHash(Request $request): string
    {
        // Identity only; P1 independently validates quantities, current prices and review evidence.
        $items = collect($request->session()->get(CartService::SESSION_KEY, []))
            ->map(fn ($item) => is_array($item) ? [$item['product_id'] ?? null, $item['quantity'] ?? null] : null)
            ->sortBy(fn ($item) => json_encode($item))->values()->all();

        return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['checkout' => 'شناسه ثبت سفارش معتبر نیست یا اطلاعات آن تغییر کرده است؛ فرم تسویه حساب را دوباره باز کنید.']);
    }
}
