<?php

namespace Tests\Unit\Actions;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Actions\Filament\ActionPickerService;
use App\CMS\Actions\Filament\Exceptions\InvalidActionPickerState;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActionPickerStateTest extends TestCase
{
    #[DataProvider('canonicalActions')]
    public function test_hydration_and_dehydration_round_trip_to_compact_canonical_state(
        array $input,
        array $expected,
    ): void {
        $service = app(ActionPickerService::class);
        $hydrated = $service->hydrate($input);

        $this->assertIsArray($hydrated);
        $this->assertSame($expected, $service->dehydrate(
            $hydrated,
            required: true,
            allowedTypes: array_keys($service->typeOptions()),
        ));
    }

    public static function canonicalActions(): array
    {
        return [
            'page' => [
                ['type' => 'page', 'reference_id' => '12'],
                [
                    'schema_version' => 1,
                    'type' => 'page',
                    'reference_id' => 12,
                    'open_in_new_tab' => false,
                ],
            ],
            'project new tab' => [
                ['type' => 'project', 'reference_id' => 2, 'open_in_new_tab' => true],
                [
                    'schema_version' => 1,
                    'type' => 'project',
                    'reference_id' => 2,
                    'open_in_new_tab' => true,
                ],
            ],
            'product' => [
                ['type' => 'product', 'reference_id' => 3],
                [
                    'schema_version' => 1,
                    'type' => 'product',
                    'reference_id' => 3,
                    'open_in_new_tab' => false,
                ],
            ],
            'service' => [
                ['type' => 'service', 'reference_id' => 4],
                [
                    'schema_version' => 1,
                    'type' => 'service',
                    'reference_id' => 4,
                    'open_in_new_tab' => false,
                ],
            ],
            'form' => [
                ['type' => 'form', 'reference_id' => 5, 'display' => 'modal'],
                [
                    'schema_version' => 1,
                    'type' => 'form',
                    'reference_id' => 5,
                    'display' => 'modal',
                    'open_in_new_tab' => false,
                ],
            ],
            'custom URL' => [
                ['type' => 'custom_url', 'value' => '/contact'],
                [
                    'schema_version' => 1,
                    'type' => 'custom_url',
                    'value' => '/contact',
                    'open_in_new_tab' => false,
                ],
            ],
            'anchor' => [
                ['type' => 'anchor', 'value' => '#contact'],
                [
                    'schema_version' => 1,
                    'type' => 'anchor',
                    'value' => 'contact',
                    'open_in_new_tab' => false,
                ],
            ],
            'email' => [
                ['type' => 'email', 'value' => 'mailto:info@example.com'],
                [
                    'schema_version' => 1,
                    'type' => 'email',
                    'value' => 'info@example.com',
                    'open_in_new_tab' => false,
                ],
            ],
            'phone' => [
                ['type' => 'phone', 'value' => 'tel:+98 912 123 4567'],
                [
                    'schema_version' => 1,
                    'type' => 'phone',
                    'value' => '+989121234567',
                    'open_in_new_tab' => false,
                ],
            ],
        ];
    }

    public function test_null_and_malformed_empty_state_hydrate_and_dehydrate_as_null(): void
    {
        $service = app(ActionPickerService::class);

        $this->assertNull($service->hydrate(null));
        $this->assertNull($service->hydrate(['type' => []]));
        $this->assertNull($service->dehydrate(
            null,
            required: false,
            allowedTypes: array_keys($service->typeOptions()),
        ));
    }

    public function test_unsupported_schema_is_safe_to_hydrate_but_rejected_on_save(): void
    {
        $service = app(ActionPickerService::class);
        $hydrated = $service->hydrate([
            'schema_version' => 99,
            'type' => 'page',
            'reference_id' => 12,
        ]);

        $this->assertSame(99, $hydrated['schema_version']);

        $this->expectException(InvalidActionPickerState::class);
        $this->expectExceptionMessage('نسخه داده مقصد پشتیبانی نمی‌شود.');

        $service->dehydrate(
            $hydrated,
            required: true,
            allowedTypes: array_keys($service->typeOptions()),
        );
    }

    public function test_type_switching_clears_incompatible_state_and_sets_form_defaults(): void
    {
        $service = app(ActionPickerService::class);

        $customUrl = $service->switchType('custom_url');
        $form = $service->switchType('form');
        $email = $service->switchType('email');
        $phone = $service->switchType('phone');

        foreach ([$customUrl, $form, $email, $phone] as $state) {
            $this->assertNull($state['reference_id']);
            $this->assertNull($state['value']);
            $this->assertFalse($state['open_in_new_tab']);
        }

        $this->assertNull($customUrl['display']);
        $this->assertSame('modal', $form['display']);
        $this->assertNull($email['display']);
        $this->assertNull($phone['display']);
    }

    #[DataProvider('invalidStates')]
    public function test_canonical_validation_returns_persian_messages(
        array $state,
        string $message,
    ): void {
        $service = app(ActionPickerService::class);

        $this->expectException(InvalidActionPickerState::class);
        $this->expectExceptionMessage($message);

        $service->dehydrate(
            $state,
            required: true,
            allowedTypes: array_keys($service->typeOptions()),
        );
    }

    public static function invalidStates(): array
    {
        return [
            'missing type' => [[], 'مقصد دکمه را انتخاب کنید.'],
            'missing reference' => [
                ['type' => 'page'],
                'یک مقصد معتبر را انتخاب کنید.',
            ],
            'unsafe URL' => [
                ['type' => 'custom_url', 'value' => 'javascript:alert(1)'],
                'لینک واردشده معتبر نیست.',
            ],
            'invalid anchor' => [
                ['type' => 'anchor', 'value' => 'bad anchor'],
                'شناسه بخش معتبر نیست.',
            ],
            'invalid email' => [
                ['type' => 'email', 'value' => 'not-an-email'],
                'ایمیل واردشده معتبر نیست.',
            ],
            'invalid phone' => [
                ['type' => 'phone', 'value' => 'phone'],
                'شماره تلفن واردشده معتبر نیست.',
            ],
            'invalid display' => [
                ['type' => 'form', 'reference_id' => 1, 'display' => 'popup'],
                'نحوه نمایش فرم را انتخاب کنید.',
            ],
        ];
    }

    public function test_component_api_filters_types_and_exposes_custom_path_and_label(): void
    {
        $picker = ActionPicker::make('button.action')
            ->label('مقصد دکمه')
            ->allowedTypes(['page', 'form', 'popup', 'download'])
            ->required()
            ->allowNewTab(false);

        $this->assertSame('button.action', $picker->getStatePath(false));
        $this->assertSame('مقصد دکمه', $picker->getLabel());
        $this->assertSame(['page' => 'برگه', 'form' => 'فرم'], $picker->getTypeOptions());
        $this->assertTrue($picker->isRequired());
        $this->assertFalse($picker->allowsNewTab());
        $this->assertSame(
            ['schema_version', 'type', 'reference_id', 'value', 'display', 'open_in_new_tab'],
            array_map(
                fn ($component): string => $component->getStatePath(false),
                $picker->getChildComponents(),
            ),
        );
    }
}
