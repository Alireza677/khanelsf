<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ProductPriceHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2', 'new_price' => 'decimal:2',
            'old_sale_price' => 'decimal:2', 'new_sale_price' => 'decimal:2',
            'price_verified_at' => 'datetime', 'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('سابقه قیمت قابل بازنویسی نیست.'));
        static::deleting(fn () => throw new LogicException('سابقه قیمت قابل حذف نیست.'));
    }
}
