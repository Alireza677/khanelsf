<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\ModuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(CartService $cart, ModuleService $modules): View
    {
        $this->abortIfShopDisabled($modules);

        return view('shop.cart', [
            'cartItems' => $cart->items(),
            'subtotal' => $cart->subtotal(),
        ]);
    }

    public function add(Product $product, Request $request, CartService $cart, ModuleService $modules): RedirectResponse
    {
        $this->abortIfShopDisabled($modules);

        abort_unless(
            Product::query()->published()->whereKey($product->getKey())->exists() && $product->isPurchasable(),
            404,
        );

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $cart->add($product, (int) ($validated['quantity'] ?? 1));

        return redirect()->route('cart.index')->with('success', 'محصول به سبد خرید اضافه شد.');
    }

    public function update(Request $request, CartService $cart, ModuleService $modules): RedirectResponse
    {
        $this->abortIfShopDisabled($modules);

        $validated = $request->validate([
            'quantities' => ['array'],
            'quantities.*' => ['integer', 'min:0', 'max:99'],
        ]);

        $cart->update($validated['quantities'] ?? []);

        return back()->with('success', 'سبد خرید به‌روزرسانی شد.');
    }

    public function remove(Request $request, CartService $cart, ModuleService $modules): RedirectResponse|JsonResponse
    {
        $this->abortIfShopDisabled($modules);

        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $cart->remove((int) $validated['product_id']);

        if ($request->expectsJson()) {
            $count = $cart->count();
            $subtotal = $cart->subtotal();

            return response()->json([
                'success' => true,
                'removed_product_id' => (int) $validated['product_id'],
                'count' => $count,
                'display_count' => $count > 99 ? '99+' : (string) $count,
                'subtotal' => $subtotal,
                'subtotal_formatted' => number_format($subtotal).' تومان',
                'is_empty' => $cart->isEmpty(),
            ]);
        }

        return back()->with('success', 'محصول از سبد خرید حذف شد.');
    }

    private function abortIfShopDisabled(ModuleService $modules): void
    {
        abort_unless($modules->shopEnabled(), 404);
    }
}
