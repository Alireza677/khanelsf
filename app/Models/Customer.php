<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'display_name',
        'company_name',
        'mobile',
        'email',
        'address',
        'notes',
        'status',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['membership_role', 'is_primary'])
            ->withTimestamps();
    }

    public function clientProjects(): HasMany
    {
        return $this->hasMany(ClientProject::class);
    }

    public function primaryUser(): ?User
    {
        return $this->users->first(fn (User $user): bool => (bool) $user->pivot->is_primary);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
