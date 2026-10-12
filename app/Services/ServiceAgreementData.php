<?php

namespace App\Services;

use App\Enums\ClientProjectDeliveryMode;
use App\Support\ServiceDeliveryNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ServiceAgreementData
{
    public const SCHEMA_VERSION = 2;

    public const FEATURE_TYPES = ['boolean' => 'دارد / ندارد', 'text' => 'متن', 'number' => 'عدد'];

    public const FEATURE_NUMBER = '/^-?\d{1,14}(?:\.\d{1,4})?$/';

    public const BILLING_BASES = ['package' => 'مبلغ بسته', 'usage' => 'مصرف', 'hybrid' => 'ترکیبی'];

    public const BILLING_INTERVALS = ['monthly' => 'ماهانه', 'one_time' => 'یک‌باره'];

    public const OVERRIDE_FIELDS = ['amount', 'currency_code', 'contract_terms', 'features', 'deliverables', 'billing_basis', 'billing_interval', 'scheduling_defaults'];

    private const DECIMAL = '/^\d{1,14}(?:\.\d{1,4})?$/';

    public static function decimal(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ((! is_string($value) && ! is_int($value)) || preg_match(self::DECIMAL, (string) $value) !== 1) {
            throw ValidationException::withMessages([$field => 'مقدار اعشاری معتبر را به صورت رشته وارد کنید.']);
        }

        return bcadd((string) $value, '0', 4);
    }

    public static function quantity(mixed $value, mixed $unit, string $field = 'quantity'): ?string
    {
        if ($unit !== null && ! is_string($unit)) {
            throw ValidationException::withMessages([preg_replace('/quantity$/', 'unit', $field) => 'واحد باید متن معتبر باشد.']);
        }
        $value = self::decimal($value, $field);
        if ($value !== null) {
            if (bccomp($value, '0', 4) <= 0) {
                throw ValidationException::withMessages([$field => 'مقدار تعهد باید بیشتر از صفر باشد.']);
            }
            ServiceDeliveryNumber::assertPrecision($value, ServiceDeliveryNumber::quantityFormat($unit), $field);
        }

        return $value;
    }

    /** Only unchanged quantities from an immutable, trusted agreement may be carried forward. */
    public static function validate(array $data, array $preservedDeliverables = []): array
    {
        $data['features'] = self::features($data['features'] ?? []);
        self::decimal($data['amount'] ?? null, 'amount');
        $position = 0;
        foreach (is_array($data['deliverables'] ?? null) ? $data['deliverables'] : [] as $index => $line) {
            $old = $preservedDeliverables[$position++] ?? [];
            if (is_array($line)) {
                $quantity = self::decimal($line['quantity'] ?? null, "deliverables.$index.quantity");
                $preserved = (string) ($old['deliverable_type_id'] ?? '') === (string) ($line['deliverable_type_id'] ?? '')
                    && ($old['unit'] ?? null) === ($line['unit'] ?? null)
                    && $quantity !== null && $quantity === self::decimal($old['quantity'] ?? null, 'quantity');
                if (! $preserved) {
                    self::quantity($line['quantity'] ?? null, $line['unit'] ?? null, "deliverables.$index.quantity");
                }
            }
        }

        return Validator::make($data, [
            'delivery_mode' => ['required', Rule::in(array_keys(ClientProjectDeliveryMode::options()))],
            'schedule_mode' => ['required', Rule::in(['recurring', 'fixed_period'])],
            'billing_basis' => ['required', Rule::in(array_keys(self::BILLING_BASES))],
            'billing_interval' => ['required', Rule::in(array_keys(self::BILLING_INTERVALS))],
            'amount' => ['nullable', 'regex:'.self::DECIMAL],
            'currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
            'contract_terms' => ['nullable', 'string'],
            'features' => ['array'],
            'scheduling_defaults' => ['nullable', 'array:cycle_anchor_day,monthly_hour_limit_minutes,start_date,end_date'],
            'scheduling_defaults.cycle_anchor_day' => ['nullable', 'integer', 'between:1,31'],
            'scheduling_defaults.monthly_hour_limit_minutes' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'scheduling_defaults.start_date' => ['nullable', 'date_format:Y-m-d'],
            'scheduling_defaults.end_date' => ['nullable', 'date_format:Y-m-d', 'after:scheduling_defaults.start_date'],
            'deliverables' => ['present', 'array'],
            'deliverables.*.deliverable_type_id' => ['required', 'integer', 'exists:deliverable_types,id'],
            'deliverables.*.name' => ['required', 'string', 'max:255'],
            'deliverables.*.description' => ['nullable', 'string'],
            'deliverables.*.quantity' => ['required', 'regex:'.self::DECIMAL, function ($attribute, $value, $fail): void {
                if (is_numeric($value) && bccomp((string) $value, '0', 4) <= 0) {
                    $fail('مقدار تعهد باید بیشتر از صفر باشد.');
                }
            }],
            'deliverables.*.unit' => ['required', 'string', 'max:100'],
            'deliverables.*.approval_required' => ['required', 'boolean'],
            'deliverables.*.revision_allowance' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'deliverables.*.scheduling_rules' => ['nullable', 'array:due_day_offset'],
            'deliverables.*.scheduling_rules.due_day_offset' => ['nullable', 'integer', 'min:0', 'max:3660'],
        ])->validate();
    }

    /** Display-only terms: never quantity commitments or inputs to work generation. */
    public static function features(mixed $features): array
    {
        $data = ['features' => $features ?? []];
        $validated = Validator::make($data, [
            'features' => ['array', 'max:100'],
            'features.*' => ['array:title,type,value,unit,numeric_format'],
            'features.*.title' => ['required', 'string', 'max:255'],
            'features.*.type' => ['required', Rule::in(array_keys(self::FEATURE_TYPES))],
            'features.*.value' => ['required', function ($attribute, $value, $fail) use ($data): void {
                $type = data_get($data, substr($attribute, 0, -strlen('value')).'type');
                if ($type === 'boolean' && ! in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    $fail('مقدار ویژگی باید دارد یا ندارد باشد.');
                } elseif ($type === 'text' && (! is_string($value) || mb_strlen($value) > 2000)) {
                    $fail('مقدار متنی ویژگی باید متن حداکثر ۲۰۰۰ نویسه باشد.');
                } elseif ($type === 'number' && ((! is_string($value) && ! is_int($value)) || preg_match(self::FEATURE_NUMBER, (string) $value) !== 1)) {
                    $fail('مقدار عددی ویژگی باید عدد اعشاری معتبر با حداکثر ۴ رقم اعشار باشد.');
                } elseif ($type === 'number' && data_get($data, substr($attribute, 0, -strlen('value')).'numeric_format', 'decimal') === 'integer' && ! ServiceDeliveryNumber::isInteger($value)) {
                    $fail('برای ویژگی با نوع عدد صحیح، مقدار اعشاری مجاز نیست.');
                }
            }],
            'features.*.numeric_format' => ['sometimes', 'required', Rule::in(array_keys(ServiceDeliveryNumber::FORMATS)), 'prohibited_unless:features.*.type,number'],
            'features.*.unit' => ['nullable', 'string', 'max:100', 'prohibited_unless:features.*.type,number'],
        ])->validate();

        return array_values(array_map(function (array $feature): array {
            $value = $feature['value'];
            if ($feature['type'] === 'boolean') {
                $value = (bool) $value;
            } elseif ($feature['type'] === 'number') {
                // Exact decimal strings, including zero/negatives; no float conversion.
                $value = ServiceDeliveryNumber::format(bcadd((string) $value, '0', 4));
            }

            return ['title' => trim($feature['title']), 'type' => $feature['type'], 'value' => $value,
                'unit' => $feature['type'] === 'number' && filled($feature['unit'] ?? null) ? trim($feature['unit']) : null,
                ...($feature['type'] === 'number' && isset($feature['numeric_format']) ? ['numeric_format' => $feature['numeric_format']] : [])];
        }, $validated['features']));
    }

    public static function featuresForComparison(mixed $features): array
    {
        return array_map(fn ($feature) => $feature['type'] === 'number' ? [...$feature, 'numeric_format' => $feature['numeric_format'] ?? 'decimal'] : $feature, self::features($features));
    }

    public static function canonical(array $data, array $preservedDeliverables = []): array
    {
        $data = self::validate($data, $preservedDeliverables);
        $data['amount'] = isset($data['amount']) ? bcadd((string) $data['amount'], '0', 4) : null;
        $data['contract_terms'] = $data['contract_terms'] ?? null;
        $data['scheduling_defaults'] = array_filter($data['scheduling_defaults'] ?? [], fn ($value) => $value !== null && $value !== '');
        $data['deliverables'] = array_values(array_map(function (array $line): array {
            return [
                'deliverable_type_id' => (int) $line['deliverable_type_id'],
                'name' => $line['name'],
                'description' => $line['description'] ?? null,
                'quantity' => bcadd((string) $line['quantity'], '0', 4),
                'unit' => $line['unit'],
                'approval_required' => (bool) $line['approval_required'],
                'revision_allowance' => (int) $line['revision_allowance'],
                'scheduling_rules' => array_filter($line['scheduling_rules'] ?? [], fn ($value) => $value !== null && $value !== ''),
            ];
        }, $data['deliverables']));

        return $data;
    }
}
