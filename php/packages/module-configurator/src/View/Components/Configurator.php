<?php

declare(strict_types=1);

namespace WebxUi\Configurator\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use WebxUi\Configurator\Catalog\PresetAddress;
use WebxUi\Configurator\Rendering\Assets;
use WebxUi\Configurator\Site\LiveConfigurators;
use WebxUi\Configurator\Site\Presenter;

/**
 * `<x-webx-configurator key="shower" />` — a configurator on the site (§12.2), in a block or in a
 * template of the site without one.
 *
 * `title` — the heading over it: empty is the configurator's name, `-` is none. `preset` — the
 * starting choice over the schema's `initial`, `{ step: value, field: number }`, what the block
 * keeps; what the schema no longer has is dropped quietly. The address may carry a preset of its
 * own for this configurator — a product of the catalogue sends a visitor here with one (§22.3) —
 * and it wins over the block's, read by the engine like any other: an unknown key or value
 * dropped, a size clamped.
 *
 * A key with nothing published under it prints nothing: a page with one section missing is better
 * than a page that says «not found» to a customer, and the panel is where somebody finds out.
 */
final class Configurator extends Component
{
    /**
     * @param  array<string, mixed>|null  $preset
     */
    public function __construct(
        private readonly LiveConfigurators $live,
        private readonly Presenter $presenter,
        private readonly Assets $assets,
        private readonly Request $request,
        private readonly ?string $key = null,
        private readonly ?string $title = null,
        private readonly ?array $preset = null,
    ) {}

    public function render(): View|string
    {
        $live = $this->live->find((string) $this->key);

        if ($live === null) {
            return '';
        }

        $preset = [...($this->preset ?? []), ...PresetAddress::from($this->request, $live['key'])];
        $data = $this->presenter->present($live, $preset, $this->title, $this->id($live['key']));

        return view('webx-configurator::configurator', [
            ...$data,
            'script' => $this->assets->script(),
        ]);
    }

    /** One id per configurator on the page: the same one twice is `-2`, as the inbox counts forms. */
    private function id(string $key): string
    {
        $base = 'webx-configurator-'.$key;
        $seen = $this->request->attributes->get('webx-configurator.ids', []);
        $seen = is_array($seen) ? $seen : [];
        $count = (int) ($seen[$base] ?? 0) + 1;
        $seen[$base] = $count;
        $this->request->attributes->set('webx-configurator.ids', $seen);

        return $count === 1 ? $base : $base.'-'.$count;
    }
}
