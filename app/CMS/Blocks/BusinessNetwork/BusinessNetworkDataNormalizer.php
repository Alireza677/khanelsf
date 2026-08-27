<?php
namespace App\CMS\Blocks\BusinessNetwork;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\Enums\NetworkLocationType;
use App\Support\IranProvinces;
final class BusinessNetworkDataNormalizer implements BlockNormalizer
{
    public const SCHEMA_VERSION=1;
    public function normalize(array $data): array
    {
        $content=is_array($data['content']??null)?$data['content']:$data;
        $settings=is_array($data['settings']??null)?$data['settings']:$data;
        $types=array_values(array_unique(array_filter(is_array($settings['enabled_types']??null)?$settings['enabled_types']:[],fn($v)=>is_string($v)&&NetworkLocationType::tryFrom($v))));
        $action=is_array($settings['empty_state_action']??null)?ActionDestination::fromArray($settings['empty_state_action'])->toArray():null;
        return ['block_id'=>$this->str($data['block_id']??null),'schema_version'=>self::SCHEMA_VERSION,'template'=>'default','content'=>[
            'title'=>$this->str($content['title']??null),'description'=>$this->str($content['description']??null),
        ],'settings'=>[
            'enabled_types'=>$types,'default_province'=>IranProvinces::valid($settings['default_province']??null)?$settings['default_province']:null,
            'show_map'=>$this->bool($settings,'show_map',true),'show_province_list'=>$this->bool($settings,'show_province_list',true),'show_result_count'=>$this->bool($settings,'show_result_count',true),'show_search'=>$this->bool($settings,'show_search',true),
            'show_type'=>$this->bool($settings,'show_type',true),'show_contact_name'=>$this->bool($settings,'show_contact_name',true),'show_position'=>$this->bool($settings,'show_position',true),'show_phone'=>$this->bool($settings,'show_phone',true),'show_mobile'=>$this->bool($settings,'show_mobile',true),'show_email'=>$this->bool($settings,'show_email',true),'show_address'=>$this->bool($settings,'show_address',true),
            'card_columns'=>max(1,min(4,(int)($settings['card_columns']??3))),'empty_state_title'=>$this->str($settings['empty_state_title']??null)??'موردی یافت نشد','empty_state_description'=>$this->str($settings['empty_state_description']??null)??'در حال حاضر مورد فعالی در این استان ثبت نشده است.','empty_state_action'=>$action,
        ]];
    }
    private function bool(array $a,string $k,bool $d): bool { return array_key_exists($k,$a)?filter_var($a[$k],FILTER_VALIDATE_BOOLEAN):$d; }
    private function str(mixed $v): ?string { return is_scalar($v)&&trim((string)$v)!==''?trim((string)$v):null; }
}
