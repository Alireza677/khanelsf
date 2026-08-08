<?php

namespace App\Http\Middleware;

use App\Services\ClientCustomerResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareClientPortalContext
{
    public function __construct(private readonly ClientCustomerResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('client');
        $requestedCustomerId = $request->integer('customer') ?: null;
        $customers = $this->resolver->accessibleCustomers($user);
        $customer = $this->resolver->resolve($user, $requestedCustomerId);

        $request->attributes->set('portalCustomers', $customers);
        $request->attributes->set('portalCustomer', $customer);

        View::share([
            'portalUser' => $user,
            'portalCustomers' => $customers,
            'portalCustomer' => $customer,
        ]);

        return $next($request);
    }
}
