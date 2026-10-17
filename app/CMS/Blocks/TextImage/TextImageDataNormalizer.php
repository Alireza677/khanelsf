<?php

namespace App\CMS\Blocks\TextImage;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Blocks\Contracts\BlockNormalizer;

final class TextImageDataNormalizer implements BlockNormalizer
{
    public function normalize(array $data): array
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $button = is_array($content['button'] ?? null) ? $content['button'] : [];
        $action = ActionDestination::fromArray(is_array($button['action'] ?? null) ? $button['action'] : []);
        $mediaId = filter_var($content['media_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'block_id' => $this->text($data['block_id'] ?? null),
            'schema_version' => 1,
            'template' => 'default',
            'content' => [
                'title' => $this->text($content['title'] ?? null),
                'eyebrow' => $this->text($content['eyebrow'] ?? null),
                'description' => $this->text($content['description'] ?? null),
                'media_id' => $mediaId === false ? null : $mediaId,
                'button' => [
                    'label' => $this->text($button['label'] ?? null),
                    'action' => $action->type === null ? null : $action->toArray(),
                ],
            ],
            'settings' => [
                'heading_tag' => $this->choice($settings, 'heading_tag', ['h2', 'h3', 'h4'], 'h2'),
                'image_position' => $this->choice($settings, 'image_position', ['left', 'right'], 'left'),
                'column_ratio' => $this->choice($settings, 'column_ratio', ['equal', 'text_wide', 'image_wide'], 'equal'),
                'vertical_alignment' => $this->choice($settings, 'vertical_alignment', ['top', 'center', 'bottom'], 'center'),
                'image_ratio' => $this->choice($settings, 'image_ratio', ['natural', '4:3', '16:9', '1:1'], 'natural'),
                'section_background' => $this->choice($settings, 'section_background', ['default', 'muted', 'dark'], 'default'),
            ],
        ];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function choice(array $settings, string $key, array $options, string $default): string
    {
        return in_array($settings[$key] ?? null, $options, true) ? $settings[$key] : $default;
    }
}
