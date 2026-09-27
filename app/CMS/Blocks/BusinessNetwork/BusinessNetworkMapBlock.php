<?php
namespace App\CMS\Blocks\BusinessNetwork;
use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\Enums\NetworkLocationType;
use App\Support\IranProvinces;
use Filament\Forms;
final class BusinessNetworkMapBlock extends AbstractBlock implements BlockNormalizer
{
    public function __construct(private readonly BusinessNetworkDataNormalizer $normalizer) {}
    public function templateTargets(): ?array
    {
        return app(\App\Services\ModuleService::class)->businessNetworkEnabled() ? null : [];
    }

    public function key(): string{return 'business_network_map';}
    public function label(): string{return 'نقشه شبکه کسب‌وکار';}
    public function icon(): ?string{return 'heroicon-o-map';}
    public function version(): int{return BusinessNetworkDataNormalizer::SCHEMA_VERSION;}
    public function templates(): array{return ['default'=>new BlockTemplate('default','پیش‌فرض','partials.blocks.business_network_map')];}
    public function defaultTemplate(): string{return 'default';}
    public function capabilities(): array{return ['dynamic_data','province_filter','search'];}
    public function normalize(array $data): array{return $this->normalizer->normalize($data);}
    public function filamentSchema(string $context): array{return [
        Forms\Components\Hidden::make('block_id'),Forms\Components\Hidden::make('schema_version')->default($this->version()),Forms\Components\Hidden::make('template')->default('default'),
        Forms\Components\TextInput::make('content.title')->label('عنوان')->maxLength(255),Forms\Components\Textarea::make('content.description')->label('توضیحات')->columnSpanFull(),
        Forms\Components\Select::make('settings.enabled_types')->label('انواع قابل نمایش')->options(NetworkLocationType::options())->multiple()->helperText('اگر خالی باشد همه انواع فعال نمایش داده می‌شوند.'),
        Forms\Components\Select::make('settings.default_province')->label('استان پیش‌فرض')->options(IranProvinces::options())->searchable(),
        Forms\Components\Section::make('اجزای نمایش')->schema([
            Forms\Components\Toggle::make('settings.show_map')->label('نمایش نقشه')->default(true),Forms\Components\Toggle::make('settings.show_province_list')->label('نمایش فهرست استان‌ها')->default(true),Forms\Components\Toggle::make('settings.show_result_count')->label('نمایش تعداد نتایج')->default(true),Forms\Components\Toggle::make('settings.show_search')->label('نمایش جستجو')->default(true),
        ])->columns(2),
        Forms\Components\Section::make('اطلاعات کارت')->schema(collect(['show_type'=>'نوع','show_contact_name'=>'نام مسئول','show_position'=>'سمت','show_phone'=>'تلفن','show_mobile'=>'موبایل','show_email'=>'ایمیل','show_address'=>'آدرس'])->map(fn($label,$key)=>Forms\Components\Toggle::make("settings.$key")->label($label)->default(true))->values()->all())->columns(3),
        Forms\Components\Select::make('settings.card_columns')->label('تعداد ستون کارت‌ها')->options([1=>'یک',2=>'دو',3=>'سه',4=>'چهار'])->default(3),
        Forms\Components\TextInput::make('settings.empty_state_title')->label('عنوان حالت خالی')->default('موردی یافت نشد'),Forms\Components\Textarea::make('settings.empty_state_description')->label('توضیح حالت خالی'),ActionPicker::make('settings.empty_state_action')->label('اقدام حالت خالی'),
    ];}
}
