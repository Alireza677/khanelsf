<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientProjectAccess
{
    public function paginateFor(?Customer $customer, int $perPage = 12): LengthAwarePaginator
    {
        return ClientProject::query()
            ->when(
                $customer,
                fn ($query) => $query->forCustomer($customer),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findFor(?Customer $customer, int $projectId): ClientProject
    {
        abort_unless($customer, 404);

        return ClientProject::query()
            ->forCustomer($customer)
            ->findOrFail($projectId);
    }
}
