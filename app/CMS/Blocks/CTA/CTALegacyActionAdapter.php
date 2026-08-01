<?php

namespace App\CMS\Blocks\CTA;

use App\CMS\Actions\Contracts\LegacyActionAdapter;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Validation\ActionDestinationValidator;

final class CTALegacyActionAdapter implements LegacyActionAdapter
{
    public function __construct(
        private readonly ActionDestinationNormalizer $normalizer,
        private readonly ActionDestinationValidator $validator,
    ) {}

    public function adapt(array $legacy): ActionDestination
    {
        $type = is_string($legacy['type'] ?? null)
            ? trim($legacy['type'])
            : null;

        if ($type !== null && ! in_array($type, ['url', 'form'], true)) {
            return $this->validated($legacy);
        }

        if ($type === 'form' || ($type === null && array_key_exists('form_id', $legacy))) {
            return $this->validated([
                'type' => 'form',
                'reference_id' => $legacy['reference_id'] ?? $legacy['form_id'] ?? null,
                'display' => $legacy['display'] ?? null,
                'open_in_new_tab' => false,
            ]);
        }

        if ($type === 'url' || ($type === null && array_key_exists('url', $legacy))) {
            return $this->validated([
                'type' => 'custom_url',
                'value' => $legacy['value'] ?? $legacy['url'] ?? null,
                'open_in_new_tab' => $legacy['open_in_new_tab'] ?? false,
            ]);
        }

        return new ActionDestination(null);
    }

    private function validated(array $data): ActionDestination
    {
        $destination = $this->normalizer->normalize($data);

        return $this->validator->validate($destination)->isValid()
            ? $destination
            : new ActionDestination(null);
    }
}
