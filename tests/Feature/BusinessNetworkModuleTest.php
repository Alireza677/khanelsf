<?php
namespace Tests\Feature;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\BusinessNetwork\BusinessNetworkDataNormalizer;
use App\CMS\Blocks\BusinessNetwork\BusinessNetworkRuntime;
use App\Filament\Resources\NetworkLocationResource;
use App\Models\NetworkLocation;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Support\IranProvinces;
use DOMDocument;
use DOMXPath;
class BusinessNetworkModuleTest extends TestCase
{
 use RefreshDatabase;
 public function test_definition_stays_registered_but_resource_is_fail_closed_when_off(): void
 { $this->assertTrue(app(BlockRegistry::class)->has('business_network_map')); $this->assertFalse(NetworkLocationResource::shouldRegisterNavigation()); $this->assertFalse(NetworkLocationResource::canAccess()); }
 public function test_normalizer_is_idempotent_and_empty_types_means_all(): void
 { $n=app(BusinessNetworkDataNormalizer::class);$once=$n->normalize([]);$this->assertSame($once,$n->normalize($once));$this->assertSame([],$once['settings']['enabled_types']);$this->assertSame(1,$once['schema_version']); }
 public function test_runtime_queries_only_when_enabled_and_filters_active_types(): void
 { DB::enableQueryLog();$this->assertNull(app(BusinessNetworkRuntime::class)->prepare([]));$this->assertCount(0,array_filter(DB::getQueryLog(),fn($q)=>str_contains($q['query'],'network_locations')));app(SettingsService::class)->set('business_network_enabled',true,'business_network','boolean');NetworkLocation::create(['name'=>'فعال','type'=>'partner','province_code'=>'IR-15','city'=>'کرمان','status'=>'active','sort_order'=>2]);NetworkLocation::create(['name'=>'غیرفعال','type'=>'partner','province_code'=>'IR-15','city'=>'کرمان','status'=>'inactive']);NetworkLocation::create(['name'=>'شعبه','type'=>'branch','province_code'=>'IR-07','city'=>'تهران','status'=>'active']);$result=app(BusinessNetworkRuntime::class)->prepare(['settings'=>['enabled_types'=>['partner'],'default_province'=>'IR-07']]);$this->assertSame(1,$result['total']);$this->assertSame('IR-15',$result['selected_province']);$this->assertSame(1,collect($result['provinces'])->firstWhere('province_code','IR-15')['active_count']); }
 public function test_invalid_province_is_rejected(): void
 { $this->expectException(ValidationException::class);NetworkLocation::create(['name'=>'x','type'=>'agency','province_code'=>'bad','city'=>'x','status'=>'active']); }
 public function test_toggle_cycle_preserves_data(): void
 { app(SettingsService::class)->set('business_network_enabled',true);$location=NetworkLocation::create(['name'=>'x','type'=>'agency','province_code'=>'IR-04','city'=>'اصفهان','status'=>'active']);app(SettingsService::class)->set('business_network_enabled',false);app(SettingsService::class)->set('business_network_enabled',true);$this->assertTrue($location->fresh()->exists);$this->assertTrue(NetworkLocationResource::canAccess()); }
 public function test_svg_has_exactly_one_path_for_every_canonical_province(): void
 { $document=new DOMDocument();$this->assertTrue($document->load(resource_path('assets/maps/iran-provinces-interactive.svg')));$paths=(new DOMXPath($document))->query('//*[local-name()="path" and @data-province-code]');$codes=[];foreach($paths as $path){$code=$path->getAttribute('data-province-code');$codes[]=$code;$this->assertSame(IranProvinces::name($code),$path->getAttribute('data-province-name'));$this->assertSame('0',$path->getAttribute('tabindex'));$this->assertSame('button',$path->getAttribute('role'));}$this->assertCount(31,$codes);$this->assertCount(31,array_unique($codes));sort($codes);$canonical=array_keys(IranProvinces::ALL);sort($canonical);$this->assertSame($canonical,$codes); }
 public function test_presentation_exposes_multi_select_type_dialog_and_composed_filter_state(): void
 { app(SettingsService::class)->set('business_network_enabled',true,'business_network','boolean');NetworkLocation::create(['name'=>'مرکز تست','type'=>'agency','province_code'=>'IR-07','city'=>'تهران','status'=>'active']);$html=view('partials.blocks.business_network_map',['data'=>[],'context'=>[]])->render();$this->assertStringContainsString('data-type-dialog',$html);$this->assertStringContainsString('data-type-filter-apply',$html);$this->assertStringContainsString('data-clear-provinces',$html);$this->assertStringContainsString('const selectedProvinces = new Set',$html);$this->assertStringContainsString('const selectedTypes = new Set',$html);$this->assertStringContainsString('selectedProvinces.has(card.dataset.province)',$html);$this->assertStringContainsString('selectedTypes.has(card.dataset.type)',$html);$this->assertStringContainsString('height:410px;overflow-y:auto',$html);$this->assertStringContainsString('max-height:680px;min-width:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain',$html);$this->assertStringContainsString('@media(max-width:1024px){.bn-cards{max-height:600px}}',$html);$this->assertStringContainsString('grid-template-columns:1fr;max-height:520px',$html);$this->assertStringContainsString('class="business-network-card__title">مرکز تست</div>',$html);$this->assertStringContainsString('class="business-network-provinces__title">استان‌ها</div>',$html);$this->assertStringNotContainsString('<h3>مرکز تست</h3>',$html);$this->assertStringNotContainsString('<h3>استان‌ها</h3>',$html);$this->assertStringNotContainsString('.bn-panel-heading h3',$html);$this->assertStringContainsString('font-size:var(--theme-base-font-size,16px)',$html);$this->assertStringContainsString('@media(max-width:560px){.business-network{font-size:var(--theme-base-font-size-mobile,15px)}}',$html);$this->assertStringContainsString('.business-network button{font-size:inherit}',$html);$this->assertStringNotContainsString('data-network-results',$html); }
 public function test_type_filter_options_are_unified_rtl_labels_and_dialog_title_is_not_a_heading(): void
 { app(SettingsService::class)->set('business_network_enabled',true,'business_network','boolean');$html=view('partials.blocks.business_network_map',['data'=>[],'context'=>[]])->render();$this->assertStringContainsString('<label class="business-network-filter-option"><input type="checkbox"',$html);$this->assertStringContainsString('class="business-network-filter-dialog__title">فیلتر نوع مرکز</div>',$html);$this->assertStringNotContainsString('<h3 id=', $html);$this->assertStringContainsString('.business-network-filter-option{direction:rtl;display:flex;align-items:center;justify-content:flex-start;gap:.55rem',$html);$this->assertStringContainsString('.business-network-filter-option input{flex:0 0 auto;width:1rem;height:1rem;margin:0',$html);$this->assertStringNotContainsString('.bn-type-options label{',$html);$this->assertStringNotContainsString('.bn-filter-dialog header h3', $html); }
 public function test_presentation_renders_nothing_when_module_is_off(): void
 { $html=view('partials.blocks.business_network_map',['data'=>[],'context'=>[]])->render();$this->assertSame('',trim($html)); }
}
