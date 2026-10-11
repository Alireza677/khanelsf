<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DeliverableType extends Model
{
    protected $attributes = ['unit' => 'count', 'active' => true];

    protected $fillable = ['code', 'name', 'description', 'unit', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $type) => Validator::make($type->getAttributes(), [
            'code' => ['required', 'string', 'max:255', Rule::unique('deliverable_types')->ignore($type->id)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:100'],
        ])->validate());
    }
}
