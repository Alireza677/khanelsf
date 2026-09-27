<?php

namespace App\Services\Calculators;

use Illuminate\Validation\ValidationException;

/** Static presentation metadata keyed by the existing canonical recommendation identity. */
final class CalculatorResultContent
{
    public const FIELDS = ['result_title', 'result_summary', 'result_description', 'result_note'];

    public function normalize(mixed $content, array $results, string $path): array
    {
        if (! is_array($content)) {
            throw ValidationException::withMessages([$path => 'ساختار محتوای نتایج معتبر نیست.']);
        }

        $normalized = [];
        foreach ($content as $key => $texts) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/D', $key) !== 1 || ! is_array($texts)) {
                throw ValidationException::withMessages([$path => 'شناسه یا محتوای نتیجه معتبر نیست.']);
            }
            foreach (self::FIELDS as $field) {
                $text = $texts[$field] ?? null;
                if ($text !== null && ! is_string($text)) {
                    throw ValidationException::withMessages(["{$path}.{$key}.{$field}" => 'محتوای نتیجه باید متن یا خالی باشد.']);
                }
                if (array_key_exists($key, $results) && trim($text ?? '') !== '') {
                    $normalized[$key][$field] = trim($text);
                }
            }
        }

        return $normalized;
    }

    /** Resolve only the winner; absent content leaves legacy result snapshots unchanged. */
    public function resolve(array $schema, ?string $resultKey): array
    {
        if ($resultKey === null) {
            return [];
        }

        $texts = data_get($schema, "calculator.result_content.{$resultKey}", []);
        $resolved = [];
        foreach (self::FIELDS as $field) {
            $text = is_array($texts) ? ($texts[$field] ?? null) : null;
            if (is_string($text) && trim($text) !== '') {
                $resolved[$field] = trim($text);
            }
        }

        return $resolved;
    }
}
