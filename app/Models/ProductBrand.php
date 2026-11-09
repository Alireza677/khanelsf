<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogIdentity;
use App\Models\Concerns\HasFeaturedImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductBrand extends Model implements HasMedia
{
    use HasCatalogIdentity;
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $fillable = ['name', 'slug', 'description', 'status', 'sort_order'];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
