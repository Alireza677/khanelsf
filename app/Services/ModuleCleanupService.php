<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModuleCleanupService
{
    public function deleteProjects(): array
    {
        return DB::transaction(function (): array {
            $counts = [
                'projects' => Project::query()->count(),
                'project_categories' => ProjectCategory::query()->count(),
            ];

            Project::query()->delete();
            ProjectCategory::query()->delete();

            Log::warning('Projects module records deleted from Site Settings cleanup action.', $counts);

            return $counts;
        });
    }

    public function deleteShop(): array
    {
        return DB::transaction(function (): array {
            $counts = [
                'orders' => Order::query()->count(),
                'order_items' => OrderItem::query()->count(),
                'products' => Product::query()->count(),
                'product_categories' => ProductCategory::query()->count(),
            ];

            Order::query()->delete();
            Product::query()->delete();
            ProductCategory::query()->delete();

            Log::warning('Shop module records deleted from Site Settings cleanup action.', $counts);

            return $counts;
        });
    }

    public function deleteGalleries(): array
    {
        return DB::transaction(function (): array {
            $counts = [
                'galleries' => Gallery::query()->count(),
                'gallery_categories' => GalleryCategory::query()->count(),
            ];

            Gallery::query()->delete();
            GalleryCategory::query()->delete();

            Log::warning('Gallery module records deleted from Site Settings cleanup action.', $counts);

            return $counts;
        });
    }
}
