<?php

namespace App\Models;

use App\Support\MobileNormalizer;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements FilamentUser, HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use Notifiable;

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'password',
        'is_admin',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->isActive();
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function isClient(): bool
    {
        return ! $this->is_admin;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)
            ->withPivot(['membership_role', 'is_primary'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected function setMobileAttribute(?string $value): void
    {
        $this->attributes['mobile'] = MobileNormalizer::normalize($value);
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('media_library')
            ->useDisk('public')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
                'image/svg+xml',
                'video/mp4',
                'video/webm',
                'video/quicktime',
            ]);
    }
}
