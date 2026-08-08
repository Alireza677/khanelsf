<?php

namespace App\Services;

use App\Support\MobileNormalizer;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

class ClientAuthenticator
{
    /**
     * Password is the phase-one credential. OTP verification can be added as a
     * separate method without changing the client guard or portal middleware.
     */
    public function attemptWithPassword(string $mobile, string $password): bool
    {
        $guard = $this->guard();
        $mobile = MobileNormalizer::normalize($mobile);

        if ($mobile === null) {
            return false;
        }

        if (! $guard->attempt([
            'mobile' => $mobile,
            'password' => $password,
            'is_admin' => false,
            'status' => 'active',
        ])) {
            return false;
        }

        return true;
    }

    public function guard(): StatefulGuard
    {
        return Auth::guard('client');
    }
}
