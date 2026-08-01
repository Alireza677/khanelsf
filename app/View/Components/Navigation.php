<?php

namespace App\View\Components;

use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Navigation extends Component
{
    public ?Menu $menu;

    public int $maxDepth;

    public function __construct(
        public string $placement,
        public string $variant,
        ?int $maxDepth = null,
    ) {
        $menus = app(MenuService::class);

        $this->menu = match ($placement) {
            'header' => $menus->header(),
            'footer' => $menus->footer(),
            default => null,
        };
        $this->maxDepth = $maxDepth ?? ($variant === 'footer' ? 1 : 2);
    }

    public function render(): View
    {
        return view('components.navigation');
    }
}
