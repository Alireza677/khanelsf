<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicOrderAccess
{
    public function paginateFor(User $user, int $perPage = 12): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->getKey())
            ->withCount('items')
            ->latest()
            ->paginate($perPage);
    }

    public function findFor(User $user, int $orderId): Order
    {
        return Order::query()
            ->where('user_id', $user->getKey())
            ->with('items')
            ->findOrFail($orderId);
    }
}
