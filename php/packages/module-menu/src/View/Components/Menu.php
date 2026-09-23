<?php

declare(strict_types=1);

namespace WebxUi\Menu\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Menu\Menus;

/**
 * `<x-webx-menu::menu name="header" />` — a menu printed as a nested `<ul>`.
 *
 * Second to the helper, and it should be. Every real site has markup of its own, and a menu is
 * ten lines of it; what this saves is the first hour of a new site, and `vendor:publish
 * --tag=webx-menu-views` is how it stops saving anything.
 */
class Menu extends Component
{
    public function __construct(
        private readonly Menus $menus,
        public readonly string $name = '',
        public readonly ?string $locale = null,
    ) {}

    public function render(): View
    {
        return view('webx-menu::menu', [
            'items' => $this->menus->tree($this->name, $this->locale),
        ]);
    }
}
