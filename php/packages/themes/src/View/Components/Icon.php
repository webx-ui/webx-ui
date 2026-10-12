<?php

declare(strict_types=1);

namespace WebxUi\Themes\View\Components;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Themes\ThemeIcons;

/**
 * `<x-theme::icon name="phone" />` — an icon of the chain's sprite (spec §13.1):
 *
 *     <svg class="theme-icon" width="1em" height="1em" fill="currentColor" aria-hidden="true"
 *          focusable="false"><use href="/themes/icons/<hash>/icons.svg#phone"/></svg>
 *
 * One em and the colour of the text, so it sits in a line of words without a stylesheet; a class
 * or a width given to the tag wins. With `label` it is a picture with a name (`role="img"`)
 * rather than decoration.
 *
 * An unknown name, or a sprite sync has not published, gives the same `<svg>` with nothing in it
 * — a page never fails over an icon. `webx:theme:check` names the unknown ones in the templates
 * of the layers, and in debug mode a comment says which command was forgotten.
 */
class Icon extends Component
{
    public function __construct(
        public string $name,
        public ?string $label = null,
    ) {}

    public function render(): View
    {
        $icons = app(ThemeIcons::class);
        $known = $icons->has($this->name);
        $sprite = $known ? $icons->url(app(UrlGenerator::class)) : null;

        return view('webx-themes::icon', [
            'href' => $sprite === null ? null : $sprite.'#'.$this->name,
            'hint' => $this->hint($icons, $known, $sprite),
        ]);
    }

    private function hint(ThemeIcons $icons, bool $known, ?string $sprite): ?string
    {
        if (! app(Repository::class)->get('app.debug')) {
            return null;
        }

        return match (true) {
            ! $known => "no icon {$this->name} in the chain: add icons/{$this->name}.svg to a layer",
            $sprite === null => 'the icon sprite is not published: php artisan webx:theme:sync',
            // Only in debug: reading every icon of the chain once a request is not free.
            $icons->current() !== $icons->hash() => 'the icon sprite is out of date: php artisan webx:theme:sync',
            default => null,
        };
    }
}
