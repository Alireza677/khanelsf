<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientAuthResponse;
use App\Support\MobileNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request, ClientAuthResponse $continuation): View
    {
        $continuation->capture($request);

        return view('client.auth.register');
    }

    public function store(Request $request, ClientAuthResponse $continuation): JsonResponse|RedirectResponse
    {
        $continuation->capture($request);
        if ($response = $continuation->alreadyAuthenticated($request)) {
            return $response;
        }

        $request->merge([
            'mobile' => MobileNormalizer::normalize($request->input('mobile')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:32', Rule::unique('users', 'mobile')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)],
            'password_confirmation' => ['required', 'string'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'] ?? null,
            'password' => $validated['password'],
            'is_admin' => false,
            'status' => 'active',
        ]);

        Auth::guard('client')->login($user);

        return $continuation->success($request);
    }
}
