<?php

namespace App\Support;

use App\Models\Form;
use App\Models\Media;

final class FormPagePresentation
{
    public static function settings(Form $form): array
    {
        $stored = data_get($form->settings, 'presentation', []);
        $stored = is_array($stored) ? $stored : [];
        $settings = [];

        foreach (['show_hero', 'show_stepper', 'show_step_counter', 'show_step_description'] as $key) {
            $settings[$key] = filter_var($stored[$key] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        foreach ([
            'title' => $form->name,
            'description' => '',
            'eyebrow' => '',
            'previous_button_label' => 'مرحله قبل',
            'next_button_label' => 'مرحله بعد',
        ] as $key => $fallback) {
            $value = is_string($stored[$key] ?? null) ? trim($stored[$key]) : '';
            $settings[$key] = $value !== '' ? $value : $fallback;
        }

        $settings['hero_media_id'] = is_numeric($stored['hero_media_id'] ?? null)
            ? (int) $stored['hero_media_id'] : null;

        return $settings;
    }

    public static function hero(array $settings): array
    {
        $media = $settings['show_hero'] ? Media::reusableImage($settings['hero_media_id']) : null;

        return [
            'title' => $settings['title'],
            'description' => $settings['description'],
            'eyebrow' => $settings['eyebrow'],
            'variant' => $media ? 'cover' : 'centered',
            'alignment' => 'center',
            'image_position' => 'end',
            'class' => 'form-page__hero',
            'image' => $media ? ['url' => $media->getUrl(), 'alt' => $media->altText()] : null,
        ];
    }
}
