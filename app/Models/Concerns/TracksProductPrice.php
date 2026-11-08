<?php

namespace App\Models\Concerns;

use App\Models\ProductPriceHistory;
use App\Services\ProductPricingService;

trait TracksProductPrice
{
    public string $priceChangeSource = 'other';

    public ?int $priceChangeActorId = null;

    /** Product and audit writes share a transaction, including ordinary Eloquent saves. */
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $pricing = app(ProductPricingService::class);
            $old = $this->exists
                ? static::query()->lockForUpdate()->findOrFail($this->getKey())
                : null;
            $changed = false;
            foreach (['price', 'sale_price'] as $field) {
                if ($old && ! $this->isDirty($field)) {
                    $this->setAttribute($field, $old->$field);
                    $this->syncOriginalAttribute($field);

                    continue;
                }
                $before = $old?->$field;
                $after = $this->getAttribute($field);
                $before = $before === null ? null : $pricing->amount($before);
                $after = $after === null ? null : $pricing->amount($after);
                $changed = $changed || $before !== $after;
            }
            if ($changed) {
                $this->price_changed_at = now()->startOfSecond();
                $this->price_verified_at = null;
            }
            $saved = parent::save($options);
            if ($saved && $changed) {
                ProductPriceHistory::query()->create([
                    'product_id' => $this->getKey(),
                    'user_id' => $this->priceChangeActorId ?? auth()->id(),
                    'old_price' => $old?->price, 'new_price' => $this->price,
                    'old_sale_price' => $old?->sale_price, 'new_sale_price' => $this->sale_price,
                    'event' => 'price_changed', 'source' => $this->priceChangeSource,
                    'price_verified_at' => null, 'occurred_at' => now(),
                ]);
            }

            return $saved;
        });
    }
}
