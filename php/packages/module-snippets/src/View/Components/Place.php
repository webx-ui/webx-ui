<?php

declare(strict_types=1);

namespace WebxUi\Snippets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use WebxUi\Snippets\Output\Printer;

/**
 * `<x-webx-snippets place="head" />` — the codes of one place, where the theme puts it (§5.1).
 *
 * Drawn through the view `webx-snippets::place`, which is also what theme-default asks
 * `view()->exists()` about before it prints a place: without this module there is no component
 * to compile against, and the layout stays as it was.
 */
final class Place extends Component
{
    public function __construct(public string $place) {}

    public function render(): View
    {
        return view('webx-snippets::place', [
            'html' => new HtmlString(app(Printer::class)->place($this->place)),
        ]);
    }
}
