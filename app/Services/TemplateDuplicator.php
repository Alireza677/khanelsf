<?php

namespace App\Services;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockEditorSaveManager;
use App\CMS\Blocks\BlockIdentityManager;
use App\CMS\Blocks\BlockRegistry;
use App\Models\Template;
use App\Support\PersianDate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TemplateDuplicator
{
    public function __construct(
        private readonly BlockIdentityManager $identities,
        private readonly BlockEditorHydrator $hydrator,
        private readonly BlockEditorSaveManager $saveBoundary,
        private readonly BlockRegistry $registry,
    ) {}

    public function duplicate(Template $source): Template
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return $source->getConnection()->transaction(function () use ($source): Template {
                    // Titles have no unique index. A common row lock serializes naming
                    // across duplications, including sources with identical titles.
                    $source->newQuery()->orderBy('id')->lockForUpdate()->firstOrFail();
                    $original = $source->newQuery()->lockForUpdate()->findOrFail($source->getKey());

                    if (! array_key_exists($original->type, Template::TYPES)) {
                        throw ValidationException::withMessages([
                            'type' => 'نوع قالب اصلی پشتیبانی نمی‌شود؛ تکثیر انجام نشد.',
                        ]);
                    }

                    $blocks = $this->copyBlocks($original->blocks);
                    [$title, $slug] = $this->availableName($original);

                    // Whitelist the copied fields: identity, timestamps and publication
                    // or matching state must never be inherited from the original.
                    $copy = new Template([
                        'title' => $title,
                        'slug' => $slug,
                        'type' => $original->type,
                        'blocks' => $blocks,
                        'status' => 'draft',
                        'is_default' => false,
                        'priority' => 0,
                        'conditions' => [],
                    ]);
                    $copy->setConnection($source->getConnectionName());
                    $copy->save();

                    return $copy;
                }, 3);
            } catch (UniqueConstraintViolationException $exception) {
                // Retry the entire transaction, not a failed statement within it.
                // This also handles slug collisions with concurrent non-copy creates.
                if ($attempt === 4) {
                    throw ValidationException::withMessages([
                        'slug' => 'نامک یکتا ایجاد نشد؛ لطفاً تکثیر قالب را دوباره انجام دهید.',
                    ]);
                }
            }
        }

        throw new \LogicException('Template duplication attempts exhausted.');
    }

    private function availableName(Template $original): array
    {
        $titleBase = $this->titleBase($original->title);
        $slugBase = $this->slugBase($original->slug);

        for ($number = 1; ; $number++) {
            [$title, $slug] = $this->candidateName($titleBase, $slugBase, $number);

            if (! $original->newQuery()->where(function ($query) use ($title, $slug): void {
                $query->where('title', $title)->orWhere('slug', $slug);
            })->exists()) {
                return [$title, $slug];
            }
        }
    }

    private function titleBase(string $title): string
    {
        do {
            $previous = $title;
            $title = preg_replace('/\s*\(کپی\s+[0-9۰-۹٠-٩]+\)\s*$/u', '', $title);
        } while ($title !== $previous);

        return trim($title) ?: 'قالب';
    }

    private function slugBase(string $slug): string
    {
        $slug = Str::slug($slug);

        do {
            $previous = $slug;
            $slug = preg_replace('/-copy-[0-9]+$/', '', $slug);
        } while ($slug !== $previous);

        return $slug ?: 'template';
    }

    private function candidateName(string $titleBase, string $slugBase, int $number): array
    {
        $titleSuffix = ' (کپی '.PersianDate::digits($number).')';
        $slugSuffix = '-copy-'.$number;

        return [
            rtrim(mb_substr($titleBase, 0, 255 - mb_strlen($titleSuffix))).$titleSuffix,
            rtrim(mb_substr($slugBase, 0, 255 - mb_strlen($slugSuffix)), '-').$slugSuffix,
        ];
    }

    private function copyBlocks(mixed $blocks): array
    {
        if ($blocks === null) {
            return [];
        }

        if (! is_array($blocks)) {
            throw ValidationException::withMessages(['blocks' => 'ساختار بلوک‌های قالب اصلی معتبر نیست.']);
        }

        foreach ($blocks as $key => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || $block['type'] === ''
                || (array_key_exists('data', $block) && ! is_array($block['data']))) {
                throw ValidationException::withMessages(['blocks' => 'ساختار یکی از بلوک‌های قالب اصلی معتبر نیست.']);
            }

            $type = $block['type'];
            // The public renderer also accepts legacy flat block envelopes.
            // Move their payload into data before the identity manager runs.
            $data = $block['data'] ?? Arr::except($block, ['type']);
            $blocks[$key] = ['type' => $type, 'data' => $data] + $block;

            if ($this->registry->has($type)
                && (int) ($data['schema_version'] ?? 0) > $this->registry->find($type)->version()) {
                throw ValidationException::withMessages([
                    'blocks' => 'نسخه داده یکی از بلوک‌ها از نسخه نصب‌شده جدیدتر است؛ تکثیر انجام نشد.',
                ]);
            }

            // Keep valid legacy blocks even when they are no longer in the picker.
            $view = $this->registry->renderView($type, $data);

            if ((! $view || ! view()->exists($view)) && ! view()->exists("partials.blocks.{$type}")) {
                throw ValidationException::withMessages(['blocks' => 'نمای یکی از بلوک‌های قالب اصلی در دسترس نیست.']);
            }
        }

        $blocks = $this->identities->regenerateDocumentIds($blocks);
        $blocks = $this->hydrator->hydrateV2($blocks);

        // The copy contains stored data, not Hero editor inputs. Normalize at the
        // save boundary without editor-only alignment/media reconciliation, which
        // would overwrite valid stored settings and external media references.
        return $this->saveBoundary->prepare($blocks, false);
    }
}
