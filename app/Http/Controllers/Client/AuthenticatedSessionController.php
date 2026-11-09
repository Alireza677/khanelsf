<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientAuthenticator;
use App\Services\ClientAuthResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, ClientAuthResponse $continuation): View
    {
        $continuation->capture($request);

        return view('client.auth.login');
    }

    public function store(Request $request, ClientAuthenticator $authenticator, ClientAuthResponse $continuation): JsonResponse|RedirectResponse
    {
        $continuation->capture($request);
        if ($response = $continuation->alreadyAuthenticated($request)) {
            return $response;
        }

        $credentials = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
        ]);

        if (! $authenticator->attemptWithPassword($credentials['mobile'], $credentials['password'])) {
            throw ValidationException::withMessages([
                'mobile' => __('auth.client_failed'),
            ]);
        }

        return $continuation->success($request);
    }

    public function destroy(Request $request, ClientAuthenticator $authenticator): RedirectResponse
    {
        $authenticator->guard()->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
