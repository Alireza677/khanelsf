<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\ImportProducts;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImport;
use App\Models\ProductSpecification;
use App\Models\User;
use App\Services\ProductImport\ProductImportMapper;
use App\Services\ProductImport\ProductImportService;
use App\Services\ProductImport\ProductImportTemplate;
use App\Services\ProductImport\ProductWorkbookReader;
use App\Services\ProductPriceFreshness;
use App\Services\SettingsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->admin()->create();
        $this->actingAs($this->actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('product_imports');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_preview_is_read_only_and_confirmed_import_preserves_persian_identifiers_zero_prices_and_safe_flags(): void
    {
        $batch = $this->stage([$this->product(['sku' => '000123', 'slug' => 'پروفیل-سی-۹۰'])], [], [
            ['sku' => '000123', 'key' => 'thickness', 'value' => '۰٫۹', 'unit' => 'میلی‌متر'],
        ]);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_specifications', 0);
        Storage::disk('product_imports')->assertExists($batch->file_path);
        $this->assertFalse(config('filesystems.disks.product_imports.serve'));
        $this->assertSame(1, $batch->summary['actions']['new']);
        $this->expectNoConfirmation($batch);
        $result = $this->service()->execute($batch, $this->actor, true);
        $this->assertSame(1, $result->summary['results']['created']);
        $product = Product::query()->sole();
        $this->assertSame('000123', $product->sku);
        $this->assertSame('پروفیل-سی-۹۰', $product->slug);
        $this->assertSame('0.00', $product->price);
        $this->assertSame('missing', app(ProductPriceFreshness::class)->status($product));
        $this->assertSame('draft', $product->status);
        $this->assertSame('out_of_stock', $product->stock_status);
        $this->assertFalse($product->has_stock);
        $this->assertFalse($product->robots_index);
        $this->assertSame('۰٫۹', $product->specifications()->sole()->value);
        $this->assertSame('میلی‌متر', $product->specifications()->sole()->unit);
    }

    public function test_230_product_contract_imports_10_categories_and_534_allowed_specs_and_is_idempotent(): void
    {
        [$products, $categories, $specs] = $this->catalogue();
        $file = $this->workbook($products, $categories, $specs, [
            'Review_Items' => [['sku', 'title'], ['REVIEW', 'نباید وارد شود']],
            'Source_Products' => [['sku', 'price'], ['P-001', 999999]],
            'Units_Future' => [['sku'], ['FUTURE']], 'Excluded_Rows' => [['sku'], ['EXCLUDED']], 'Readme' => [['راهنما']],
        ]);
        $batch = $this->service()->stage($file, $this->actor);
        $this->assertCount(8, $batch->sheet_names);
        $this->assertSame(230, $batch->summary['products']);
        $this->assertSame(627, $batch->summary['source_specifications']);
        $this->assertSame(534, $batch->summary['eligible_specifications']);
        $this->assertSame(93, $batch->summary['ignored_specifications']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_categories', 0);
        $batch = $this->approveCategories($batch);
        $result = $this->service()->execute($batch, $this->actor, true);
        $this->assertSame(['created' => 230], $result->summary['results']);
        $this->assertDatabaseCount('products', 230);
        $this->assertDatabaseCount('product_categories', 10);
        $this->assertDatabaseCount('product_specifications', 534);
        $this->assertSame(0, Product::query()->where('status', '!=', 'draft')->count());
        $this->assertSame(0, Product::query()->where('price', '!=', 0)->count());
        $this->assertSame(0, Product::query()->where('has_stock', true)->orWhere('robots_index', true)->count());
        $this->assertSame(0, Product::query()->whereIn('sku', ['REVIEW', 'FUTURE', 'EXCLUDED'])->count());
        $ids = Product::query()->pluck('id', 'sku')->all();
        $repeat = $this->service()->stage($file, $this->actor);
        $repeat = $this->service()->execute($repeat, $this->actor, true);
        $this->assertSame(230, $repeat->summary['results']['skipped']);
        $this->assertSame($ids, Product::query()->pluck('id', 'sku')->all());
        $this->assertDatabaseCount('product_specifications', 534);
        $this->assertSame($repeat->summary, $this->service()->execute($repeat, $this->actor, true)->summary);
    }

    public function test_real_lsf_workbook_in_isolated_test_database(): void
    {
        $path = storage_path('app/imports/khane_lsf_atomic_products_import.xlsx');
        if (! is_file($path)) {
            $this->markTestSkipped('فایل واقعی خانه LSF در مسیر اعلام‌شده موجود نیست؛ داده ساختگی جایگزین آن نمی‌شود.');
        }
        $file = new UploadedFile($path, basename($path), null, null, true);
        $batch = $this->service()->stage($file, $this->actor);
        $this->assertSame(230, $batch->summary['products']);
        $this->assertSame(627, $batch->summary['source_specifications']);
        $this->assertSame(534, $batch->summary['eligible_specifications']);
        $batch = $this->approveCategories($batch);
        $result = $this->service()->execute($batch, $this->actor, true);
        $this->assertSame(['created' => 230], $result->summary['results']);
        $this->assertDatabaseCount('product_categories', 10);
        $this->assertDatabaseCount('product_specifications', 534);
        $this->assertSame(230, Product::query()->where('price', 0)->where('status', 'draft')->where('stock_status', 'out_of_stock')->where('has_stock', false)->where('robots_index', false)->count());
        $repeat = $this->service()->stage($file, $this->actor);
        $repeat = $this->service()->execute($repeat, $this->actor, true);
        $this->assertSame(['skipped' => 230], $repeat->summary['results']);
        $this->assertDatabaseCount('products', 230);
        $this->assertDatabaseCount('product_specifications', 534);
        foreach (Product::query()->get() as $product) {
            $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertOk();
        }
    }

    public function test_duplicate_skus_slugs_and_legacy_database_skus_are_reported_without_partial_products(): void
    {
        $batch = $this->stage([
            $this->product(['sku' => 'DUP', 'slug' => 'one']), $this->product(['sku' => 'dup', 'slug' => 'two']),
            $this->product(['sku' => 'X', 'slug' => 'same']), $this->product(['sku' => 'Y', 'slug' => 'same']),
            $this->product(['sku' => 'OK', 'slug' => 'ok']),
        ]);
        $this->assertSame(4, $batch->summary['actions']['duplicate']);
        $this->service()->execute($batch, $this->actor, true);
        $this->assertDatabaseCount('products', 1);
        Product::factory()->create(['sku' => 'LEGACY']);
        Product::factory()->create(['sku' => 'LEGACY']);
        $legacy = $this->stage([$this->product(['sku' => 'LEGACY', 'slug' => 'legacy'])]);
        $this->assertNotEmpty($legacy->warnings);
        $this->assertSame('error', $legacy->rows()->sole()->action);
        $this->service()->execute($legacy, $this->actor, true);
        $this->assertDatabaseCount('products', 3);
    }

    public function test_slug_conflict_and_sku_less_similar_products_require_explicit_binding_and_identity_confirmation(): void
    {
        $existing = Product::factory()->create(['title' => 'پروفیل آزمایشی', 'slug' => 'existing', 'sku' => null]);
        $batch = $this->stage([$this->product(['title' => $existing->title, 'slug' => 'existing'])]);
        $this->assertSame('error', $batch->rows()->sole()->action);
        $batch = $this->service()->configure($batch, $this->actor, 'create_update', []);
        $batch = $this->service()->decide($batch, $batch->rows()->sole(), $this->actor, ['product_id' => $existing->id, 'update' => true]);
        $this->assertSame('review', $batch->rows()->sole()->action);
        $batch = $this->service()->decide($batch, $batch->rows()->sole(), $this->actor, ['product_id' => $existing->id, 'update' => true, 'confirm_identity' => true]);
        $this->assertSame('update', $batch->rows()->sole()->action);
        $this->service()->execute($batch, $this->actor, true);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('SKU-001', $existing->fresh()->sku);
        $similar = $this->stage([$this->product(['sku' => 'OTHER', 'slug' => 'another', 'title' => $existing->title])]);
        $this->assertSame('review', $similar->rows()->sole()->action);
        $similar = $this->service()->decide($similar, $similar->rows()->sole(), $this->actor, ['allow_similar' => true]);
        $this->service()->execute($similar, $this->actor, true);
        $this->assertDatabaseCount('products', 2);
    }

    public function test_categories_are_reused_or_manually_mapped_and_same_name_is_not_created_automatically(): void
    {
        $existing = ProductCategory::factory()->create(['slug' => 'existing', 'name' => 'قطعات']);
        $batch = $this->stage([$this->product(['category_slug' => 'new-slug'])], [['slug' => 'new-slug', 'name' => 'قطعات']]);
        $this->assertSame('review', $batch->rows()->sole()->action);
        $this->assertDatabaseCount('product_categories', 1);
        $batch = $this->service()->configure($batch, $this->actor, 'create_only', ['new-slug' => $existing->id]);
        $this->service()->execute($batch, $this->actor, true);
        $this->assertSame($existing->id, Product::query()->sole()->product_category_id);
        $this->assertDatabaseCount('product_categories', 1);
        $reuse = $this->stage([$this->product(['sku' => 'REUSE', 'slug' => 'reuse', 'title' => 'کالای دیگر', 'category_slug' => 'existing'])]);
        $this->service()->execute($reuse, $this->actor, true);
        $this->assertDatabaseCount('product_categories', 1);
    }

    public function test_update_requires_row_confirmation_preserves_blanks_media_documents_relations_and_unrelated_specs(): void
    {
        $existing = Product::factory()->create(['sku' => 'SKU-001', 'slug' => 'product-001', 'price' => 100, 'sale_price' => 50, 'excerpt' => 'خلاصه قبلی', 'content' => '<p>قبلی</p>']);
        $existing->specifications()->createMany([['key' => 'brand', 'value' => 'قدیمی'], ['key' => 'thickness', 'value' => '0.8']]);
        $existing->documents()->create(['title' => 'سند', 'external_url' => 'https://example.test/file.pdf']);
        $related = Product::factory()->create();
        $existing->relatedProducts()->attach($related);
        $image = $this->actor->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('media_library', 'public');
        ProductResource::syncFeaturedImage($existing, $image->id);
        ProductResource::syncMediaLibraryCollection($existing, 'gallery', [$image->id]);
        $batch = $this->stage([$this->product(['price' => 200, 'excerpt' => '', 'content' => '', 'sale_price' => ''])], [], [['sku' => 'SKU-001', 'key' => 'thickness', 'value' => '0.9']]);
        $this->assertSame('skip', $batch->rows()->sole()->action);
        $batch = $this->service()->configure($batch, $this->actor, 'create_update', []);
        $this->assertSame('review', $batch->rows()->sole()->action);
        $batch = $this->service()->approveUpdates($batch, $this->actor, [$batch->rows()->sole()->id]);
        $result = $this->service()->execute($batch, $this->actor, true);
        $this->assertSame(1, $result->summary['results']['updated']);
        $existing->refresh();
        $this->assertSame('200.00', $existing->price);
        $this->assertSame('50.00', $existing->sale_price);
        $this->assertSame('خلاصه قبلی', $existing->excerpt);
        $this->assertSame('<p>قبلی</p>', $existing->content);
        $this->assertSame('قدیمی', $existing->specifications()->where('key', 'brand')->value('value'));
        $this->assertSame('0.9', $existing->specifications()->where('key', 'thickness')->value('value'));
        $this->assertSame(2, $existing->specifications()->count());
        $this->assertSame(1, $existing->documents()->count());
        $this->assertSame([$related->id], $existing->relatedProducts()->pluck('products.id')->all());
        $this->assertSame($image->id, $existing->featuredImage()->id);
        $this->assertSame([$image->id], $existing->galleryImages()->pluck('id')->all());
    }

    public function test_changed_data_after_preview_is_not_overwritten(): void
    {
        $existing = Product::factory()->create(['sku' => 'SKU-001', 'slug' => 'product-001']);
        $batch = $this->stage([$this->product()]);
        $batch = $this->service()->configure($batch, $this->actor, 'create_update', []);
        $batch = $this->service()->approveUpdates($batch, $this->actor, [$batch->rows()->sole()->id]);
        $existing->update(['title' => 'ویرایش همزمان']);
        $result = $this->service()->execute($batch, $this->actor, true);
        $this->assertSame(1, $result->summary['results']['error']);
        $this->assertSame('ویرایش همزمان', $existing->fresh()->title);
    }

    public function test_conflicting_specs_are_errors_and_identical_source_specs_are_deduplicated(): void
    {
        $spec = ['sku' => 'SKU-001', 'key' => 'thickness', 'value' => '0.9'];
        $good = $this->stage([$this->product()], [], [$spec, $spec]);
        $this->assertNotEmpty($good->rows()->sole()->warnings);
        $this->service()->execute($good, $this->actor, true);
        $this->assertDatabaseCount('product_specifications', 1);
        $bad = $this->stage([$this->product(['sku' => 'BAD', 'slug' => 'bad'])], [], [array_replace($spec, ['sku' => 'BAD']), array_replace($spec, ['sku' => 'BAD', 'value' => '1.2'])]);
        $this->assertSame('error', $bad->rows()->sole()->action);
        $this->service()->execute($bad, $this->actor, true);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_a_row_failure_rolls_back_its_product_category_and_specs_but_other_rows_continue(): void
    {
        ProductSpecification::creating(function ($spec): void {
            if ($spec->key === 'fail') {
                throw new RuntimeException('Test row failure');
            }
        });
        $batch = $this->stage([
            $this->product(['sku' => 'GOOD', 'slug' => 'good', 'title' => 'کالای سالم']),
            $this->product(['sku' => 'BAD', 'slug' => 'bad', 'title' => 'کالای ناموفق', 'category_slug' => 'rollback']),
        ], [['slug' => 'rollback', 'name' => 'دسته بازگشتی']], [['sku' => 'BAD', 'key' => 'fail', 'value' => 'x']]);
        $batch = $this->approveCategories($batch);
        $result = $this->service()->execute($batch, $this->actor, true);
        $this->assertSame(1, $result->summary['results']['created']);
        $this->assertSame(1, $result->summary['results']['error']);
        $this->assertDatabaseMissing('products', ['sku' => 'BAD']);
        $this->assertDatabaseMissing('product_categories', ['slug' => 'rollback']);
        $this->assertDatabaseCount('product_specifications', 0);
    }

    public function test_invalid_values_are_reported_and_no_missing_optional_value_activates_sales(): void
    {
        foreach ([['price' => '-1'], ['price' => '1.234'], ['price' => '10000000000'], ['sale_price' => '1'], ['status' => 'bad'], ['has_stock' => 'yes'], ['sort_order' => '-1']] as $index => $invalid) {
            $batch = $this->stage([$this->product($invalid + ['sku' => 'INVALID-'.$index, 'slug' => 'invalid-'.$index])]);
            $this->assertSame('error', $batch->rows()->sole()->action, json_encode($invalid));
        }
        $safe = $this->stage([['sku' => 'MIN', 'title' => 'حداقل', 'slug' => 'minimum', 'price' => '۰']]);
        $this->service()->execute($safe, $this->actor, true);
        $product = Product::query()->sole();
        $this->assertFalse($product->isPurchasable());
        $this->assertFalse($product->robots_index);
        $this->assertSame('draft', $product->status);
    }

    public function test_broken_workbook_missing_headers_formulas_and_traversal_are_rejected(): void
    {
        $broken = $this->temporary();
        file_put_contents($broken, 'not xlsx');
        foreach ([$broken] as $path) {
            $this->assertReaderFails($path);
        }
        $missing = $this->workbook([$this->product()]);
        $book = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($missing->getRealPath());
        $book->getSheetByName('Import_Products')->setCellValue('A1', 'missing_sku');
        (new Xlsx($book))->save($missing->getRealPath());
        $book->disconnectWorksheets();
        $this->assertReaderFails($missing->getRealPath());
        $formula = $this->workbook([$this->product()]);
        $book = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($formula->getRealPath());
        $book->getSheetByName('Import_Products')->setCellValue('B2', '=1+1');
        $writer = new Xlsx($book);
        $writer->setPreCalculateFormulas(false);
        $writer->save($formula->getRealPath());
        $book->disconnectWorksheets();
        $batch = $this->service()->stage($formula, $this->actor);
        $this->assertSame('error', $batch->rows()->sole()->action);
        $unsafe = $this->workbook([$this->product()]);
        $zip = new ZipArchive;
        $zip->open($unsafe->getRealPath());
        $zip->addFromString('../outside.php', '<?php echo 1;');
        $zip->close();
        $this->assertReaderFails($unsafe->getRealPath());
        $this->assertDatabaseCount('products', 0);
    }

    public function test_header_order_is_free_and_template_roundtrips_through_the_reader(): void
    {
        $book = app(ProductImportTemplate::class)->workbook();
        $sheet = $book->getSheetByName('Import_Products');
        foreach (ProductImportMapper::PRODUCT_HEADERS as $index => $field) {
            $sheet->setCellValueExplicit([$index + 1, 2], (string) ($this->product()[$field] ?? ''), DataType::TYPE_STRING);
        }
        $path = $this->temporary();
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $this->assertCount(1, app(ProductWorkbookReader::class)->read($path)['Import_Products']);
        $file = $this->workbook([array_reverse($this->product(), true)], [], [], [], ['slug', 'price', 'title', 'sku']);
        $batch = $this->service()->stage($file, $this->actor);
        $this->assertSame('new', $batch->rows()->sole()->action);
    }

    public function test_import_page_actions_authorization_module_guard_and_report_download(): void
    {
        $this->get(ProductResource::getUrl('import'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('درون‌ریزی محصولات');
        $batch = $this->stage([$this->product()]);
        Livewire::withQueryParams(['batch' => $batch->id])->test(ImportProducts::class)->assertOk()
            ->assertCanSeeTableRecords($batch->rows)->assertActionVisible('execute')->assertActionVisible('report');
        Livewire::test(ImportProducts::class)->callAction('template')->assertFileDownloaded('king-products-template.xlsx');
        Livewire::withQueryParams(['batch' => $batch->id])->test(ImportProducts::class)
            ->callAction('report')->assertFileDownloaded('product-import-'.$batch->id.'.csv');
        $other = User::factory()->admin()->create();
        $this->flushSession();
        $this->actingAs($other)->get(ProductResource::getUrl('import', ['batch' => $batch->id]))->assertForbidden();
        $this->flushSession();
        $this->actingAs(User::factory()->client()->create())->get(ProductResource::getUrl('import'))->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->actor);
        Gate::before(fn ($user, $ability) => $ability === 'create' ? false : null);
        $this->get(ProductResource::getUrl('import'))->assertForbidden();
    }

    public function test_module_disabled_blocks_even_direct_import_execution(): void
    {
        $batch = $this->stage([$this->product()]);
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        $this->get(ProductResource::getUrl('import'))->assertForbidden();
        $this->expectException(HttpException::class);
        $this->service()->execute($batch, $this->actor, true);
    }

    public function test_imported_html_is_sanitized_and_source_warnings_are_visible(): void
    {
        $batch = $this->stage([$this->product(['content' => '<p>توضیح سالم</p><script>alert(1)</script><img src="x" onerror="alert(2)">'])]);
        $this->assertNotEmpty($batch->rows()->sole()->warnings);
        $this->service()->execute($batch, $this->actor, true);
        $html = Product::query()->sole()->content;
        $this->assertStringContainsString('<p>توضیح سالم</p>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_interrupted_run_resumes_only_pending_rows_and_rejects_decision_changes_after_start(): void
    {
        $batch = $this->stage([$this->product(), $this->product(['sku' => 'SECOND', 'slug' => 'second', 'title' => 'کالای بعدی'])]);
        $batch->rows()->first()->update(['result' => 'rejected']);
        $batch->update(['status' => 'processing', 'updated_at' => now()->subMinutes(11)]);
        try {
            $this->service()->configure($batch, $this->actor, 'create_update', []);
            $this->fail('Decisions modified after execution started.');
        } catch (ValidationException) {
            $this->assertSame('create_only', $batch->fresh()->mode);
        }
        $result = $this->service()->execute($batch, $this->actor, true);
        $this->assertSame(1, $result->summary['results']['rejected']);
        $this->assertSame(1, $result->summary['results']['created']);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame('SECOND', Product::query()->sole()->sku);
    }

    public function test_filament_upload_preview_category_decision_and_final_confirmation_flow(): void
    {
        $file = $this->workbook([$this->product(['category_slug' => 'new-category'])], [['slug' => 'new-category', 'name' => 'دسته جدید']]);
        $file = UploadedFile::fake()->createWithContent('products.xlsx', file_get_contents($file->getRealPath()));
        Livewire::test(ImportProducts::class)->callAction('upload', data: ['file' => $file])
            ->assertHasNoActionErrors();
        $batch = ProductImport::query()->sole();
        $this->assertDatabaseCount('products', 0);
        Livewire::withQueryParams(['batch' => $batch->id])->test(ImportProducts::class)
            ->callAction('configure', data: ['mode' => 'create_only', 'categories' => [['source_slug' => 'new-category', 'target' => 'create']]])
            ->assertHasNoActionErrors()
            ->callAction('execute', data: ['confirm' => true])->assertHasNoActionErrors();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_categories', 1);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_http_batches_require_initial_confirmation_and_resume_without_repeating_completed_rows(): void
    {
        $batch = $this->stage([$this->product(), $this->product(['sku' => 'TWO', 'slug' => 'two', 'title' => 'کالای دوم'])]);
        try {
            $this->service()->continueExecution($batch, $this->actor);
            $this->fail('An unconfirmed preview was executed by polling.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
        }
        $batch = $this->service()->execute($batch, $this->actor, true, 1);
        $this->assertSame('processing', $batch->status);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(1, $batch->rows()->whereNotNull('result')->count());
        Livewire::withQueryParams(['batch' => $batch->id])->test(ImportProducts::class)
            ->assertSee('پیشرفت درون‌ریزی محصولات', false)->call('continueImport')->assertSee('نتیجه نهایی');
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertDatabaseCount('products', 2);
        $this->service()->continueExecution($batch, $this->actor);
        $this->assertDatabaseCount('products', 2);
    }

    private function service(): ProductImportService
    {
        return app(ProductImportService::class);
    }

    public function test_import_price_changes_invalidate_verification_and_repeat_import_has_no_duplicate_history(): void
    {
        $batch = $this->stage([$this->product(['price' => '100.25'])]);
        $this->service()->execute($batch, $this->actor, true);
        $product = Product::query()->sole();
        $freshness = app(ProductPriceFreshness::class);
        $this->assertSame('unverified', $freshness->status($product));
        $this->assertSame('import', $product->priceHistories()->sole()->source);
        $this->assertSame($this->actor->id, $product->priceHistories()->sole()->user_id);
        $product = $freshness->verify($product, $this->actor);
        $originalChanged = $product->price_changed_at->toDateTimeString();
        $this->travel(1)->day();
        $same = $this->stage([$this->product(['price' => '100.25'])]);
        $this->service()->execute($same, $this->actor, true);
        $this->assertSame($originalChanged, $product->fresh()->price_changed_at->toDateTimeString());
        $this->assertNotNull($product->fresh()->price_verified_at);
        $this->assertDatabaseCount('product_price_histories', 2);

        $changed = $this->stage([$this->product(['price' => '200.50'])]);
        $changed = $this->service()->configure($changed, $this->actor, 'create_update', []);
        $changed = $this->service()->decide($changed, $changed->rows()->sole(), $this->actor, ['update' => true]);
        $this->service()->execute($changed, $this->actor, true);
        $this->assertSame('200.50', $product->fresh()->price);
        $this->assertNull($product->fresh()->price_verified_at);
        $this->assertDatabaseCount('product_price_histories', 3);
        $repeat = $this->stage([$this->product(['price' => '200.50'])]);
        $this->service()->execute($repeat, $this->actor, true);
        $this->assertDatabaseCount('product_price_histories', 3);
    }

    private function product(array $overrides = []): array
    {
        return array_replace([
            'sku' => 'SKU-001', 'title' => 'محصول آزمایشی', 'slug' => 'product-001', 'price' => '0',
            'status' => 'draft', 'stock_status' => 'out_of_stock', 'has_stock' => '0', 'robots_index' => '0',
        ], $overrides);
    }

    private function stage(array $products, array $categories = [], array $specs = []): ProductImport
    {
        return $this->service()->stage($this->workbook($products, $categories, $specs), $this->actor);
    }

    private function approveCategories(ProductImport $batch): ProductImport
    {
        return $this->service()->configure($batch, $this->actor, $batch->mode, array_fill_keys(array_keys($batch->categories), 'create'));
    }

    private function workbook(array $products, array $categories = [], array $specs = [], array $extras = [], ?array $productHeaders = null): UploadedFile
    {
        $book = new Spreadsheet;
        $sheets = [
            'Import_Products' => [$productHeaders ?? ProductImportMapper::PRODUCT_HEADERS, $products],
            'Categories' => [ProductImportMapper::CATEGORY_HEADERS, $categories],
            'Specifications_Proposed' => [ProductImportMapper::SPEC_HEADERS, $specs],
        ];
        $first = true;
        foreach ($sheets as $name => [$headers, $rows]) {
            $sheet = $first ? $book->getActiveSheet() : $book->createSheet();
            $first = false;
            $sheet->setTitle($name);
            $sheet->fromArray($headers, null, 'A1');
            foreach ($rows as $index => $row) {
                foreach ($headers as $column => $header) {
                    $sheet->setCellValueExplicit([$column + 1, $index + 2], (string) ($row[$header] ?? ''), DataType::TYPE_STRING);
                }
            }
        }
        foreach ($extras as $name => $rows) {
            $book->createSheet()->setTitle($name)->fromArray($rows);
        }
        $path = $this->temporary();
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'products.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function temporary(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'king-product-import-');
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function expectNoConfirmation(ProductImport $batch): void
    {
        try {
            $this->service()->execute($batch, $this->actor);
            $this->fail('Unconfirmed import executed.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
        }
    }

    private function assertReaderFails(string $path): void
    {
        try {
            app(ProductWorkbookReader::class)->read($path);
            $this->fail('Invalid workbook accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors()['file']);
        }
    }

    private function catalogue(): array
    {
        $products = $categories = $specs = [];
        for ($i = 0; $i < 10; $i++) {
            $categories[] = ['slug' => 'category-'.$i, 'name' => 'دسته '.$i];
        }
        for ($i = 1; $i <= 230; $i++) {
            $sku = 'P-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $products[] = $this->product(['sku' => $sku, 'title' => 'پروفیل نمونه شماره '.$i, 'slug' => 'پروفیل-'.$i, 'category_slug' => 'category-'.($i % 10)]);
            for ($j = 0; $j < ($i <= 74 ? 3 : 2); $j++) {
                $specs[] = ['sku' => $sku, 'key' => 'dimension-'.$j, 'value' => (string) ($i + $j), 'unit' => 'mm'];
            }
        }
        for ($i = 0; $i < 93; $i++) {
            $specs[] = ['sku' => 'OUTSIDE-'.$i, 'key' => 'ignored', 'value' => 'x'];
        }

        return [$products, $categories, $specs];
    }
}
