<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliverableType extends Model
{
    protected $attributes = ['unit' => 'count', 'active' => true];

    protected $fillable = ['name', 'description', 'unit', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            // Saving runs before creating, so generate before validating required code.
            if (! $type->exists) {
                $type->code = (string) Str::ulid();
            } elseif ($type->isDirty('code')) {
                throw ValidationException::withMessages(['code' => 'کد سیستمی نوع تحویل پس از ایجاد قابل تغییر نیست.']);
            }

            Validator::make($type->getAttributes(), [
                'code' => ['required', 'string', 'max:255', Rule::unique('deliverable_types')->ignore($type->id)],
                'name' => ['required', 'string', 'max:255'],
                'unit' => ['required', 'string', 'max:100'],
            ])->validate();
        });
    }
}
