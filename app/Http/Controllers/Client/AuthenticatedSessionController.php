<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('client.auth.login');
    }

    public function store(Request $request, ClientAuthenticator $authenticator): RedirectResponse
    {
        $credentials = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
        ]);

        if (! $authenticator->attemptWithPassword($credentials['mobile'], $credentials['password'])) {
            return back()->withErrors([
                'mobile' => 'The provided credentials are incorrect.',
            ])->onlyInput('mobile');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.home'));
    }

    public function destroy(Request $request, ClientAuthenticator $authenticator): RedirectResponse
    {
        $authenticator->guard()->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
