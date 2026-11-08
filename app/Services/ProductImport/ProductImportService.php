<?php

namespace App\Services\ProductImport;

use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ProductImportService
{
    public function __construct(private readonly ProductWorkbookReader $reader, private readonly ProductImportMapper $mapper) {}

    public function authorize(User $actor, ?ProductImport $import = null): void
    {
        abort_unless(auth()->id() === $actor->id && $actor->isAdmin() && $actor->isActive()
            && app(ModuleService::class)->shopEnabled() && ProductResource::canViewAny() && ProductResource::canCreate(), 403);
        if ($import) {
            abort_unless((int) $import->user_id === (int) $actor->id, 403);
        }
    }

    public function stage(UploadedFile $file, User $actor): ProductImport
    {
        $this->authorize($actor);
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx' || $file->getSize() > ProductWorkbookReader::MAX_BYTES) {
            $this->fail('فقط فایل XLSX با حجم حداکثر ۱۰ مگابایت پذیرفته می‌شود.');
        }
        $source = $this->reader->read($file->getRealPath());
        $categories = [];
        foreach ($source['Categories'] as $row) {
            [$data, $errors] = $this->mapper->category($row);
            $slug = $this->mapper->identity($data['slug'] ?? '');
            $data['_errors'] = [...$row['__errors'], ...$errors];
            if (isset($categories[$slug])) {
                $data['_errors'][] = 'نامک دسته در فایل تکراری است.';
            }
            $categories[$slug] = $data;
        }
        $products = [];
        $skuCounts = $slugCounts = [];
        foreach ($source['Import_Products'] as $row) {
            [$data, $errors, $notices] = $this->mapper->product($row);
            $row['__warnings'] = [...$row['__warnings'], ...$notices];
            $products[] = [$row, $data, [...$row['__errors'], ...$errors]];
            $sku = $this->mapper->identity($data['sku'] ?? '');
            $slug = $this->mapper->identity($data['slug'] ?? '');
            $skuCounts[$sku] = ($skuCounts[$sku] ?? 0) + 1;
            $slugCounts[$slug] = ($slugCounts[$slug] ?? 0) + 1;
        }
        $specs = $specErrors = $specWarnings = [];
        $eligible = $ignored = 0;
        foreach ($source['Specifications_Proposed'] as $row) {
            $sku = $this->mapper->identity($row['sku'] ?? '');
            if ($sku === '' || ! isset($skuCounts[$sku])) {
                $ignored++;

                continue;
            }
            $eligible++;
            [$data, $errors] = $this->mapper->specification($row);
            $specErrors[$sku] = [...($specErrors[$sku] ?? []), ...array_map(
                fn ($error) => "مشخصات، ردیف {$row['__row']}: {$error}", [...$row['__errors'], ...$errors],
            )];
            unset($data['sku']);
            $key = $this->mapper->specificationKey($data);
            if (isset($specs[$sku][$key])) {
                if ($specs[$sku][$key] === $data) {
                    $specWarnings[$sku][] = "مشخصه تکراری یکسان در ردیف {$row['__row']} فقط یک بار نگهداری شد.";
                } else {
                    $specErrors[$sku][] = "مشخصه متناقض با کلید یکسان در ردیف {$row['__row']}.";
                }
            } else {
                $specs[$sku][$key] = $data;
            }
        }
        $path = 'sources/'.Str::uuid().'.xlsx';
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            Storage::disk('product_imports')->put($path, $stream);
        } finally {
            fclose($stream);
        }
        try {
            $import = DB::transaction(function () use ($actor, $file, $path, $source, $categories, $products, $skuCounts, $slugCounts, $specs, $specErrors, $specWarnings, $eligible, $ignored): ProductImport {
                $duplicates = Product::query()->whereNotNull('sku')->whereRaw("TRIM(sku) <> ''")
                    ->selectRaw('LOWER(TRIM(sku)) AS identity, COUNT(*) AS total')->groupByRaw('LOWER(TRIM(sku))')->havingRaw('COUNT(*) > 1')->get()->count();
                $warnings = $duplicates ? ["{$duplicates} گروه SKU تکراری در داده‌های قبلی وجود دارد؛ ردیف‌های مربوط تا رفع تعارض وارد نمی‌شوند."] : [];
                $import = ProductImport::query()->create([
                    'user_id' => $actor->id, 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'file_path' => $path, 'sha256' => hash_file('sha256', $file->getRealPath()),
                    'sheet_names' => $source['sheet_names'], 'categories' => $categories,
                    'options' => ['categories' => []], 'warnings' => $warnings,
                    'summary' => ['products' => count($products), 'source_specifications' => count($source['Specifications_Proposed']),
                        'eligible_specifications' => $eligible, 'ignored_specifications' => $ignored],
                ]);
                foreach ($products as [$sourceRow, $data, $errors]) {
                    $sku = $this->mapper->identity($data['sku'] ?? '');
                    $slug = $this->mapper->identity($data['slug'] ?? '');
                    if (($sku !== '' && $skuCounts[$sku] > 1) || ($slug !== '' && $slugCounts[$slug] > 1)) {
                        $data['_duplicate'] = true;
                        $errors[] = 'SKU یا نامک در شیت محصولات تکراری است؛ تمام ردیف‌های متعارض رد می‌شوند.';
                    }
                    $import->rows()->create([
                        'sheet_row' => $sourceRow['__row'], 'sku' => mb_substr($data['sku'] ?? '', 0, 255),
                        'title' => mb_substr($data['title'] ?? '', 0, 255), 'category_slug' => mb_substr($data['category_slug'] ?? '', 0, 255),
                        'payload' => $data, 'specifications' => array_values($specs[$sku] ?? []),
                        'source_errors' => [...$errors, ...($specErrors[$sku] ?? [])],
                        'source_warnings' => [...$sourceRow['__warnings'], ...($specWarnings[$sku] ?? [])],
                        'errors' => [], 'warnings' => [], 'decision' => [],
                    ]);
                }

                return $import;
            });

            return $this->refresh($import, $actor);
        } catch (Throwable $exception) {
            if (! isset($import)) {
                Storage::disk('product_imports')->delete($path);
            }
            throw $exception;
        }
    }

    public function refresh(ProductImport $import, User $actor): ProductImport
    {
        return DB::transaction(function () use ($import, $actor): ProductImport {
            $import = ProductImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->authorize($actor, $import);
            $this->assertPreview($import);
            $import->rows()->chunkById(100, function ($rows) use ($import): void {
                foreach ($rows as $row) {
                    $row->update($this->plan($import, $row));
                }
            });
            $targets = $import->rows()->whereNotNull('product_id')->get()->groupBy('product_id');
            foreach ($targets as $rows) {
                if ($rows->count() > 1) {
                    foreach ($rows as $row) {
                        $row->update(['action' => 'duplicate', 'errors' => [...$row->errors, 'چند ردیف فایل به یک محصول موجود متصل شده‌اند.']]);
                    }
                }
            }

            return $this->summarize($import);
        });
    }

    public function configure(ProductImport $import, User $actor, string $mode, array $categoryMappings): ProductImport
    {
        return DB::transaction(function () use ($import, $actor, $mode, $categoryMappings): ProductImport {
            $import = ProductImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->authorize($actor, $import);
            $this->assertPreview($import);
            if (! in_array($mode, ['create_only', 'create_update'], true)) {
                $this->fail('حالت درون‌ریزی معتبر نیست.');
            }
            foreach ($categoryMappings as $target) {
                if (! is_string($target) && ! is_int($target)) {
                    $this->fail('تصمیم دسته‌بندی معتبر نیست.');
                }
                if (! in_array($target, ['create', 'pending'], true) && ! ProductCategory::query()->whereKey($target)->exists()) {
                    $this->fail('دسته انتخاب‌شده وجود ندارد.');
                }
            }
            $import->update(['mode' => $mode, 'options' => ['categories' => $categoryMappings]]);

            return $this->refresh($import, $actor);
        });
    }

    public function decide(ProductImport $import, ProductImportRow $row, User $actor, array $decision): ProductImport
    {
        return DB::transaction(function () use ($import, $row, $actor, $decision): ProductImport {
            $import = ProductImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->authorize($actor, $import);
            $this->assertPreview($import);
            abort_unless((int) $row->product_import_id === (int) $import->id, 404);
            $decision = Arr::only($decision, ['product_id', 'update', 'confirm_identity', 'allow_similar']);
            Validator::make($decision, [
                'product_id' => ['nullable', 'integer', 'exists:products,id'],
                'update' => ['sometimes', 'boolean'], 'confirm_identity' => ['sometimes', 'boolean'],
                'allow_similar' => ['sometimes', 'boolean'],
            ])->validate();
            foreach (['update', 'confirm_identity', 'allow_similar'] as $flag) {
                $decision[$flag] = (bool) ($decision[$flag] ?? false);
            }
            if (! empty($decision['product_id']) && ! Product::query()->whereKey($decision['product_id'])->exists()) {
                $this->fail('محصول انتخاب‌شده وجود ندارد.');
            }
            $row->update(['decision' => $decision]);

            return $this->refresh($import, $actor);
        });
    }

    public function execute(ProductImport $import, User $actor, bool $confirmed = false, ?int $limit = null): ProductImport
    {
        $this->authorize($actor, $import);
        if (! $confirmed) {
            $this->fail('اجرای درون‌ریزی به تأیید صریح نیاز دارد.');
        }

        return Cache::store('database')->lock('king-product-import-batch-'.$import->id, 30)->block(2,
            fn () => $this->process($import, $actor, $limit === null ? null : max(1, min(20, $limit)), false));
    }

    public function continueExecution(ProductImport $import, User $actor): ProductImport
    {
        $this->authorize($actor, $import);

        return Cache::store('database')->lock('king-product-import-batch-'.$import->id, 30)->get(
            fn () => $this->process($import, $actor, 20, true),
        ) ?: $import->fresh();
    }

    private function process(ProductImport $import, User $actor, ?int $limit, bool $continuing): ProductImport
    {
        $proceed = DB::transaction(function () use ($import, $continuing): bool {
            $current = ProductImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($current->status === 'completed') {
                return false;
            }
            if ($continuing && $current->status !== 'processing') {
                $this->fail('ادامه پردازش فقط برای درون‌ریزی تأییدشده و آغازشده مجاز است.');
            }
            if (! $continuing && $current->status === 'processing' && $current->updated_at->gt(now()->subMinutes(10))) {
                $this->fail('این درون‌ریزی در حال اجراست. برای ادامه اجرای قطع‌شده، ۱۰ دقیقه صبر کنید.');
            }
            $current->update(['status' => 'processing']);

            return true;
        });
        if (! $proceed) {
            return $import->fresh();
        }
        $import = $import->fresh();
        $pending = $import->rows()->whereNull('result');
        if ($limit !== null) {
            $ids = $import->rows()->whereNull('result')->limit($limit)->pluck('id');
            $pending->whereIn('id', $ids);
        }
        $deadline = microtime(true) + 8;
        $pending->chunkById(50, function ($rows) use ($import, $actor, $limit, $deadline) {
            foreach ($rows as $row) {
                if ($limit !== null && microtime(true) >= $deadline) {
                    return false;
                }
                try {
                    // A database-backed lock serializes importer writers across workers/hosts.
                    Cache::store('database')->lock('king-product-import-write', 30)->block(5, function () use ($import, $row, $actor): void {
                        DB::transaction(function () use ($import, $row, $actor): void {
                            $this->authorize($actor, $import);
                            $row = ProductImportRow::query()->lockForUpdate()->findOrFail($row->id);
                            if ($row->result !== null) {
                                return;
                            }
                            if (in_array($row->action, ['error', 'duplicate', 'review'], true)) {
                                $row->update(['result' => $row->action === 'error' ? 'error' : 'rejected']);

                                return;
                            }
                            $plan = $this->plan($import, $row, true);
                            if (in_array($plan['action'], ['error', 'duplicate', 'review'], true)) {
                                throw ValidationException::withMessages(['row' => [...$plan['errors'], ...$plan['warnings']]]);
                            }
                            if ($row->baseline_hash !== $plan['baseline_hash'] && $plan['action'] !== 'skip') {
                                $this->fail('اطلاعات یا تطبیق محصول پس از پیش‌نمایش تغییر کرده است؛ فایل را دوباره بررسی کنید.');
                            }
                            if (in_array($plan['action'], ['skip', 'unchanged'], true)) {
                                $row->update([...$plan, 'result' => $plan['action'] === 'skip' ? 'skipped' : 'unchanged']);

                                return;
                            }
                            $product = $plan['product_id'] ? Product::query()->lockForUpdate()->findOrFail($plan['product_id']) : new Product;
                            abort_unless($product->exists ? ProductResource::canEdit($product) : ProductResource::canCreate(), 403);
                            $category = $this->category($import, $row->payload);
                            if ($category['create']) {
                                abort_unless(ProductCategoryResource::canCreate(), 403);
                                $record = ProductCategory::query()->create(Arr::except($category['definition'], ['_errors']));
                                $category['id'] = $record->id;
                            }
                            $attributes = $this->attributes($row->payload, $category['id'], ! $product->exists);
                            $created = ! $product->exists;
                            $product->priceChangeSource = 'import';
                            $product->priceChangeActorId = $actor->id;
                            $product->fill($attributes)->save();
                            $this->syncSpecifications($product, $row->specifications);
                            $row->update([...$plan, 'product_id' => $product->id, 'result' => $created ? 'created' : 'updated']);
                        }, 3);
                    });
                } catch (Throwable $exception) {
                    report($exception);
                    $errors = $exception instanceof ValidationException ? Arr::flatten($exception->errors()) : ['ثبت این ردیف انجام نشد؛ گزارش خطای سرور و دسترسی‌ها بررسی شود.'];
                    $row->update(['result' => 'error', 'errors' => $errors]);
                }
                $import->touch();
            }
        });
        if (! $import->rows()->whereNull('result')->exists()) {
            $import->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return $this->summarize($import);
    }

    public function approveUpdates(ProductImport $import, User $actor, array $rowIds): ProductImport
    {
        return DB::transaction(function () use ($import, $actor, $rowIds): ProductImport {
            $import = ProductImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->authorize($actor, $import);
            $this->assertPreview($import);
            if ($import->mode !== 'create_update') {
                $this->fail('ابتدا حالت ایجاد و به‌روزرسانی را انتخاب کنید.');
            }
            foreach ($import->rows()->whereIn('id', $rowIds)->get() as $row) {
                $row->update(['decision' => [...$row->decision, 'update' => true]]);
            }

            return $this->refresh($import, $actor);
        });
    }

    public function report(ProductImport $import, User $actor): StreamedResponse
    {
        $this->authorize($actor, $import);

        return response()->streamDownload(function () use ($import): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ردیف اکسل', 'SKU', 'عنوان', 'عملیات', 'نتیجه', 'خطاها', 'هشدارها']);
            foreach ($import->rows()->cursor() as $row) {
                $values = [$row->sheet_row, $row->sku, $row->title, $row->action, $row->result, implode(' | ', $row->errors), implode(' | ', $row->warnings)];
                $values = array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : (string) $value, $values);
                fputcsv($stream, $values);
            }
            fclose($stream);
        }, 'product-import-'.$import->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function plan(ProductImport $import, ProductImportRow $row, bool $lock = false): array
    {
        $data = $row->payload;
        $errors = $row->source_errors;
        $warnings = $row->source_warnings;
        $product = null;
        $action = ! empty($data['_duplicate']) ? 'duplicate' : 'new';
        if ($errors === []) {
            $matches = Product::query()->whereRaw('LOWER(TRIM(sku)) = ?', [$this->mapper->identity($data['sku'])])->when($lock, fn ($query) => $query->lockForUpdate())->get();
            if ($matches->count() > 1) {
                $errors[] = 'چند محصول با این SKU در دیتابیس وجود دارد؛ تطبیق خودکار مجاز نیست.';
            } else {
                $product = $matches->first();
            }
            if (! empty($row->decision['product_id'])) {
                $selected = Product::query()->when($lock, fn ($query) => $query->lockForUpdate())->find($row->decision['product_id']);
                if (! $selected || ($product && ! $selected->is($product))) {
                    $errors[] = 'محصول انتخاب‌شده با مالک SKU تعارض دارد یا حذف شده است.';
                } else {
                    $product = $selected;
                }
            }
            $slugOwner = Product::query()->whereRaw('LOWER(TRIM(slug)) = ?', [$this->mapper->identity($data['slug'])])->first();
            if ($slugOwner && (! $product || ! $slugOwner->is($product))) {
                $errors[] = 'نامک متعلق به محصول دیگری است؛ محصول موجود را صریحاً تطبیق دهید یا فایل را اصلاح کنید.';
            }
        }
        $baseline = $product ? $this->fingerprint($product) : null;
        if ($errors === []) {
            $category = $this->category($import, $data);
            $warnings = [...$warnings, ...$category['warnings']];
            $errors = [...$errors, ...$category['errors']];
            if ($product && $import->mode === 'create_only') {
                $action = 'skip';
                $warnings[] = 'محصول موجود است؛ در حالت فقط ایجاد، هیچ فیلد یا مشخصه‌ای تغییر نمی‌کند.';
            } elseif ($category['pending']) {
                $action = 'review';
            } elseif ($product) {
                $attributes = $this->attributes($data, $category['id'], false);
                $identityChanged = ($product->sku !== $data['sku'] || $product->slug !== $data['slug']);
                $effectiveSale = $attributes['sale_price'] ?? $product->sale_price;
                if ($effectiveSale !== null && bccomp((string) $effectiveSale, (string) $attributes['price'], 2) >= 0) {
                    $errors[] = 'قیمت ویژه موجود با قیمت جدید سازگار نیست؛ مقدار معتبر را صریحاً در فایل وارد کنید.';
                }
                $specChanges = $this->specificationChanges($product, $row->specifications);
                $errors = [...$errors, ...$specChanges['errors']];
                $changed = $category['create'] || $this->different($product, $attributes) || $specChanges['changed'];
                if (! $changed) {
                    $action = 'unchanged';
                } elseif (empty($row->decision['update']) || ($identityChanged && empty($row->decision['confirm_identity']))) {
                    $action = 'review';
                    $warnings[] = $identityChanged ? 'تغییر SKU یا نامک نیازمند تأیید صریح هویت و به‌روزرسانی این ردیف است.' : 'به‌روزرسانی این ردیف را تأیید کنید.';
                } else {
                    $action = 'update';
                }
            } elseif (empty($row->decision['allow_similar'])) {
                $candidates = $this->similarProducts($import, $data, $row->specifications);
                if ($candidates !== []) {
                    $action = 'review';
                    $warnings[] = 'محصول مشابه پیدا شد: '.implode('، ', $candidates).'؛ محصول موجود را تطبیق دهید یا ایجاد کالای متفاوت را تأیید کنید.';
                }
            }
        }
        if ($errors !== [] && $action !== 'duplicate') {
            $action = 'error';
        }

        return ['action' => $action, 'errors' => $errors, 'warnings' => $warnings, 'product_id' => $product?->id, 'baseline_hash' => $baseline];
    }

    private function category(ProductImport $import, array $data): array
    {
        $result = ['id' => null, 'create' => false, 'pending' => false, 'definition' => [], 'errors' => [], 'warnings' => []];
        if (empty($data['category_slug'])) {
            return $result;
        }
        $slug = $this->mapper->identity($data['category_slug']);
        $existing = ProductCategory::query()->whereRaw('LOWER(TRIM(slug)) = ?', [$slug])->get();
        if ($existing->count() > 1) {
            $result['errors'][] = 'چند دسته با نامک متناظر وجود دارد.';

            return $result;
        }
        if ($existing->isNotEmpty()) {
            $result['id'] = $existing->first()->id;

            return $result;
        }
        $mapping = $import->options['categories'][$slug] ?? 'pending';
        if (is_numeric($mapping)) {
            $result['id'] = ProductCategory::query()->find($mapping)?->id;
            if (! $result['id']) {
                $result['errors'][] = 'دسته تطبیق‌یافته دیگر وجود ندارد.';
            }

            return $result;
        }
        $definition = $import->categories[$slug] ?? null;
        if (! $definition || ! empty($definition['_errors'])) {
            $result['errors'][] = 'تعریف معتبر این دسته در شیت Categories پیدا نشد: '.$data['category_slug'];
            $result['errors'] = [...$result['errors'], ...($definition['_errors'] ?? [])];
        } elseif ($mapping !== 'create') {
            $result['pending'] = true;
            $sameName = ProductCategory::query()->where('name', $definition['name'])->exists();
            $result['warnings'][] = $sameName ? 'دسته هم‌نام با نامک دیگر وجود دارد؛ تطبیق یا ایجاد دسته را صریحاً انتخاب کنید.' : 'ایجاد دسته جدید یا تطبیق آن با دسته موجود را انتخاب کنید.';
        } else {
            $result['create'] = true;
            $result['definition'] = $definition;
            $result['warnings'][] = 'دسته جدید پس از تأیید نهایی ایجاد می‌شود: '.$definition['name'];
        }

        return $result;
    }

    private function attributes(array $data, ?int $categoryId, bool $creating): array
    {
        $attributes = Arr::only($data, array_diff(ProductImportMapper::PRODUCT_HEADERS, ['category_slug', 'category_name']));
        if (! empty($data['category_slug'])) {
            $attributes['product_category_id'] = $categoryId;
        }

        return $creating ? $attributes + ProductImportMapper::SAFE_DEFAULTS : $attributes;
    }

    private function specificationChanges(Product $product, array $incoming): array
    {
        $existing = $product->specifications()->get()->groupBy(fn ($spec) => $this->mapper->specificationKey($spec->attributesToArray()));
        $changed = false;
        $errors = [];
        foreach ($incoming as $data) {
            $matches = $existing->get($this->mapper->specificationKey($data));
            if ($matches?->count() > 1) {
                $errors[] = 'چند مشخصه موجود با گروه و کلید یکسان وجود دارد: '.($data['key'] ?? '');
            } elseif (! $matches || $this->different($matches->first(), $data)) {
                $changed = true;
            }
        }

        return compact('changed', 'errors');
    }

    private function syncSpecifications(Product $product, array $incoming): void
    {
        foreach ($incoming as $data) {
            $query = $product->specifications()->where('key', $data['key']);
            $group = $data['group_name'] ?? null;
            $group === null ? $query->where(fn ($q) => $q->whereNull('group_name')->orWhere('group_name', '')) : $query->where('group_name', $group);
            $matches = $query->lockForUpdate()->get();
            if ($matches->count() > 1) {
                $this->fail('مشخصه موجود مبهم است؛ این ردیف بدون تغییر بازگردانده شد.');
            }
            if ($matches->isNotEmpty()) {
                $matches->first()->fill($data)->save();
            } else {
                $product->specifications()->create($data + ['sort_order' => 0]);
            }
        }
    }

    private function different(Model $model, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if (is_bool($value) ? (bool) $model->getAttribute($key) !== $value : (string) $model->getAttribute($key) !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    private function fingerprint(Product $product): string
    {
        return hash('sha256', json_encode([$product->getAttributes(), $product->specifications()->orderBy('id')->get()->map->getAttributes()->all()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function similarProducts(ProductImport $import, array $data, array $specs): array
    {
        $normalize = fn ($text) => trim(preg_replace('/[\s\p{P}\p{S}]+/u', ' ', mb_strtolower(strtr($text, ['ي' => 'ی', 'ك' => 'ک']))));
        $title = $normalize($data['title']);
        $word = explode(' ', $title)[0];
        $candidates = Product::query()->with('specifications')
            ->whereNotIn('id', $import->rows()->reorder()->where('result', 'created')->whereNotNull('product_id')->select('product_id'))
            ->where('title', 'like', '%'.addcslashes($word, '%_\\').'%')->limit(100)->get();
        $matches = [];
        foreach ($candidates as $candidate) {
            similar_text($title, $normalize($candidate->title), $percent);
            $matchingSpecs = 0;
            foreach ($specs as $spec) {
                if (isset($spec['value']) && $candidate->specifications->contains(fn ($current) => $current->key === $spec['key'] && (string) $current->value === $spec['value'] && (string) $current->unit === ($spec['unit'] ?? ''))) {
                    $matchingSpecs++;
                }
            }
            if ($percent >= 85 || ($percent >= 60 && $matchingSpecs >= 2)) {
                $matches[] = '#'.$candidate->id.' '.$candidate->title;
            }
        }

        return array_slice($matches, 0, 5);
    }

    private function summarize(ProductImport $import): ProductImport
    {
        $actions = $import->rows()->selectRaw('action, COUNT(*) as total')->groupBy('action')->pluck('total', 'action')->all();
        $results = $import->rows()->whereNotNull('result')->selectRaw('result, COUNT(*) as total')->groupBy('result')->pluck('total', 'result')->all();
        $usedCategories = $import->rows()->whereNotNull('category_slug')->where('category_slug', '!=', '')->distinct()->count('category_slug');
        $newProducts = $import->rows()->where(fn ($query) => $query->where('action', 'new')
            ->orWhere(fn ($pending) => $pending->whereNull('product_id')->where('action', 'review')))->count();
        $updatableProducts = $import->rows()->whereNotNull('product_id')->whereIn('action', ['update', 'review'])->count();
        $categoryCounts = ['existing' => 0, 'new' => 0, 'pending' => 0];
        foreach ($import->rows()->where('category_slug', '!=', '')->get()->unique('category_slug') as $row) {
            $category = $this->category($import, $row->payload);
            $key = $category['id'] ? 'existing' : ($category['create'] ? 'new' : 'pending');
            $categoryCounts[$key]++;
        }
        $import->update(['summary' => [...$import->summary, 'actions' => $actions, 'results' => $results,
            'used_categories' => $usedCategories, 'category_counts' => $categoryCounts,
            'new_products' => $newProducts, 'updatable_products' => $updatableProducts]]);

        return $import->fresh();
    }

    private function assertPreview(ProductImport $import): void
    {
        if ($import->fresh()->status !== 'preview') {
            $this->fail('تصمیم‌ها فقط پیش از شروع درون‌ریزی قابل تغییرند. برای بررسی مجدد، فایل را دوباره بارگذاری کنید.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['import' => $message]);
    }
}
