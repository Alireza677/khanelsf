<?php

namespace App\Services;

use App\Enums\ClientProjectDeliveryMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ServiceAgreementData
{
    public const BILLING_BASES = ['package' => 'مبلغ بسته', 'usage' => 'مصرف', 'hybrid' => 'ترکیبی'];

    public const BILLING_INTERVALS = ['monthly' => 'ماهانه', 'one_time' => 'یک‌باره'];

    public const OVERRIDE_FIELDS = ['amount', 'currency_code', 'contract_terms', 'deliverables', 'billing_basis', 'billing_interval', 'scheduling_defaults'];

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

    public static function validate(array $data): array
    {
        self::decimal($data['amount'] ?? null, 'amount');
        foreach (is_array($data['deliverables'] ?? null) ? $data['deliverables'] : [] as $index => $line) {
            if (is_array($line)) {
                self::decimal($line['quantity'] ?? null, "deliverables.$index.quantity");
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

    public static function canonical(array $data): array
    {
        $data = self::validate($data);
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
