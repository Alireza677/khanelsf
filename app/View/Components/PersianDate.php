<?php

namespace App\View\Components;

use App\Support\PersianDate as Formatter;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class PersianDate extends Component
{
    public function __construct(
        public DateTimeInterface|string|null $value,
        public string $format = 'date',
        public ?string $datetime = null,
        public string $fallback = '—',
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.persian-date', [
            'formatted' => match ($this->format) {
                'datetime' => Formatter::dateTime($this->value),
                'weekday' => Formatter::dateWithWeekday($this->value),
                'month-year' => Formatter::monthYear($this->value),
                'human' => Formatter::human($this->value),
                'year' => Formatter::year($this->value),
                default => Formatter::date($this->value),
            },
        ]);
    }
}
