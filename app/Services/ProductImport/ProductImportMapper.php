<?php

namespace App\Services\ProductImport;

use App\Services\ProductPricingService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class ProductImportMapper
{
    public const PRODUCT_HEADERS = [
        'sku', 'title', 'slug', 'category_slug', 'category_name', 'price', 'sale_price', 'status',
        'stock_status', 'has_stock', 'excerpt', 'content', 'seo_title', 'seo_description',
        'robots_index', 'robots_follow', 'is_featured', 'sort_order',
    ];

    public const SPEC_HEADERS = ['sku', 'group_name', 'key', 'label', 'value', 'unit', 'sort_order'];

    public const CATEGORY_HEADERS = ['slug', 'name', 'description', 'status', 'sort_order'];

    public const SAFE_DEFAULTS = [
        'sale_price' => null, 'status' => 'draft', 'stock_status' => 'out_of_stock',
        'has_stock' => false, 'robots_index' => false, 'robots_follow' => true,
        'is_featured' => false, 'sort_order' => 0,
    ];

    public function product(array $row): array
    {
        $data = $this->nonblank($row, self::PRODUCT_HEADERS);
        $errors = [];
        $notices = [];
        if (isset($data['content'])) {
            $safeHtml = Str::sanitizeHtml($data['content']);
            if ($safeHtml !== $data['content']) {
                $notices[] = 'محتوای HTML با پاک‌ساز موجود CMS ایمن‌سازی شد.';
                $data['content'] = $safeHtml;
            }
        }
        foreach (['has_stock', 'robots_index', 'robots_follow', 'is_featured'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = strtolower($this->digits($data[$field]));
            if (! in_array($value, ['0', '1', 'true', 'false'], true)) {
                $errors[] = "{$field}: فقط ۰، ۱، true یا false مجاز است.";
            } else {
                $data[$field] = in_array($value, ['1', 'true'], true);
            }
        }
        foreach (['price', 'sale_price'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            try {
                $data[$field] = app(ProductPricingService::class)->amount($this->digits($data[$field]));
            } catch (InvalidArgumentException $exception) {
                $errors[] = "{$field}: {$exception->getMessage()}";
            }
        }
        if (isset($data['sort_order'])) {
            $data['sort_order'] = $this->digits($data['sort_order']);
        }
        $validator = Validator::make($data, [
            'sku' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'not_regex:/[\s\/?#\\\\]/u'],
            'category_slug' => ['sometimes', 'string', 'max:255'],
            'category_name' => ['sometimes', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'numeric', 'min:0', 'lt:price'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'stock_status' => ['sometimes', Rule::in(['in_stock', 'out_of_stock'])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'seo_title' => ['sometimes', 'string', 'max:70'],
            'seo_description' => ['sometimes', 'string', 'max:160'],
        ], [], ['sku' => 'SKU', 'title' => 'عنوان', 'slug' => 'نامک', 'price' => 'قیمت']);

        return [$data, [...$errors, ...$validator->errors()->all()], $notices];
    }

    public function specification(array $row): array
    {
        $data = $this->nonblank($row, self::SPEC_HEADERS);
        if (isset($data['sort_order'])) {
            $data['sort_order'] = $this->digits($data['sort_order']);
        }
        $validator = Validator::make($data, [
            'sku' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255'],
            'group_name' => ['sometimes', 'string', 'max:255'],
            'label' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'string'],
            'unit' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ]);

        return [$data, $validator->errors()->all()];
    }

    public function category(array $row): array
    {
        $data = $this->nonblank($row, self::CATEGORY_HEADERS);
        $data += ['status' => 'active', 'sort_order' => 0];
        $data['sort_order'] = $this->digits($data['sort_order']);
        $validator = Validator::make($data, [
            'slug' => ['required', 'string', 'max:255', 'not_regex:/[\s\/?#\\\\]/u'],
            'name' => ['required', 'string', 'max:255'],
            'status' => [Rule::in(['active', 'inactive'])],
            'sort_order' => ['integer', 'min:0', 'max:4294967295'],
        ]);

        return [$data, $validator->errors()->all()];
    }

    public function identity(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    public function specificationKey(array $row): string
    {
        return json_encode([$row['group_name'] ?? '', $row['key'] ?? ''], JSON_UNESCAPED_UNICODE);
    }

    private function nonblank(array $row, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $value = $row[$field] ?? null;
            if ($value !== null && is_scalar($value) && trim((string) $value) !== '') {
                $data[$field] = trim((string) $value);
            }
        }

        return $data;
    }

    private function digits(mixed $value): string
    {
        return strtr((string) $value, array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩٫', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789.'),
        ));
    }
}
