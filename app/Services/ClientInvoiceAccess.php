<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ClientInvoiceAccess
{
    public function paginate(User $user, int $perPage = 12): LengthAwarePaginator
    {
        return $this->query($user)->latest('issued_at')->paginate($perPage)->withQueryString();
    }

    public function find(User $user, int $id): Invoice
    {
        return $this->query($user)->with('items')->findOrFail($id);
    }

    private function query(User $user)
    {
        return Invoice::query()->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])
            ->whereIn('customer_id', $user->customers()->where('customers.status', 'active')->select('customers.id'));
    }
}
