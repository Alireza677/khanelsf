<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductTag extends Model
{
    use HasCatalogIdentity;

    protected $fillable = ['name', 'slug'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_tag');
    }
}
