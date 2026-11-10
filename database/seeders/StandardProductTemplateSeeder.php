<?php

namespace Database\Seeders;

use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StandardProductTemplateSeeder extends Seeder
{
    public const INSTALLATION_STATE_KEY = 'king_product_bootstrap';

    public const TEMPLATE_SLUG = 'product-standard-v1';

    public function run(): void
    {
        // Only DatabaseSeeder can establish intent, before any starter data exists.
        // Missing templates or a missing default never imply a new installation.
        DB::transaction(function (): void {
            $intent = Setting::query()->where('key', self::INSTALLATION_STATE_KEY)->lockForUpdate()->first();
            if ($intent?->value !== 'pending') {
                return;
            }
            if (Template::query()->where('type', 'product_single')->orWhere('slug', self::TEMPLATE_SLUG)->exists()) {
                $intent->update(['value' => 'preserved']);

                return;
            }
            $template = app(TemplateRecipeInstantiator::class)->makeDraft('product-standard-v1', [
                'title' => 'Product Standard', 'slug' => self::TEMPLATE_SLUG, 'is_default' => true,
            ]);
            $template->status = 'published';
            $errors = app(TemplatePublicationValidator::class)->validate($template->toArray());
            if ($errors !== []) {
                throw ValidationException::withMessages(['blocks' => $errors]);
            }
            $template->save();
            $intent->update(['value' => 'completed']);
        });
    }
}
