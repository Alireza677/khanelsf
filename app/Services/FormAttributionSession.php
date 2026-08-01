<?php

namespace App\Services;

use App\Models\Form;
use Illuminate\Http\Request;

final class FormAttributionSession
{
    private const TTL_SECONDS = 1800;

    public function put(Request $request, Form $form, array $context): array
    {
        $context = $this->normalize($context);
        $context['stored_at'] = now()->timestamp;
        $request->session()->put($this->key($form), $context);

        return $context;
    }

    public function get(Request $request, Form $form): array
    {
        $context = $request->session()->get($this->key($form));

        if (! is_array($context)
            || ! is_numeric($context['stored_at'] ?? null)
            || now()->timestamp - (int) $context['stored_at'] > self::TTL_SECONDS) {
            $this->forget($request, $form);

            return [];
        }

        unset($context['stored_at']);

        return $context;
    }

    public function forget(Request $request, Form $form): void
    {
        $request->session()->forget($this->key($form));
    }

    public function normalize(array $context): array
    {
        return [
            'source' => 'website',
            'page_id' => is_numeric($context['page_id'] ?? null) ? (int) $context['page_id'] : null,
            'page_url' => $this->stringOrNull($context['page_url'] ?? null),
            'block_id' => $this->blockId($context['block_id'] ?? null),
        ];
    }

    private function key(Form $form): string
    {
        return "forms.attribution.{$form->getKey()}";
    }

    private function blockId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $value) === 1
            ? strtoupper($value)
            : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
