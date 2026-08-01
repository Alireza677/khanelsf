<?php

namespace App\CMS\Actions\Filament;

use Closure;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

final class ActionPicker extends Fieldset
{
    /** @var array<int, string>|null */
    private ?array $allowedActionTypes = null;

    private bool|Closure $actionIsRequired = false;

    private bool|Closure $newTabIsAllowed = true;

    public static function make(
        string|Htmlable|Closure|null $name = null,
    ): static {
        if (! is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('Action Picker requires a state path.');
        }

        $static = app(self::class, ['label' => 'مقصد اقدام']);
        $static->configure();
        $static->statePath($name);

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->columns(1)
            ->extraAttributes(['dir' => 'rtl'])
            ->schema(fn (): array => $this->pickerSchema())
            ->afterStateHydrated(function (ActionPicker $component, mixed $state): void {
                $component->state($this->service()->hydrate($state));
            })
            ->mutateDehydratedStateUsing(
                fn (mixed $state): ?array => $this->service()->dehydrate(
                    $state,
                    $this->isRequired(),
                    array_keys($this->getTypeOptions()),
                    $this->allowsNewTab(),
                ),
            );
    }

    /** @param array<int, string> $types */
    public function allowedTypes(array $types): static
    {
        $this->allowedActionTypes = array_values(array_unique($types));

        return $this;
    }

    public function required(bool|Closure $condition = true): static
    {
        $this->actionIsRequired = $condition;

        return $this;
    }

    public function allowNewTab(bool|Closure $condition = true): static
    {
        $this->newTabIsAllowed = $condition;

        return $this;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->actionIsRequired);
    }

    public function allowsNewTab(): bool
    {
        return (bool) $this->evaluate($this->newTabIsAllowed);
    }

    /** @return array<string, string> */
    public function getTypeOptions(): array
    {
        return $this->service()->typeOptions($this->allowedActionTypes);
    }

    /** @return array<int, Hidden|Select|TextInput|Toggle> */
    private function pickerSchema(): array
    {
        return [
            Hidden::make('schema_version')->default(1),
            Select::make('type')
                ->label('نوع مقصد')
                ->options(fn (): array => $this->getTypeOptions())
                ->placeholder('نوع مقصد را انتخاب کنید')
                ->native(false)
                ->live()
                ->required(fn (): bool => $this->isRequired())
                ->validationMessages([
                    'required' => 'مقصد دکمه را انتخاب کنید.',
                ])
                ->rules([
                    fn (Get $get): Closure => function (
                        string $attribute,
                        mixed $value,
                        Closure $fail,
                    ) use ($get): void {
                        $message = $this->service()->validationMessage(
                            $this->stateFromGet($get),
                            $this->isRequired(),
                            array_keys($this->getTypeOptions()),
                        );

                        if ($message !== null) {
                            $fail($message);
                        }
                    },
                ])
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    foreach ($this->service()->switchType($state) as $key => $value) {
                        if ($key !== 'type') {
                            $set($key, $value);
                        }
                    }
                }),
            Select::make('reference_id')
                ->label(fn (Get $get): string => $get('type') === 'form'
                    ? 'فرم'
                    : 'مقصد داخلی')
                ->placeholder('حداقل دو حرف برای جست‌وجو وارد کنید')
                ->searchable()
                ->native(false)
                ->searchDebounce(300)
                ->searchPrompt('حداقل دو حرف از عنوان یا شناسه را وارد کنید.')
                ->loadingMessage('در حال جست‌وجو...')
                ->noSearchResultsMessage('نتیجه‌ای پیدا نشد.')
                ->getSearchResultsUsing(
                    fn (string $search, Get $get): array => $this->service()
                        ->searchOptions((string) $get('type'), $search),
                )
                ->getOptionLabelUsing(
                    fn (mixed $value, Get $get): string => $this->service()
                        ->selectedOptionLabel((string) $get('type'), $value),
                )
                ->required(fn (Get $get): bool => in_array(
                    $get('type'),
                    ['page', 'project', 'product', 'service', 'form'],
                    true,
                ))
                ->validationMessages([
                    'required' => 'یک مقصد معتبر را انتخاب کنید.',
                ])
                ->visible(fn (Get $get): bool => in_array(
                    $get('type'),
                    ['page', 'project', 'product', 'service', 'form'],
                    true,
                )),
            TextInput::make('value')
                ->label(fn (Get $get): string => match ($get('type')) {
                    'custom_url' => 'نشانی لینک',
                    'anchor' => 'شناسه بخش',
                    'email' => 'نشانی ایمیل',
                    'phone' => 'شماره تلفن',
                    default => 'مقدار مقصد',
                })
                ->type(fn (Get $get): string => match ($get('type')) {
                    'email' => 'email',
                    'phone' => 'tel',
                    default => 'text',
                })
                ->helperText(fn (Get $get): ?string => match ($get('type')) {
                    'custom_url' => 'برای ایجاد موقت دکمه بدون مقصد، می‌توانید فقط # وارد کنید.',
                    'anchor' => 'شناسه بخشی از همین صفحه؛ مانند contact',
                    default => null,
                })
                ->required(fn (Get $get): bool => in_array(
                    $get('type'),
                    ['custom_url', 'anchor', 'email', 'phone'],
                    true,
                ))
                ->validationMessages([
                    'required' => 'مقدار مقصد را وارد کنید.',
                ])
                ->visible(fn (Get $get): bool => in_array(
                    $get('type'),
                    ['custom_url', 'anchor', 'email', 'phone'],
                    true,
                )),
            Select::make('display')
                ->label('نحوه نمایش فرم')
                ->options([
                    'modal' => 'بازکردن فرم در پنجره',
                    'page' => 'بازکردن صفحه فرم',
                ])
                ->default('modal')
                ->native(false)
                ->required(fn (Get $get): bool => $get('type') === 'form')
                ->validationMessages([
                    'required' => 'نحوه نمایش فرم را انتخاب کنید.',
                ])
                ->visible(fn (Get $get): bool => $get('type') === 'form'),
            Toggle::make('open_in_new_tab')
                ->label('بازکردن در تب جدید')
                ->helperText('لینک در تب جدید باز شود.')
                ->default(false)
                ->visible(fn (Get $get): bool => $this->allowsNewTab()
                    && in_array(
                        $get('type'),
                        ['custom_url', 'page', 'project', 'product', 'service'],
                        true,
                    )),
        ];
    }

    /** @return array<string, mixed> */
    private function stateFromGet(Get $get): array
    {
        return [
            'schema_version' => $get('schema_version'),
            'type' => $get('type'),
            'reference_id' => $get('reference_id'),
            'value' => $get('value'),
            'display' => $get('display'),
            'open_in_new_tab' => $get('open_in_new_tab'),
        ];
    }

    private function service(): ActionPickerService
    {
        return app(ActionPickerService::class);
    }
}
