<?php

namespace App\CMS\Blocks\FeatureGrid;

use App\CMS\Actions\Contracts\LegacyActionAdapter;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Validation\ActionDestinationValidator;

final class FeatureGridLegacyActionAdapter implements LegacyActionAdapter
{
    public function __construct(
        private readonly ActionDestinationNormalizer $normalizer,
        private readonly ActionDestinationValidator $validator,
    ) {}

    public function adapt(array $legacy): ActionDestination
    {
        $input = is_array($legacy['action'] ?? null)
            ? $legacy['action']
            : $legacy;

        if (! array_key_exists('type', $input)) {
            $input = [
                'type' => 'custom_url',
                'value' => $legacy['button_url'] ?? $legacy['url'] ?? null,
            ];
        }

        $destination = $this->normalizer->normalize($input);

        return $this->validator->validate($destination)->isValid()
            ? $destination
            : new ActionDestination(null);
    }
}
