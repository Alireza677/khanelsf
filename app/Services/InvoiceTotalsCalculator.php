<?php

namespace App\Services;

use InvalidArgumentException;

final class InvoiceTotalsCalculator
{
    public function calculate(iterable $itemAmounts, string|int $discount = '0', string|int $tax = '0'): array
    {
        $subtotal = '0.00';
        foreach ($itemAmounts as $amount) {
            $amount = $this->amount($amount, 'Item amount');
            $subtotal = bcadd($subtotal, $amount, 2);
        }
        $discount = $this->amount($discount, 'Discount');
        $tax = $this->amount($tax, 'Tax');
        if (bccomp($discount, $subtotal, 2) > 0) {
            throw new InvalidArgumentException('Discount cannot exceed subtotal.');
        }

        return ['subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'total_amount' => bcadd(bcsub($subtotal, $discount, 2), $tax, 2)];
    }

    private function amount(string|int|null $value, string $label): string
    {
        $value = trim((string) ($value ?? '0'));
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("{$label} must be a non-negative decimal.");
        }

        return bcadd($value, '0', 2);
    }
}
