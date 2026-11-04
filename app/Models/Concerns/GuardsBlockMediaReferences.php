<?php

namespace App\Models\Concerns;

use App\Media\BlockMediaReferences;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait GuardsBlockMediaReferences
{
    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $reader = app(BlockMediaReferences::class);
            $ids = $reader->referenceIds($this->blocks ?? []);
            $original = $this->getRawOriginal('blocks');
            $oldIds = $reader->referenceIds(is_array($original) ? $original : (json_decode($original ?? '[]', true) ?: []));
            // Serialize audited ID-based block writes with deletion of the canonical media row.
            $present = Media::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if (array_diff($ids, $oldIds, $present) !== []) {
                throw ValidationException::withMessages(['blocks' => 'رسانه انتخاب‌شده دیگر موجود نیست؛ کتابخانه را تازه‌سازی کنید.']);
            }

            return parent::save($options);
        }, 3);
    }
}
