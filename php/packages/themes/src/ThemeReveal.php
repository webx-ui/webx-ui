<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Support\HtmlString;

/**
 * The entrance of a theme's blocks as the page scrolls (spec §9.3): what `@webxReveal` prints.
 *
 * The theme decides whether its blocks come in at all — `"reveal"` in the manifest, off unless a
 * layer names it — and the block may say no for itself. A template marks the parts that come in
 * (`@webxReveal` on its words, `@webxReveal('stagger')` on a list, whose children then come one
 * after another); this class is the one place that turns the mark into `data-webx-reveal` or into
 * nothing. The motion itself is `webx-ui/widgets`' (`reveal.js`): it hides only what is below the
 * screen when it runs, so the first screen is never hidden and never blinks, and without
 * JavaScript or with reduced motion everything is simply there.
 */
final readonly class ThemeReveal
{
    /** The effects of `data-webx-reveal`, the first is what `"reveal": true` means. */
    public const array EFFECTS = ['up', 'fade', 'scale', 'off'];

    public function __construct(private ThemeChain $chain) {}

    /** The effect of the chain, or null when the theme's blocks do not come in. */
    public function effect(): ?string
    {
        return $this->chain->reveal();
    }

    /**
     * The attributes for one element, with the space before them; empty when nothing comes in.
     *
     * @param  string  $mode  Empty — the element itself, with the theme's effect; `stagger` — its
     *                        children one after another, each with that effect; `up`, `fade`,
     *                        `scale` — the element itself with this effect rather than the theme's.
     * @param  mixed  $off  The block's own "no animation": anything truthy prints nothing.
     */
    public function attributes(string $mode = '', mixed $off = false): HtmlString
    {
        $effect = $this->effect();

        if ($effect === null || ! in_array($off, [false, null, 0, '', '0'], true)) {
            return new HtmlString('');
        }

        if ($mode === 'stagger') {
            return new HtmlString(' data-webx-reveal="stagger" data-webx-reveal-effect="'.e($effect).'"');
        }

        $own = in_array($mode, ['up', 'fade', 'scale'], true) ? $mode : $effect;

        return new HtmlString(' data-webx-reveal="'.e($own).'"');
    }
}
