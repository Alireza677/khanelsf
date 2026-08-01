<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use App\Filament\Resources\MenuResource;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\MenuTreeService;
use App\Services\NavigationSourceVisibility;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EditMenu extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = MenuResource::class;

    protected static string $view = 'filament.resources.menu-resource.pages.edit-menu';

    /**
     * @var array<int, int|string>
     */
    public array $selectedPageIds = [];

    /**
     * @var array<int, string>
     */
    public array $selectedSourceKeys = [];

    public ?string $customItemTitle = null;

    public ?string $customItemUrl = null;

    public string $customItemTarget = '_self';

    public function getAvailablePages(): Collection
    {
        $visibility = app(NavigationSourceVisibility::class);

        return Page::query()
            ->select(['id', 'title', 'slug', 'status'])
            ->orderBy('title')
            ->get()
            ->filter(fn (Page $page): bool => $visibility->canAdd(
                MenuItem::TYPE_PAGE,
                $this->pageUrl($page),
            ) && ! $this->urlBelongsToRegisteredSource($this->pageUrl($page)))
            ->values();
    }

    /**
     * @return array<int, array{source_key: string, label: string, module: string|null, url: string|null}>
     */
    public function getVisibleNavigationSources(): array
    {
        return app(NavigationSourceVisibility::class)->visibleSources();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMenuTree(): array
    {
        return app(MenuTreeService::class)->tree($this->getRecord());
    }

    /**
     * @param  array<int, mixed>  $tree
     */
    public function saveMenuTree(array $tree): void
    {
        try {
            app(MenuTreeService::class)->save($this->getRecord(), $tree);
        } catch (ValidationException $exception) {
            $message = $exception->validator->errors()->first('menuTree');
            $this->addError('menuTree', $message);

            Notification::make()
                ->title('ساختار منو ذخیره نشد.')
                ->body($message)
                ->danger()
                ->send();

            return;
        }

        $this->resetErrorBag('menuTree');

        Notification::make()
            ->title('ساختار منو ذخیره شد.')
            ->success()
            ->send();
    }

    public function addSelectedPages(): void
    {
        $validated = $this->validate([
            'selectedPageIds' => ['required', 'array', 'min:1'],
            'selectedPageIds.*' => ['integer', 'distinct', 'exists:pages,id'],
        ], [
            'selectedPageIds.required' => 'حداقل یک برگه را انتخاب کنید.',
            'selectedPageIds.min' => 'حداقل یک برگه را انتخاب کنید.',
        ]);

        $selectedIds = array_map('intval', $validated['selectedPageIds']);
        $pages = Page::query()
            ->whereKey($selectedIds)
            ->get()
            ->sortBy(fn (Page $page): int => array_search($page->getKey(), $selectedIds, true));

        $visibility = app(NavigationSourceVisibility::class);

        if ($pages->contains(fn (Page $page): bool => ! $visibility->canAdd(
            MenuItem::TYPE_PAGE,
            $this->pageUrl($page),
        ) || $this->urlBelongsToRegisteredSource($this->pageUrl($page)))) {
            throw ValidationException::withMessages([
                'selectedPageIds' => 'یکی از برگه‌های انتخاب‌شده به بخش غیرفعال سایت مربوط است.',
            ]);
        }

        DB::transaction(function () use ($pages): void {
            $sortOrder = $this->nextMenuItemSortOrder();

            foreach ($pages as $page) {
                $this->getRecord()->items()->create([
                    'type' => MenuItem::TYPE_PAGE,
                    'source_key' => null,
                    'reference_id' => $page->getKey(),
                    'reference_type' => $page->getMorphClass(),
                    'title' => $page->title,
                    'url' => null,
                    'target' => '_self',
                    'sort_order' => $sortOrder++,
                    'status' => 'active',
                ]);
            }
        });

        $this->reset('selectedPageIds');

        Notification::make()
            ->title($pages->count().' برگه به منو افزوده شد.')
            ->success()
            ->send();
    }

    public function addSelectedSources(): void
    {
        $validated = $this->validate([
            'selectedSourceKeys' => ['required', 'array', 'min:1'],
            'selectedSourceKeys.*' => ['required', 'string', 'distinct', 'max:128'],
        ], [
            'selectedSourceKeys.required' => 'حداقل یک مقصد سیستمی را انتخاب کنید.',
            'selectedSourceKeys.min' => 'حداقل یک مقصد سیستمی را انتخاب کنید.',
            'selectedSourceKeys.*.distinct' => 'هر مقصد سیستمی را فقط یک‌بار انتخاب کنید.',
        ]);

        $registry = app(NavigationSourceRegistry::class);
        $sourceKeys = array_values($validated['selectedSourceKeys']);

        foreach ($sourceKeys as $sourceKey) {
            if (! $registry->exists($sourceKey)) {
                throw ValidationException::withMessages([
                    'selectedSourceKeys' => 'یکی از مقصدهای انتخاب‌شده در سیستم ثبت نشده است.',
                ]);
            }

            if (! $registry->isAvailable($sourceKey)) {
                throw ValidationException::withMessages([
                    'selectedSourceKeys' => 'یکی از مقصدهای انتخاب‌شده در حال حاضر فعال نیست.',
                ]);
            }
        }

        DB::transaction(function () use ($registry, $sourceKeys): void {
            $sortOrder = $this->nextMenuItemSortOrder();

            foreach ($sourceKeys as $sourceKey) {
                $source = $registry->find($sourceKey);

                $this->getRecord()->items()->create([
                    'type' => MenuItem::TYPE_SOURCE,
                    'source_key' => $sourceKey,
                    'reference_id' => null,
                    'reference_type' => null,
                    'title' => $source->label,
                    'url' => null,
                    'target' => '_self',
                    'sort_order' => $sortOrder++,
                    'status' => 'active',
                ]);
            }
        });

        $count = count($sourceKeys);
        $this->reset('selectedSourceKeys');

        Notification::make()
            ->title($count.' مقصد سیستمی به منو افزوده شد.')
            ->success()
            ->send();
    }

    public function addCustomItem(): void
    {
        $validated = $this->validate([
            'customItemTitle' => ['required', 'string', 'max:255'],
            'customItemUrl' => ['required', 'string', 'max:255'],
            'customItemTarget' => ['required', Rule::in(['_self', '_blank'])],
        ], [
            'customItemTitle.required' => 'عنوان پیوند را وارد کنید.',
            'customItemUrl.required' => 'نشانی پیوند را وارد کنید.',
        ]);

        if (! app(NavigationSourceVisibility::class)->canAdd(
            MenuItem::TYPE_CUSTOM_URL,
            $validated['customItemUrl'],
        ) || $this->urlBelongsToRegisteredSource($validated['customItemUrl'])) {
            throw ValidationException::withMessages([
                'customItemUrl' => 'این نشانی یک مقصد سیستمی است یا به بخش غیرفعال سایت مربوط است؛ آن را از مقصدهای سیستمی انتخاب کنید.',
            ]);
        }

        $this->getRecord()->items()->create([
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'source_key' => null,
            'reference_id' => null,
            'reference_type' => null,
            'title' => $validated['customItemTitle'],
            'url' => $validated['customItemUrl'],
            'target' => $validated['customItemTarget'],
            'sort_order' => $this->nextMenuItemSortOrder(),
            'status' => 'active',
        ]);

        $this->reset('customItemTitle', 'customItemUrl');
        $this->customItemTarget = '_self';

        Notification::make()
            ->title('پیوند دلخواه به منو افزوده شد.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMenuItem(int $itemId, array $data): void
    {
        $item = $this->findOwnedMenuItem($itemId);

        if (! $item) {
            return;
        }

        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'url' => [
                Rule::prohibitedIf($item->type !== MenuItem::TYPE_CUSTOM_URL || filled($item->source_key)),
                'nullable',
                'string',
                'max:255',
            ],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'title.required' => 'عنوان آیتم را وارد کنید.',
            'target.in' => 'مقدار Target معتبر نیست.',
            'status.in' => 'وضعیت آیتم معتبر نیست.',
        ]);

        $errorPrefix = "menuItems.{$itemId}";
        $errorKeys = array_map(
            fn (string $field): string => "{$errorPrefix}.{$field}",
            ['title', 'url', 'target', 'status'],
        );
        $this->resetErrorBag($errorKeys);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError("{$errorPrefix}.{$field}", $message);
                }
            }

            Notification::make()
                ->title('تغییرات آیتم ذخیره نشد.')
                ->danger()
                ->send();

            return;
        }

        $validated = $validator->validated();

        if (
            $item->type === MenuItem::TYPE_CUSTOM_URL
            && blank($item->source_key)
            && filled($validated['url'] ?? null)
            && $this->urlBelongsToRegisteredSource($validated['url'])
        ) {
            $this->addError(
                "{$errorPrefix}.url",
                'این نشانی یک مقصد سیستمی است؛ آن را از مقصدهای سیستمی انتخاب کنید.',
            );

            Notification::make()
                ->title('تغییرات آیتم ذخیره نشد.')
                ->danger()
                ->send();

            return;
        }

        if ($item->type !== MenuItem::TYPE_CUSTOM_URL || filled($item->source_key)) {
            unset($validated['url']);
        }

        $item->update($validated);

        Notification::make()
            ->title('آیتم منو به‌روزرسانی شد.')
            ->success()
            ->send();
    }

    public function deleteMenuItem(int $itemId): void
    {
        $item = $this->findOwnedMenuItem($itemId);

        if (! $item) {
            return;
        }

        DB::transaction(function () use ($item): void {
            $item->delete();

            app(MenuTreeService::class)->save(
                $this->getRecord(),
                app(MenuTreeService::class)->tree($this->getRecord()),
            );
        });

        $this->resetErrorBag(array_map(
            fn (string $field): string => "menuItems.{$itemId}.{$field}",
            ['title', 'url', 'target', 'status'],
        ));

        Notification::make()
            ->title('آیتم منو حذف شد.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    private function nextMenuItemSortOrder(): int
    {
        $maximum = $this->getRecord()->items()->max('sort_order');

        return $maximum === null ? 0 : ((int) $maximum) + 1;
    }

    private function pageUrl(Page $page): string
    {
        return $page->slug === 'home' ? '/' : '/'.$page->slug;
    }

    private function urlBelongsToRegisteredSource(?string $url): bool
    {
        $candidate = $this->relativePath($url);

        if ($candidate === null) {
            return false;
        }

        foreach (app(NavigationSourceRegistry::class)->all() as $source) {
            if ($candidate === $this->relativePath($source->resolve())) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(?string $url): ?string
    {
        if (blank($url) || preg_match('/^https?:\/\//i', (string) $url)) {
            return null;
        }

        $path = parse_url((string) $url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return rtrim('/'.ltrim($path, '/'), '/') ?: '/';
    }

    private function findOwnedMenuItem(int $itemId): ?MenuItem
    {
        $item = $this->getRecord()->items()->whereKey($itemId)->first();

        if ($item) {
            return $item;
        }

        Notification::make()
            ->title('آیتم موردنظر در این منو پیدا نشد.')
            ->danger()
            ->send();

        return null;
    }
}
