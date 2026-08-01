<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MenuTreeService
{
    private const MAX_DEPTH = 20;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(Menu $menu): array
    {
        $items = $menu->items()
            ->select([
                'id',
                'parent_id',
                'type',
                'source_key',
                'reference_id',
                'reference_type',
                'title',
                'url',
                'target',
                'sort_order',
                'status',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $itemsById = $items->keyBy('id');
        $childrenByParent = [];

        foreach ($items as $item) {
            $parentId = $item->parent_id;

            if (
                $parentId === null
                || $parentId === $item->getKey()
                || ! $itemsById->has($parentId)
            ) {
                $parentId = 0;
            }

            $childrenByParent[$parentId][] = $item->getKey();
        }

        $visited = [];
        $buildNode = function (int $id, array $ancestors = []) use (&$buildNode, &$visited, $childrenByParent, $itemsById): ?array {
            if (isset($visited[$id]) || isset($ancestors[$id])) {
                return null;
            }

            $item = $itemsById->get($id);

            if (! $item) {
                return null;
            }

            $visited[$id] = true;
            $ancestors[$id] = true;
            $children = [];

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                if ($child = $buildNode((int) $childId, $ancestors)) {
                    $children[] = $child;
                }
            }

            return [
                'id' => $item->getKey(),
                'type' => $item->type,
                'source_key' => $item->source_key,
                'reference_id' => $item->reference_id,
                'reference_type' => $item->reference_type,
                'title' => $item->title,
                'url' => $item->resolvedUrl(),
                'target' => $item->target,
                'status' => $item->status,
                'children' => $children,
            ];
        };

        $tree = [];

        foreach ($childrenByParent[0] ?? [] as $rootId) {
            if ($node = $buildNode((int) $rootId)) {
                $tree[] = $node;
            }
        }

        // Existing malformed cycles or cross-menu parents remain visible and recoverable.
        foreach ($items as $item) {
            if ($node = $buildNode((int) $item->getKey())) {
                $tree[] = $node;
            }
        }

        return $tree;
    }

    /**
     * @param  array<int, mixed>  $tree
     */
    public function save(Menu $menu, array $tree): void
    {
        DB::transaction(function () use ($menu, $tree): void {
            $items = MenuItem::query()
                ->where('menu_id', $menu->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $updates = [];
            $seen = [];
            $this->flatten($tree, null, 1, $seen, $updates);

            $expectedIds = $items->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $receivedIds = array_keys($seen);
            sort($receivedIds);

            if ($receivedIds !== $expectedIds) {
                throw ValidationException::withMessages([
                    'menuTree' => 'ساختار ارسالی با آیتم‌های این منو مطابقت ندارد.',
                ]);
            }

            foreach ($updates as $update) {
                $items->get($update['id'])->update([
                    'parent_id' => $update['parent_id'],
                    'sort_order' => $update['sort_order'],
                ]);
            }
        });
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  array<int, true>  $seen
     * @param  array<int, array{id: int, parent_id: ?int, sort_order: int}>  $updates
     */
    private function flatten(
        array $nodes,
        ?int $parentId,
        int $depth,
        array &$seen,
        array &$updates,
    ): void {
        if ($nodes === []) {
            return;
        }

        if ($depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'menuTree' => 'عمق منو نمی‌تواند بیشتر از '.self::MAX_DEPTH.' سطح باشد.',
            ]);
        }

        foreach (array_values($nodes) as $order => $node) {
            if (! is_array($node) || ! isset($node['id']) || ! is_numeric($node['id'])) {
                throw ValidationException::withMessages([
                    'menuTree' => 'ساختار درخت منو معتبر نیست.',
                ]);
            }

            $id = (int) $node['id'];

            if ($id < 1 || isset($seen[$id])) {
                throw ValidationException::withMessages([
                    'menuTree' => 'هر آیتم باید دقیقاً یک‌بار در ساختار منو قرار بگیرد.',
                ]);
            }

            $seen[$id] = true;
            $updates[] = [
                'id' => $id,
                'parent_id' => $parentId,
                'sort_order' => $order,
            ];

            $children = $node['children'] ?? [];

            if (! is_array($children)) {
                throw ValidationException::withMessages([
                    'menuTree' => 'فرزندان آیتم منو باید یک فهرست معتبر باشند.',
                ]);
            }

            if ($children !== []) {
                $this->flatten($children, $id, $depth + 1, $seen, $updates);
            }
        }
    }
}
