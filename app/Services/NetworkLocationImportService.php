<?php

namespace App\Services;

use App\Data\NetworkLocationImportResult;
use App\Enums\NetworkLocationStatus;
use App\Enums\NetworkLocationType;
use App\Models\NetworkLocation;
use App\Support\IranProvinces;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class NetworkLocationImportService
{
    public const HEADERS = ['type','name','province','city','contact_name','position','mobile','phone','email','address','latitude','longitude','description','status','sort_order'];
    public const REQUIRED_HEADERS = ['type','name','province','city'];

    public function __construct(private readonly ModuleService $modules) {}

    public function import(string $absolutePath): NetworkLocationImportResult
    {
        if (! $this->modules->businessNetworkEnabled()) {
            return new NetworkLocationImportResult(0, 0, 0, 0, 0, [$this->error(null, null, 'ماژول شبکه کسب‌وکار غیرفعال است.')]);
        }
        [$rows, $parseErrors] = $this->parse($absolutePath);
        if ($parseErrors !== []) return new NetworkLocationImportResult(0, 0, 0, 0, 0, $parseErrors);

        $total = 0; $blank = 0; $errors = []; $valid = [];
        foreach ($rows as $rowNumber => $row) {
            if ($this->blankRow($row)) { $blank++; continue; }
            $total++; [$normalized, $rowErrors] = $this->normalizeAndValidate($row, $rowNumber);
            $errors = [...$errors, ...$rowErrors];
            if ($rowErrors === []) $valid[] = $normalized;
        }
        if ($errors !== []) return new NetworkLocationImportResult($total, count($valid), 0, 0, $blank, $errors);

        $existing = NetworkLocation::query()->get(['name','type','province_code','city'])
            ->mapWithKeys(fn (NetworkLocation $location) => [$this->identity([
                'name'=>$location->name,'type'=>$location->type->value,'province_code'=>$location->province_code,'city'=>$location->city,
            ]) => true])->all();
        $seen = []; $create = []; $duplicates = 0;
        foreach ($valid as $row) {
            $identity = $this->identity($row);
            if (isset($seen[$identity]) || isset($existing[$identity])) { $duplicates++; continue; }
            $seen[$identity] = true; $create[] = $row;
        }

        DB::transaction(function () use ($create): void {
            foreach ($create as $attributes) NetworkLocation::query()->create($attributes);
        });

        return new NetworkLocationImportResult($total, count($valid), count($create), $duplicates, $blank);
    }

    /** @return array{array<int,array<string,mixed>>,array<int,array{row:int|null,field:string|null,message:string}>} */
    private function parse(string $path): array
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $matrix = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().$sheet->getHighestRow(), null, false, true, false);
        } catch (Throwable) {
            return [[], [$this->error(null, null, 'فایل قابل خواندن نیست یا فرمت معتبری ندارد.')]];
        }
        if ($matrix === []) return [[], [$this->error(null, null, 'فایل خالی است.')]];
        $rawHeaders = array_shift($matrix); $headers = array_map(fn ($value) => Str::lower(ltrim($this->text($value) ?? '', "\xEF\xBB\xBF")), $rawHeaders);
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missing !== []) return [[], array_map(fn ($header) => $this->error(1, $header, "ستون «{$header}» در فایل پیدا نشد."), $missing)];
        $rows = [];
        foreach ($matrix as $offset => $values) {
            $row = [];
            foreach ($headers as $index => $header) if (in_array($header, self::HEADERS, true)) $row[$header] = $values[$index] ?? null;
            $rows[$offset + 2] = $row;
        }
        return [$rows, []];
    }

    private function normalizeAndValidate(array $row, int $number): array
    {
        $type = $this->enumValue($row['type'] ?? null, NetworkLocationType::cases());
        $statusInput = $this->text($row['status'] ?? null);
        $status = $statusInput === null ? NetworkLocationStatus::Active->value : $this->enumValue($statusInput, NetworkLocationStatus::cases());
        $provinceName = $this->text($row['province'] ?? null);
        $provinceCode = $provinceName === null ? null : array_search($provinceName, IranProvinces::ALL, true);
        $name = $this->text($row['name'] ?? null); $city = $this->text($row['city'] ?? null);
        $email = $this->text($row['email'] ?? null); $latitude = $this->number($row['latitude'] ?? null); $longitude = $this->number($row['longitude'] ?? null);
        $sortRaw = $this->latinDigits($this->text($row['sort_order'] ?? null));
        $errors = [];
        if ($type === null) $errors[]=$this->error($number,'type','مقدار type معتبر نیست.');
        if ($name === null) $errors[]=$this->error($number,'name','نام الزامی است.'); elseif(mb_strlen($name)>255) $errors[]=$this->error($number,'name','نام نباید بیشتر از ۲۵۵ نویسه باشد.');
        if ($provinceName === null) $errors[]=$this->error($number,'province','استان الزامی است.'); elseif($provinceCode===false) $errors[]=$this->error($number,'province',"استان «{$provinceName}» شناخته نشد.");
        if ($city === null) $errors[]=$this->error($number,'city','شهر الزامی است.'); elseif(mb_strlen($city)>255) $errors[]=$this->error($number,'city','شهر نباید بیشتر از ۲۵۵ نویسه باشد.');
        if ($status === null) $errors[]=$this->error($number,'status','مقدار status معتبر نیست.');
        if ($email !== null && filter_var($email,FILTER_VALIDATE_EMAIL)===false) $errors[]=$this->error($number,'email','ایمیل معتبر نیست.');
        if ($latitude === false || ($latitude !== null && ($latitude < -90 || $latitude > 90))) $errors[]=$this->error($number,'latitude','عرض جغرافیایی باید بین ۹۰- و ۹۰ باشد.');
        if ($longitude === false || ($longitude !== null && ($longitude < -180 || $longitude > 180))) $errors[]=$this->error($number,'longitude','طول جغرافیایی باید بین ۱۸۰- و ۱۸۰ باشد.');
        if ($sortRaw !== null && (!ctype_digit($sortRaw) || (int)$sortRaw < 0)) $errors[]=$this->error($number,'sort_order','ترتیب نمایش باید عدد صحیح صفر یا بزرگ‌تر باشد.');
        $normalized = ['type'=>$type,'name'=>$name,'province_code'=>$provinceCode===false?null:$provinceCode,'city'=>$city,
            'contact_name'=>$this->text($row['contact_name']??null),'position'=>$this->text($row['position']??null),'mobile'=>$this->latinDigits($this->text($row['mobile']??null)),'phone'=>$this->latinDigits($this->text($row['phone']??null)),'email'=>$email,'address'=>$this->text($row['address']??null),'latitude'=>$latitude===false?null:$latitude,'longitude'=>$longitude===false?null:$longitude,'description'=>$this->text($row['description']??null),'status'=>$status,'sort_order'=>$sortRaw===null?0:(int)$sortRaw];
        foreach (['contact_name','position','email'] as $field) if ($normalized[$field]!==null && mb_strlen($normalized[$field])>255) $errors[]=$this->error($number,$field,"مقدار {$field} بیش از حد طولانی است.");
        foreach (['mobile','phone'] as $field) if ($normalized[$field]!==null && mb_strlen($normalized[$field])>32) $errors[]=$this->error($number,$field,"مقدار {$field} بیش از ۳۲ نویسه است.");
        return [$normalized,$errors];
    }

    private function enumValue(mixed $input, array $cases): ?string { $value=$this->text($input); if($value===null)return null; foreach($cases as $case) if($value===$case->value||$value===$case->label()) return $case->value; return null; }
    private function identity(array $row): string { return implode('|',[$this->identityText($row['name']),$row['type'],$row['province_code'],$this->identityText($row['city'])]); }
    private function identityText(mixed $value): string { return Str::lower(preg_replace('/\s+/u',' ',trim((string)$value))); }
    private function blankRow(array $row): bool { foreach($row as $value) if($this->text($value)!==null)return false; return true; }
    private function text(mixed $value): ?string { if($value===null||!is_scalar($value))return null;$value=preg_replace('/\s+/u',' ',trim((string)$value));return $value===''?null:$value; }
    private function latinDigits(?string $value): ?string { return $value===null?null:strtr($value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.']); }
    private function number(mixed $value): float|int|false|null { $value=$this->latinDigits($this->text($value));if($value===null)return null;if(!is_numeric($value))return false;$number=(float)$value;return $number===(float)(int)$number?(int)$number:$number; }
    private function error(?int $row, ?string $field, string $message): array { return ['row'=>$row,'field'=>$field,'message'=>$row===null?$message:"ردیف {$row}: {$message}"]; }
}
