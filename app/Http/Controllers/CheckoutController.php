<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Services\CartService;
use App\Services\ModuleService;
use App\Services\SettingsService;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    public function create(CartService $cart, ModuleService $modules): View|RedirectResponse
    {
        $this->abortIfShopDisabled($modules);

        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('success', 'سبد خرید شما خالی است.');
        }

        return view('shop.checkout', [
            'cartItems' => $cart->items(),
            'subtotal' => $cart->subtotal(),
        ]);
    }

    public function store(Request $request, CartService $cart, SettingsService $settings, PaymentGateway $paymentGateway, ModuleService $modules): RedirectResponse
    {
        $this->abortIfShopDisabled($modules);

        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('success', 'سبد خرید شما خالی است.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'customer_phone' => ['required', 'string', 'min:4', 'max:255'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
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

        $order = DB::transaction(function () use ($cart, $validated, $paymentGateway): Order {
            $subtotal = $cart->subtotal();

            $order = Order::query()->create([
                ...$validated,
                'order_number' => 'ORD-'.now()->format('YmdHis').'-'.random_int(1000, 9999),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'payment_method' => $paymentGateway->method(),
                'payment_status' => $paymentGateway->initialPaymentStatus(),
            ]);

            foreach ($cart->items() as $item) {
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

        $cart->clear();
        $this->sendOrderEmails($order->load('items'), $settings, $paymentGateway);

        return redirect()->route('checkout.thank-you', $order)->with('success', 'سفارش شما دریافت شد.');
    }

    public function thankYou(Order $order, PaymentGateway $paymentGateway, ModuleService $modules): View
    {
        $this->abortIfShopDisabled($modules);

        return view('shop.thank-you', [
            'order' => $order->load('items'),
            'manualPaymentMessage' => $paymentGateway->instructions(),
        ]);
    }

    private function sendOrderEmails(Order $order, SettingsService $settings, PaymentGateway $paymentGateway): void
    {
        $adminEmail = trim((string) ($settings->get('shop_order_admin_email') ?: $settings->contactEmail()));

        if ($adminEmail !== '') {
            $this->sendPlainOrderEmail(
                $adminEmail,
                "سفارش جدید {$order->order_number}",
                $this->adminOrderEmailBody($order, $paymentGateway),
            );
        }

        if (filled($order->customer_email)) {
            $this->sendPlainOrderEmail(
                $order->customer_email,
                "سفارش دریافت شد: {$order->order_number}",
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
            "سپاسگزاریم. سفارش {$order->order_number} دریافت شد.",
            '',
            ...$this->orderSummaryLines($order, $paymentGateway),
            '',
            $paymentGateway->instructions(),
        ]);
    }

    private function orderSummaryLines(Order $order, PaymentGateway $paymentGateway): array
    {
        $lines = [
            "شماره سفارش: {$order->order_number}",
            "مشتری: {$order->customer_name}",
            "تلفن: {$order->customer_phone}",
            'ایمیل: '.($order->customer_email ?: '-'),
            "وضعیت: {$order->status}",
            "وضعیت پرداخت: {$order->payment_status}",
            '',
            'آیتم‌ها:',
        ];

        foreach ($order->items as $item) {
            $lines[] = "- {$item->product_title} × {$item->quantity}: ".number_format((float) $item->total).' تومان';
        }

        return [
            ...$lines,
            '',
            'مبلغ کل: '.number_format((float) $order->total).' تومان',
            'توضیح پرداخت: '.$paymentGateway->instructions(),
        ];
    }

    private function abortIfShopDisabled(ModuleService $modules): void
    {
        abort_unless($modules->shopEnabled(), 404);
    }
}
