<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerMembershipManager
{
    public function assign(Customer $customer, User $user, string $role, bool $isPrimary = false): void
    {
        $this->ensureAssignableClient($user);
        $this->ensureValidRole($role);

        DB::transaction(function () use ($customer, $user, $role, $isPrimary): void {
            if ($isPrimary) {
                DB::table('customer_user')
                    ->where('customer_id', $customer->getKey())
                    ->update(['is_primary' => false]);
            }

            $customer->users()->syncWithoutDetaching([
                $user->getKey() => [
                    'membership_role' => $role,
                    'is_primary' => $isPrimary,
                ],
            ]);
        });
    }

    public function remove(Customer $customer, User $user): void
    {
        $customer->users()->detach($user);
    }

    private function ensureAssignableClient(User $user): void
    {
        if (! $user->isClient() || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'user_id' => 'فقط کاربر مشتری فعال قابل انتخاب است.',
            ]);
        }
    }

    private function ensureValidRole(string $role): void
    {
        if (! in_array($role, ['owner', 'member'], true)) {
            throw ValidationException::withMessages([
                'membership_role' => 'نقش عضویت معتبر نیست.',
            ]);
        }
    }
}
