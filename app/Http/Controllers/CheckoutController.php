<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutIdempotency;
use App\Services\ManualPaymentGateway;
use App\Services\ModuleService;
use App\Services\OrderConfirmationUrl;
use App\Services\OrderReceiptPresentation;
use App\Services\ProductPricingService;
use App\Services\SettingsService;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function create(Request $request, CartService $cart, ModuleService $modules): View|RedirectResponse
    {
        $this->abortIfShopDisabled($modules);

        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('success', 'سبد خرید شما خالی است.');
        }

        try {
            $items = $cart->refresh();
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->withErrors($exception->errors())
                ->withInput($request->session()->getOldInput());
        }

        return view('shop.checkout', [
            'cartItems' => $items,
            'subtotal' => $cart->totalFor($items),
            'cartReview' => $cart->reviewToken($items),
            'checkoutIntent' => app(CheckoutIdempotency::class)->issue($request, $this->publicOwnerId()),
        ]);
    }

    public function store(Request $request, CartService $cart, SettingsService $settings, PaymentGateway $paymentGateway, ModuleService $modules, OrderConfirmationUrl $confirmations): RedirectResponse
    {
        $this->abortIfShopDisabled($modules);

        if ($cart->isEmpty() && ! $request->filled('_checkout_intent')) {
            return redirect()->route('cart.index')->with('success', 'سبد خرید شما خالی است.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'customer_phone' => ['required', 'string', 'min:4', 'max:255'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            '_cart_review' => ['nullable', 'string', 'size:40'],
            '_checkout_intent' => ['nullable', 'string', 'size:64', 'regex:/^[a-zA-Z0-9]+$/D'],
        ], [
            'customer_name.required' => 'لطفا نام خود را وارد کنید.',
            'customer_name.min' => 'نام باید حداقل ۲ کاراکتر باشد.',
            'customer_phone.required' => 'لطفا شماره تلفن را برای تایید سفارش وارد کنید.',
            'customer_phone.min' => 'لطفا یک شماره تلفن معتبر وارد کنید.',
            'customer_email.email' => 'لطفا ایمیل معتبر وارد کنید یا این فیلد را خالی بگذارید.',
            'customer_address.max' => 'آدرس بیش از حد طولانی است. لطفا کمتر از ۱۰۰۰ کاراکتر وارد کنید.',
            'notes.max' => 'توضیحات بیش از حد طولانی است. لطفا کمتر از ۲۰۰۰ کاراکتر وارد کنید.',
        ]);

        if ($paymentGateway instanceof ZarinpalPaymentGateway && ! $paymentGateway->isConfigured()) {
            return back()
                ->withInput()
                ->withErrors([
                    'payment' => 'پرداخت زرین‌پال انتخاب شده اما هنوز پیکربندی نشده است. لطفا با مدیر سایت تماس بگیرید یا پرداخت دستی را انتخاب کنید.',
                ]);
        }

        $ownerId = $this->publicOwnerId();
        $customer = collect($validated)->except(['_cart_review', '_checkout_intent'])->all();
        try {
            [$order, $created] = app(CheckoutIdempotency::class)->execute($request, $ownerId, $customer, $paymentGateway->method(), function () use ($cart, $customer, $validated, $paymentGateway, $ownerId): Order {
                $items = $cart->authoritativeItems(lock: true);
                $cart->assertReviewed($items, $validated['_cart_review'] ?? null);
                $subtotal = $cart->totalFor($items);
                if ($paymentGateway instanceof ZarinpalPaymentGateway) {
                    $paymentGateway->validateAmount($subtotal);
                }

                $order = Order::query()->create([
                    ...$customer,
                    'user_id' => $ownerId,
                    'order_number' => 'ORD-'.Str::uuid(),
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'payment_method' => $paymentGateway->method(),
                    'payment_status' => $paymentGateway->initialPaymentStatus(),
                ]);

                foreach ($items as $item) {
                    $order->items()->create([
                        'product_id' => $item['product_id'],
                        'product_title' => $item['title'],
                        'product_sku' => $item['sku'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'total' => $item['total'],
                    ]);
                }

                return $order;
            });
        } catch (ValidationException $exception) {
            return redirect()->route('checkout.create')->withErrors($exception->errors())->withInput($customer);
        }

        // A concurrent replay can carry the pre-commit Session snapshot. Clear that
        // consumed cart, while preserving a later purchase with a different form intent.
        if ($created || ! $request->filled('_checkout_intent')
            || $request->session()->get('shop_checkout_intent') === $request->input('_checkout_intent')) {
            $cart->clear();
            $request->session()->forget('shop_checkout_intent');
            $request->session()->put('shop_checkout_generation', Str::random(40));
        }
        if ($created) {
            $this->sendOrderEmails($order->load('items'), $settings, $paymentGateway);
        }

        $confirmations->remember($order);
        if ($paymentGateway instanceof ZarinpalPaymentGateway) {
            $result = $paymentGateway->createPayment($order);
            if ($result['redirect_url']) {
                return redirect()->away($result['redirect_url']);
            }
        }

        return redirect()->to($confirmations->temporary($order))->with('success', 'سفارش شما دریافت شد.');
    }

    public function thankYou(Order $order, PaymentGateway $paymentGateway, ModuleService $modules): View
    {
        $ownerId = Auth::guard('client')->id();

        abort_if($order->user_id !== null && $ownerId !== $order->user_id, 404);

        return view('shop.thank-you', app(OrderReceiptPresentation::class)->forOrder($order));
    }

    private function sendOrderEmails(Order $order, SettingsService $settings, PaymentGateway $paymentGateway): void
    {
        $adminEmail = trim((string) ($settings->get('shop_order_admin_email') ?: $settings->contactEmail()));

        if ($adminEmail !== '') {
            $this->sendPlainOrderEmail(
                $adminEmail,
                "سفارش جدید {$order->displayCode()}",
                $this->adminOrderEmailBody($order, $paymentGateway),
            );
        }

        if (filled($order->customer_email)) {
            $this->sendPlainOrderEmail(
                $order->customer_email,
                "سفارش دریافت شد: {$order->displayCode()}",
                $this->customerOrderEmailBody($order, $paymentGateway),
            );
        }
    }

    private function sendPlainOrderEmail(string $to, string $subject, string $body): void
    {
        rescue(
            fn () => Mail::raw($body, fn ($message) => $message->to($to)->subject($subject)),
            report: false,
        );
    }

    private function adminOrderEmailBody(Order $order, PaymentGateway $paymentGateway): string
    {
        return implode(PHP_EOL, [
            'یک سفارش جدید در فروشگاه ثبت شد.',
            '',
            ...$this->orderSummaryLines($order, $paymentGateway),
        ]);
    }

    private function customerOrderEmailBody(Order $order, PaymentGateway $paymentGateway): string
    {
        return implode(PHP_EOL, [
            "سپاسگزاریم. سفارش {$order->displayCode()} دریافت شد.",
            '',
            ...$this->orderSummaryLines($order, $paymentGateway),
            '',
            $paymentGateway->instructions(),
        ]);
    }

    private function orderSummaryLines(Order $order, PaymentGateway $paymentGateway): array
    {
        $lines = [
            "کد سفارش: {$order->displayCode()}",
            "مشتری: {$order->customer_name}",
            "تلفن: {$order->customer_phone}",
            'ایمیل: '.($order->customer_email ?: '-'),
            "وضعیت: {$order->statusLabel()}",
            "وضعیت پرداخت: {$order->paymentStatusLabel()}",
            '',
            'آیتم‌ها:',
        ];

        foreach ($order->items as $item) {
            $lines[] = "- {$item->product_title} × {$item->quantity}: ".app(ProductPricingService::class)->formatAmount($item->total).' تومان';
        }

        return [
            ...$lines,
            '',
            'مبلغ کل: '.app(ProductPricingService::class)->formatAmount($order->total).' تومان',
            'توضیح پرداخت: '.$paymentGateway->instructions(),
        ];
    }

    private function abortIfShopDisabled(ModuleService $modules): void
    {
        abort_unless($modules->shopEnabled(), 404);
    }

    private function publicOwnerId(): ?int
    {
        $user = Auth::guard('client')->user();

        return $user && ! $user->is_admin && $user->status === 'active' ? $user->getKey() : null;
    }
}
