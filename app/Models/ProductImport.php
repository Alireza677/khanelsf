<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImport extends Model
{
    protected $attributes = ['status' => 'preview', 'mode' => 'create_only'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sheet_names' => 'array', 'categories' => 'array', 'options' => 'array',
            'summary' => 'array', 'warnings' => 'array', 'completed_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class)->orderBy('sheet_row');
    }
}
