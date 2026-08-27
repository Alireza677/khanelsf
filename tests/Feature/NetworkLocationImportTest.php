<?php

namespace Tests\Feature;

use App\Filament\Resources\NetworkLocationResource\Pages\ListNetworkLocations;
use App\Models\NetworkLocation;
use App\Services\NetworkLocationImportService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionMethod;
use Tests\TestCase;

class NetworkLocationImportTest extends TestCase
{
    use RefreshDatabase;
    private array $temporaryFiles=[];

    protected function setUp(): void { parent::setUp(); app(SettingsService::class)->set('business_network_enabled',true,'business_network','boolean'); }
    protected function tearDown(): void { foreach($this->temporaryFiles as $file) if(is_file($file)) unlink($file); parent::tearDown(); }

    public function test_valid_xlsx_accepts_labels_defaults_and_persian_digits(): void
    {
        $result=$this->importXlsx([NetworkLocationImportService::HEADERS,[
            'همکار',' مجری کرمان ',' کرمان ','کرمان','علی','','۰۹۱۲۳۴۵۶۷۸۹','۰۳۴۱۲۳۴۵۶۷۸','a@example.com','نشانی','۳۰٫۵','۵۷.۱','توضیح','','۱۲',
        ]]);
        $this->assertTrue($result->successful());$this->assertSame(1,$result->created);
        $location=NetworkLocation::first();$this->assertSame('partner',$location->type->value);$this->assertSame('IR-15',$location->province_code);$this->assertSame('active',$location->status->value);$this->assertSame('09123456789',$location->mobile);$this->assertSame(12,$location->sort_order);
    }

    public function test_valid_csv_supports_reordered_and_extra_columns_and_blank_rows(): void
    {
        $result=$this->importCsv([['city','extra','province','name','type'],['تهران','ignored',' تهران ','دفتر','agency'],['','','','',''],['شیراز','ignored','فارس','شعبه','branch']]);
        $this->assertTrue($result->successful());$this->assertSame(2,$result->created);$this->assertSame(1,$result->ignoredBlankRows);
    }

    public function test_missing_header_and_invalid_rows_are_atomic_and_report_excel_rows(): void
    {
        $missing=$this->importCsv([['type','name','city'],['partner','الف','کرمان']]);
        $this->assertFalse($missing->successful());$this->assertStringContainsString('province',$missing->errors[0]['message']);
        $result=$this->importCsv([NetworkLocationImportService::HEADERS,
            ['partner','صحیح','کرمان','کرمان','','','','','','','','','','',''],
            ['bad','خراب','کرماان','', '', '', '', '', 'invalid', '', '91','181','','wrong','-1'],
        ]);
        $this->assertFalse($result->successful());$this->assertSame(0,NetworkLocation::count());$this->assertContains(3,array_column($result->errors,'row'));
        foreach(['type','province','city','status','email','latitude','longitude','sort_order'] as $field) $this->assertContains($field,array_column($result->errors,'field'));
    }

    public function test_canonical_and_persian_enum_values_are_accepted_and_invalid_values_rejected(): void
    {
        $ok=$this->importCsv([NetworkLocationImportService::HEADERS,['branch','شعبه یک','فارس','شیراز','','','','','','','','','','inactive',''],['نمایندگی','نمایندگی یک','تهران','تهران','','','','','','','','','','فعال','']]);
        $this->assertTrue($ok->successful());$this->assertSame(2,$ok->created);
        $bad=$this->importCsv([NetworkLocationImportService::HEADERS,['nope','x','تهران','تهران','','','','','','','','','','ناشناخته','']]);
        $this->assertFalse($bad->successful());$this->assertSame(2,NetworkLocation::count());
    }

    public function test_duplicates_inside_file_and_database_are_skipped_without_updates(): void
    {
        $existing=NetworkLocation::create(['name'=>'دفتر تهران','type'=>'agency','province_code'=>'IR-07','city'=>' تهران ','status'=>'inactive','sort_order'=>9]);
        $result=$this->importCsv([NetworkLocationImportService::HEADERS,
            ['agency',' دفتر تهران ','تهران','تهران','','','','','','','','','','active','0'],
            ['partner','همکار نو','کرمان','کرمان','','','','','','','','','','',''],
            ['همکار',' همکار نو ','کرمان',' کرمان ','','','','','','','','','','',''],
        ]);
        $this->assertTrue($result->successful());$this->assertSame(1,$result->created);$this->assertSame(2,$result->duplicates);$this->assertSame('inactive',$existing->fresh()->status->value);$this->assertSame(9,$existing->sort_order);
    }

    public function test_module_off_fails_closed_and_header_action_visibility_tracks_module(): void
    {
        $page=app(ListNetworkLocations::class);$method=new ReflectionMethod($page,'getHeaderActions');$method->setAccessible(true);$actions=$method->invoke($page);$import=collect($actions)->first(fn($action)=>$action->getName()==='import');$this->assertTrue($import->isVisible());
        app(SettingsService::class)->set('business_network_enabled',false,'business_network','boolean');
        $result=$this->importCsv([NetworkLocationImportService::HEADERS,['partner','x','تهران','تهران','','','','','','','','','','','']]);
        $this->assertFalse($result->successful());$this->assertSame(0,NetworkLocation::count());
        $this->assertFalse($import->isVisible());
    }

    private function importCsv(array $rows): \App\Data\NetworkLocationImportResult
    { $path=$this->temporary('.csv');$handle=fopen($path,'wb');fwrite($handle,"\xEF\xBB\xBF");foreach($rows as $row)fputcsv($handle,$row);fclose($handle);return app(NetworkLocationImportService::class)->import($path); }
    private function importXlsx(array $rows): \App\Data\NetworkLocationImportResult
    { $sheet=new Spreadsheet();$sheet->getActiveSheet()->fromArray($rows);$path=$this->temporary('.xlsx');(new Xlsx($sheet))->save($path);$sheet->disconnectWorksheets();return app(NetworkLocationImportService::class)->import($path); }
    private function temporary(string $extension): string { $path=tempnam(sys_get_temp_dir(),'network-import-').$extension;$this->temporaryFiles[]=$path;return $path; }
}
