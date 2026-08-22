<?php

namespace App\Services;

use App\Models\Form;
use Illuminate\Http\Request;

final class FormAttributionSession
{
    private const TTL_SECONDS = 1800;

    public function put(Request $request, Form $form, array $context, ?string $instanceToken = null): array
    {
        $context = $this->normalize($context);
        $context['stored_at'] = now()->timestamp;
        $request->session()->put($this->key($form, $instanceToken), $context);

        if ($instanceToken !== null) {
            $request->session()->put($this->activeKey($form), $instanceToken);
        }

        return $context;
    }

    public function get(Request $request, Form $form, ?string $instanceToken = null): array
    {
        $context = $request->session()->get($this->key($form, $instanceToken));

        if (! is_array($context)
            || ! is_numeric($context['stored_at'] ?? null)
            || now()->timestamp - (int) $context['stored_at'] > self::TTL_SECONDS) {
            $this->forget($request, $form, $instanceToken);

            return [];
        }

        unset($context['stored_at']);

        return $context;
    }

    public function forget(Request $request, Form $form, ?string $instanceToken = null): void
    {
        $request->session()->forget($this->key($form, $instanceToken));

        if ($instanceToken !== null && $request->session()->get($this->activeKey($form)) === $instanceToken) {
            $request->session()->forget($this->activeKey($form));
        }
    }

    public function activeInstance(Request $request, Form $form): ?string
    {
        return $this->instanceToken($request->session()->get($this->activeKey($form)));
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

    private function key(Form $form, ?string $instanceToken = null): string
    {
        $key = "forms.attribution.{$form->getKey()}";

        return $instanceToken === null ? $key : "{$key}.instances.{$instanceToken}";
    }

    private function activeKey(Form $form): string
    {
        return "forms.attribution.{$form->getKey()}.active_instance";
    }

    private function instanceToken(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $value) === 1
            ? $value
            : null;
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
