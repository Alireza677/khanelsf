<?php
namespace App\CMS\Blocks\BusinessNetwork;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\Enums\NetworkLocationType;
use App\Models\NetworkLocation;
use App\Services\ModuleService;
use App\Support\IranProvinces;
final class BusinessNetworkRuntime
{
    public function __construct(private readonly ModuleService $modules,private readonly BusinessNetworkDataNormalizer $normalizer,private readonly RuntimeActionResolver $actions,private readonly ActionPresentation $presentation){}
    public function prepare(array $data,array $context=[]): ?array
    {
        if(!$this->modules->businessNetworkEnabled()) return null;
        $block=$this->normalizer->normalize($data); $settings=$block['settings'];
        $query=NetworkLocation::query()->active()->ordered();
        if($settings['enabled_types']!==[]) $query->whereIn('type',$settings['enabled_types']);
        $locations=$query->get(); $grouped=$locations->groupBy('province_code');
        $provinces=[];
        foreach(IranProvinces::ALL as $code=>$name){$items=$grouped->get($code,collect())->map(fn(NetworkLocation $l)=>[
            'id'=>$l->id,'name'=>$l->name,'type'=>$l->type->value,'type_label'=>$l->type->label(),'province_code'=>$code,'province_name'=>$name,'city'=>$l->city,'contact_name'=>$l->contact_name,'position'=>$l->position,'mobile'=>$l->mobile,'phone'=>$l->phone,'email'=>$l->email,'address'=>$l->address,'description'=>$l->description,
        ])->values()->all();$provinces[]=['province_code'=>$code,'province_name'=>$name,'active_count'=>count($items),'locations'=>$items];}
        $codes=$locations->pluck('province_code')->unique();
        $selected=$settings['default_province']&&$codes->contains($settings['default_province'])?$settings['default_province']:array_key_first(array_filter(IranProvinces::ALL,fn($_,$code)=>$codes->contains($code),ARRAY_FILTER_USE_BOTH));
        $cta=null;if(is_array($settings['empty_state_action'])){$resolved=$this->actions->resolve(ActionDestination::fromArray($settings['empty_state_action']),ResolutionContext::fromArray($context));$cta=$this->presentation->present($resolved,$context);}
        return ['block'=>$block,'settings'=>$settings,'provinces'=>$provinces,'selected_province'=>$selected,'total'=>$locations->count(),'empty_action'=>$cta,'type_labels'=>NetworkLocationType::options()];
    }
}
