<?php

namespace Tests\Feature;

use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTableColumnsTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULTS = [
        'featured_image' => false,
        'category.name' => false,
        'price' => true,
        'sale_price' => false,
        'status' => true,
        'robots_index' => true,
        'price_state' => true,
        'price_verified_at' => true,
        'price_changed_at' => false,
        'effective_validity' => false,
        'price_expiry' => false,
        'stock_status' => false,
        'is_featured' => false,
        'updated_at' => false,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function assertMenuMatchesRenderedColumns($page, array $expected): void
    {
        $table = $page->instance()->getTable();
        $checkboxes = $page->instance()->getTableColumnToggleForm()->getComponents();
        $this->assertSame(array_keys(self::DEFAULTS), array_map(fn ($field) => $field->getName(), $checkboxes));
        $this->assertSame(array_keys(self::DEFAULTS), array_values(array_filter(array_keys($table->getColumns()), fn ($name) => $name !== 'title')));
        $this->assertFalse($table->getColumn('title')->isToggleable());
        $page->assertCanRenderTableColumn('title');

        foreach ($checkboxes as $field) {
            $this->assertInstanceOf(Checkbox::class, $field);
            $name = $field->getName();
            $column = $table->getColumn($name);
            $this->assertTrue($column->isToggleable());
            $this->assertSame($column->getLabel(), $field->getLabel());
            $this->assertSame($expected[$name], (bool) $field->getState(), $name.' checkbox');
            $this->assertSame(! $expected[$name], $column->isToggledHidden(), $name.' visibility');
            if ($expected[$name]) {
                $page->assertCanRenderTableColumn($name);
            } else {
                $page->assertCanNotRenderTableColumn($name);
            }
        }
    }

    public function test_defaults_and_price_labels_match_the_standard_column_menu(): void
    {
        Product::factory()->published()->create(['is_featured' => true]);
        $page = Livewire::test(ListProducts::class);
        $this->assertMenuMatchesRenderedColumns($page, self::DEFAULTS);
        $this->assertSame('قیمت عادی', $page->instance()->getTable()->getColumn('price')->getLabel());
        $this->assertSame('قیمت با تخفیف', $page->instance()->getTable()->getColumn('sale_price')->getLabel());
    }

    public function test_all_columns_can_be_toggled_and_saved_without_mutating_products_or_history(): void
    {
        $product = Product::factory()->published()->create(['is_featured' => true]);
        $before = (array) DB::table('products')->where('id', $product->id)->first();
        $historyBefore = DB::table('product_price_histories')->get()->all();
        $page = Livewire::test(ListProducts::class);
        $inverted = array_map(fn ($visible) => ! $visible, self::DEFAULTS);
        $state = [];
        foreach ($inverted as $name => $visible) {
            data_set($state, $name, $visible);
        }
        $page->set('toggledTableColumns', $state);
        $this->assertMenuMatchesRenderedColumns($page, $inverted);
        $this->assertSame($state, session($page->instance()->getTableColumnToggleFormStateSessionKey()));
        $this->assertMenuMatchesRenderedColumns(Livewire::test(ListProducts::class), $inverted);

        foreach (self::DEFAULTS as $name => $visible) {
            data_set($state, $name, $visible);
        }
        $page->set('toggledTableColumns', $state);
        $this->assertMenuMatchesRenderedColumns($page, self::DEFAULTS);
        $this->assertSame($before, (array) DB::table('products')->where('id', $product->id)->first());
        $this->assertEquals($historyBefore, DB::table('product_price_histories')->get()->all());
    }

    public function test_saved_preferences_override_defaults_and_other_table_preferences_are_preserved(): void
    {
        $product = Product::factory()->published()->create(['title' => 'Alpha product', 'is_featured' => true, 'price' => 20]);
        $other = Product::factory()->published()->create(['title' => 'Zeta product', 'is_featured' => false, 'price' => 10]);
        $key = 'tables.'.md5(ListProducts::class).'_toggled_columns';
        $otherKey = 'tables.'.md5(ListPosts::class).'_toggled_columns';
        $saved = ['is_featured' => true, 'price' => true, 'category' => ['name' => false]];
        session()->put($key, $saved);
        session()->put($otherKey, ['robots_index' => false]);
        $page = Livewire::test(ListProducts::class);
        $expected = array_fill_keys(array_keys(self::DEFAULTS), false);
        $expected['is_featured'] = $expected['price'] = true;
        $this->assertMenuMatchesRenderedColumns($page, $expected);
        $page->set('toggledTableColumns.is_featured', false)->assertCanNotRenderTableColumn('is_featured');
        $this->assertFalse(session($key.'.is_featured'));
        $this->assertSame(['robots_index' => false], session($otherKey));
        $page->searchTable('Alpha')->sortTable('price', 'desc')->filterTable('is_featured', '1')
            ->assertCanSeeTableRecords([$product])->assertCanNotSeeTableRecords([$other])
            ->assertTableActionExists('edit')->assertTableBulkActionExists('delete');
    }
}
