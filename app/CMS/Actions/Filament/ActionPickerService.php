<?php

namespace App\CMS\Actions\Filament;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Filament\Exceptions\InvalidActionPickerState;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use App\CMS\InternalLinks\Contracts\ResolvesInternalLinkReference;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use App\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

final class ActionPickerService
{
    public const MAX_SEARCH_RESULTS = 20;

    /** @var array<string, string> */
    private const TYPE_LABELS = [
        'custom_url' => 'لینک دلخواه',
        'page' => 'برگه',
        'project' => 'پروژه',
        'product' => 'محصول',
        'service' => 'خدمت',
        'form' => 'فرم',
        'anchor' => 'بخش همین صفحه',
        'email' => 'ایمیل',
        'phone' => 'شماره تلفن',
    ];

    /** @var array<int, string> */
    private const ENTITY_TYPES = ['page', 'project', 'product', 'service'];

    public function __construct(
        private readonly ActionDestinationNormalizer $normalizer,
        private readonly ActionDestinationValidator $validator,
        private readonly InternalLinkSearchRegistry $internalLinks,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array<string, string> */
    public function typeOptions(?array $allowedTypes = null): array
    {
        $allowedTypes ??= array_keys(self::TYPE_LABELS);

        return collect(self::TYPE_LABELS)
            ->filter(function (string $label, string $type) use ($allowedTypes): bool {
                if (! in_array($type, $allowedTypes, true)) {
                    return false;
                }

                return ! in_array($type, self::ENTITY_TYPES, true)
                    || $this->internalLinks->has($type);
            })
            ->all();
    }

    /** @return array<string, int|string|bool|null>|null */
    public function hydrate(mixed $state): ?array
    {
        if (! is_array($state) || $this->isEmpty($state)) {
            return null;
        }

        return $this->normalizer->normalize($state)->toInternalArray();
    }

    /** @return array<string, int|string|bool|null> */
    public function switchType(?string $type): array
    {
        $type = array_key_exists((string) $type, self::TYPE_LABELS)
            ? $type
            : null;

        return [
            'schema_version' => ActionDestination::SCHEMA_VERSION,
            'type' => $type,
            'reference_id' => null,
            'value' => null,
            'display' => $type === CoreActionType::Form->value ? 'modal' : null,
            'open_in_new_tab' => false,
        ];
    }

    /**
     * @param  array<int, string>  $allowedTypes
     * @return array<string, int|string|bool>|null
     */
    public function dehydrate(
        mixed $state,
        bool $required,
        array $allowedTypes,
        bool $allowNewTab = true,
    ): ?array {
        if (! is_array($state) || $this->isEmpty($state)) {
            if ($required) {
                throw new InvalidActionPickerState('مقصد دکمه را انتخاب کنید.');
            }

            return null;
        }

        if (! $allowNewTab) {
            $state['open_in_new_tab'] = false;
        }

        if ($message = $this->canonicalValidationMessage($state, $required, $allowedTypes)) {
            throw new InvalidActionPickerState($message);
        }

        return $this->normalizer->normalize($state)->toArray();
    }

    /**
     * @param  array<int, string>  $allowedTypes
     */
    public function validationMessage(
        array $state,
        bool $required,
        array $allowedTypes,
    ): ?string {
        if ($message = $this->canonicalValidationMessage($state, $required, $allowedTypes)) {
            return $message;
        }

        $destination = $this->normalizer->normalize($state);

        if ($destination->coreType()?->usesReference()
            && ! $this->referenceIsAvailable(
                (string) $destination->type,
                $destination->referenceId,
            )) {
            return $destination->type === CoreActionType::Form->value
                ? 'فرم انتخاب‌شده در دسترس نیست.'
                : 'مقصد انتخاب‌شده در دسترس نیست.';
        }

        return null;
    }

    /** @return array<int|string, string> */
    public function searchOptions(string $targetKey, string $query): array
    {
        $query = trim(Str::squish($query));

        if (mb_strlen($query) < 2) {
            return [];
        }

        if ($targetKey === CoreActionType::Form->value) {
            return $this->searchForms($query);
        }

        $source = $this->internalLinks->get($targetKey);

        if (! $source || ! $source->isAvailable()) {
            return [];
        }

        try {
            return collect($source->search($query, self::MAX_SEARCH_RESULTS))
                ->where('targetKey', $targetKey)
                ->mapWithKeys(fn ($result): array => [
                    $result->referenceId => $this->optionLabel(
                        $result->title,
                        $source->label(),
                        $result->subtitle,
                    ),
                ])
                ->all();
        } catch (Throwable $exception) {
            $this->logFailure($targetKey, $exception);

            return [];
        }
    }

    public function selectedOptionLabel(string $targetKey, mixed $referenceId): string
    {
        $referenceId = filter_var($referenceId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($referenceId === false) {
            return 'مقصد در دسترس نیست';
        }

        if ($targetKey === CoreActionType::Form->value) {
            return Form::query()
                ->published()
                ->whereKey($referenceId)
                ->value('name') ?? 'فرم در دسترس نیست';
        }

        $source = $this->internalLinks->get($targetKey);

        if (! $source instanceof ResolvesInternalLinkReference) {
            return 'مقصد در دسترس نیست';
        }

        try {
            return $source->find($referenceId)?->title ?? 'مقصد در دسترس نیست';
        } catch (Throwable $exception) {
            $this->logFailure($targetKey, $exception);

            return 'مقصد در دسترس نیست';
        }
    }

    /**
     * @param  array<int, string>  $allowedTypes
     */
    private function canonicalValidationMessage(
        array $state,
        bool $required,
        array $allowedTypes,
    ): ?string {
        $destination = $this->normalizer->normalize($state);

        if ($destination->type === null) {
            return $required || ! $this->isEmpty($state)
                ? 'مقصد دکمه را انتخاب کنید.'
                : null;
        }

        if (! in_array($destination->type, $allowedTypes, true)
            || ! array_key_exists($destination->type, self::TYPE_LABELS)) {
            return 'نوع مقصد انتخاب‌شده معتبر نیست.';
        }

        $validation = $this->validator->validate($destination);

        if ($validation->isValid()) {
            return null;
        }

        $field = array_key_first($validation->errors);

        return match ($field) {
            'schema_version' => 'نسخه داده مقصد پشتیبانی نمی‌شود.',
            'reference_id' => $destination->type === CoreActionType::Form->value
                ? 'یک فرم منتشرشده را انتخاب کنید.'
                : 'یک مقصد معتبر را انتخاب کنید.',
            'display' => 'نحوه نمایش فرم را انتخاب کنید.',
            'value' => match ($destination->type) {
                CoreActionType::CustomUrl->value => 'لینک واردشده معتبر نیست.',
                CoreActionType::Anchor->value => 'شناسه بخش معتبر نیست.',
                CoreActionType::Email->value => 'ایمیل واردشده معتبر نیست.',
                CoreActionType::Phone->value => 'شماره تلفن واردشده معتبر نیست.',
                default => 'مقدار مقصد معتبر نیست.',
            },
            'open_in_new_tab' => 'بازکردن در تب جدید برای این مقصد مجاز نیست.',
            default => 'اطلاعات مقصد معتبر نیست.',
        };
    }

    private function referenceIsAvailable(string $targetKey, ?int $referenceId): bool
    {
        if ($referenceId === null) {
            return false;
        }

        if ($targetKey === CoreActionType::Form->value) {
            return Form::query()
                ->published()
                ->whereKey($referenceId)
                ->exists();
        }

        $source = $this->internalLinks->get($targetKey);

        if (! $source instanceof ResolvesInternalLinkReference) {
            return false;
        }

        try {
            return $source->find($referenceId) !== null;
        } catch (Throwable $exception) {
            $this->logFailure($targetKey, $exception);

            return false;
        }
    }

    /** @return array<int|string, string> */
    private function searchForms(string $query): array
    {
        $contains = '%'.$this->escapeLike($query).'%';
        $prefix = $this->escapeLike($query).'%';

        return Form::query()
            ->published()
            ->where(function (Builder $builder) use ($contains): void {
                $builder
                    ->where('name', 'like', $contains)
                    ->orWhere('slug', 'like', $contains);
            })
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
                [$query, $prefix],
            )
            ->orderBy('id')
            ->limit(self::MAX_SEARCH_RESULTS)
            ->pluck('name', 'id')
            ->all();
    }

    private function optionLabel(
        string $title,
        string $targetLabel,
        string $subtitle,
    ): string {
        return implode(' — ', array_filter([
            $title,
            $targetLabel,
            $subtitle,
        ], fn (string $part): bool => $part !== ''));
    }

    private function isEmpty(array $state): bool
    {
        return blank($state['type'] ?? null)
            && blank($state['reference_id'] ?? null)
            && blank($state['value'] ?? null)
            && blank($state['display'] ?? null);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function logFailure(string $targetKey, Throwable $exception): void
    {
        $this->logger->error('Action Picker reference lookup failed.', [
            'target_key' => $targetKey,
            'exception' => $exception,
        ]);
    }
}
