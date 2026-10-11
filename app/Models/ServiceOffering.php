<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceOffering extends Model
{
    protected $attributes = ['status' => 'draft', 'sort_order' => 0];

    protected $fillable = ['service_id', 'name', 'description', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $offering): void {
            // Saving runs before creating, so generate before validating required code.
            if (! $offering->exists) {
                $offering->code = (string) Str::ulid();
            } elseif ($offering->isDirty('code')) {
                throw ValidationException::withMessages(['code' => 'کد سیستمی بسته پس از ایجاد قابل تغییر نیست.']);
            }

            Validator::make($offering->getAttributes(), [
                'service_id' => ['required', 'exists:services,id'],
                'code' => ['required', 'string', 'max:255', Rule::unique('service_offerings')->ignore($offering->id)],
                'name' => ['required', 'string', 'max:255'],
                'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
                'sort_order' => ['required', 'integer', 'min:0'],
            ])->validate();
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServiceOfferingVersion::class)->orderByDesc('version');
    }
}
