<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Catalog information only. Never participates in pricing, stock or cart identity. */
final class ProductCatalogService
{
    public function formState(Product $product): array
    {
        return $product->attributeAssignments()->get()->groupBy('product_attribute_id')
            ->map(fn ($items, $attributeId): array => [
                'attribute_id' => (int) $attributeId,
                'value_ids' => $items->pluck('product_attribute_value_id')->all(),
            ])->values()->all();
    }

    public function syncAttributes(Product $product, array $rows): void
    {
        $product->getConnection()->transaction(function () use ($product, $rows): void {
            Product::query()->lockForUpdate()->findOrFail($product->id);
            Validator::make(['catalog_attributes' => $rows], [
                'catalog_attributes' => ['array', 'max:100'],
                'catalog_attributes.*' => ['array'],
                'catalog_attributes.*.attribute_id' => ['required', 'integer', 'distinct'],
                'catalog_attributes.*.value_ids' => ['required', 'array', 'min:1', 'max:100'],
                'catalog_attributes.*.value_ids.*' => ['required', 'integer'],
            ])->validate();
            $existing = $product->attributeAssignments()->get()->keyBy('product_attribute_value_id');
            $attributes = ProductAttribute::query()->whereIn('id', array_column($rows, 'attribute_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $wanted = [];
            $position = 0;
            foreach ($rows as $rowKey => $row) {
                $attribute = $attributes->get($row['attribute_id']);
                $ids = array_map('intval', $row['value_ids']);
                $field = 'catalog_attributes.'.$rowKey.'.value_ids';
                if (! $attribute || $attribute->data_type !== 'choice' || count(array_unique($ids)) !== count($ids)
                    || (! $attribute->is_multiple && count($ids) > 1)) {
                    throw ValidationException::withMessages([$field => 'ویژگی و تعداد مقادیر انتخاب‌شده معتبر نیست.']);
                }
                $values = $attribute->values()->whereIn('id', $ids)->get()->keyBy('id');
                foreach ($ids as $id) {
                    $value = $values->get($id);
                    if (! $value || ((! $existing->has($id)) && ($attribute->status !== 'active' || $value->status !== 'active'))) {
                        throw ValidationException::withMessages([$field => 'مقدار باید متعلق به همین ویژگی و فعال باشد؛ مقدار غیرفعال قبلی قابل نگهداری یا حذف است.']);
                    }
                    $wanted[$id] = ['product_attribute_id' => $attribute->id, 'sort_order' => $position];
                }
                $position++;
            }
            // Validate the entire requested set before removing or changing anything.
            $product->attributeAssignments()->whereNotIn('product_attribute_value_id', array_keys($wanted))->delete();
            foreach ($wanted as $id => $data) {
                $assignment = $existing->get($id) ?? $product->attributeAssignments()->make(['product_attribute_value_id' => $id]);
                $assignment->fill($data)->save();
            }
            $product->unsetRelation('attributeAssignments');
        }, 3);
    }
}
