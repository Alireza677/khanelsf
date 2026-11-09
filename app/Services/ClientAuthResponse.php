<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Continuation/transport for the existing client login and registration endpoints. */
final class ClientAuthResponse
{
    public function capture(Request $request): void
    {
        // Only a named, server-owned destination is accepted from a public form.
        if ($request->input('continue') === 'checkout') {
            $request->session()->put('url.intended', route('checkout.create'));
        }
    }

    public function alreadyAuthenticated(Request $request): JsonResponse|RedirectResponse|null
    {
        $user = Auth::guard('client')->user()?->fresh();
        if ($user?->isClient() && $user->isActive()) {
            return $this->success($request);
        }
        if (Auth::guard('client')->check()) {
            Auth::guard('client')->logout();
        }

        return null;
    }

    public function success(Request $request): JsonResponse|RedirectResponse
    {
        $this->capture($request);
        $request->session()->regenerate();
        // A new authentication must review the cart under its current identity.
        // Keep the cart and the durable intent records, including committed replays.
        $request->session()->forget(['shop_checkout_intent', 'shop_checkout_reviews']);
        if ($request->session()->get(CartService::SESSION_KEY, []) !== []) {
            $request->session()->put('shop_checkout_review_required', true);
        }

        $destination = $this->safeDestination($request->session()->pull('url.intended'));

        return $request->expectsJson()
            ? response()->json(['authenticated' => true, 'redirect_url' => $destination])
                ->header('Cache-Control', 'no-store, private')
            : redirect()->to($destination);
    }

    private function safeDestination(mixed $intended): string
    {
        $fallback = route('account.home');
        if (! is_string($intended) || preg_match('/[\\\\\x00-\x20\x7f]/', $intended)) {
            return $fallback;
        }
        $target = parse_url($intended);
        $origin = parse_url($fallback);
        if ($target === false || isset($target['user']) || isset($target['pass'])
            || (isset($target['host']) && (($target['scheme'] ?? null) !== $origin['scheme']
                || $target['host'] !== $origin['host'] || ($target['port'] ?? null) !== ($origin['port'] ?? null)))
            || str_starts_with($intended, '//')
            || ! preg_match('~^/(?:account(?:/|$)|dashboard(?:/|$)|profile$|checkout$)~', $target['path'] ?? '')) {
            return $fallback;
        }

        // Return a local absolute path, never a user-controlled external Location.
        return ($target['path'] ?? '/account').(isset($target['query']) ? '?'.$target['query'] : '');
    }
}
