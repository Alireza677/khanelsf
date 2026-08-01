<?php

namespace App\CMS\Actions\Enums;

enum CoreActionType: string
{
    case CustomUrl = 'custom_url';
    case Page = 'page';
    case Project = 'project';
    case Product = 'product';
    case Service = 'service';
    case Form = 'form';
    case Anchor = 'anchor';
    case Email = 'email';
    case Phone = 'phone';

    public static function fromInput(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value, " \t");

        return $value === '' ? null : self::tryFrom($value);
    }

    public function usesReference(): bool
    {
        return in_array($this, [
            self::Page,
            self::Project,
            self::Product,
            self::Service,
            self::Form,
        ], true);
    }

    public function usesValue(): bool
    {
        return in_array($this, [
            self::CustomUrl,
            self::Anchor,
            self::Email,
            self::Phone,
        ], true);
    }

    public function allowsNewTab(): bool
    {
        return in_array($this, [
            self::CustomUrl,
            self::Page,
            self::Project,
            self::Product,
            self::Service,
        ], true);
    }
}
