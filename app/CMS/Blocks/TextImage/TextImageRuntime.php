<?php

namespace App\CMS\Blocks\TextImage;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\Models\Media;

final class TextImageRuntime
{
    public function __construct(
        private readonly TextImageDataNormalizer $normalizer,
        private readonly ActionResolver $actions,
        private readonly ActionPresentation $presentation,
    ) {}

    public function prepare(array $data, array $context = [], bool $preview = false): array
    {
        $block = $this->normalizer->normalize($data);
        $media = Media::reusableImage($block['content']['media_id']);
        $block['image'] = $media ? ['url' => $media->getUrl(), 'alt' => $media->altText()] : null;
        $button = $block['content']['button'];
        $block['presentation'] = $button['label'] && $button['action']
            ? $this->presentation->present(
                $this->actions->resolve(
                    ActionDestination::fromArray($button['action']),
                    new ResolutionContext($preview ? ResolutionMode::Preview : ResolutionMode::Production),
                ),
                [
                    'page_id' => $context['page_id'] ?? null,
                    'page_url' => $context['page_url'] ?? request()->getRequestUri(),
                    'block_id' => $block['block_id'],
                ],
            )
            : null;

        return $block;
    }
}
