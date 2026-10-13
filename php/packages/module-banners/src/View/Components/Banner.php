<?php

declare(strict_types=1);

namespace WebxUi\Banners\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Banners\Rendering\Stylesheet;

/**
 * `<x-webx-banner :banner="$card" />` — one banner, from its card (§4.3 of the layers spec): the
 * frame, the picture (and the one for phones), the video, and the layers on their stage — or, for
 * a banner without layers, its words and buttons in a corner, as the guide's sample has them.
 *
 * `layout` is what `banners_layout()` answered for its place; without it the component asks.
 * `eager` loads the picture at once — the first banner of a page; null leaves the choice to
 * whatever it stands in (a slide of `<x-webx-slider>` makes the ones out of view lazy).
 */
final class Banner extends Component
{
    /** @var array<string, mixed> */
    public array $options;

    public int $breakpoint;

    public string $frameStyle;

    /**
     * @param  array<string, mixed>  $banner
     * @param  array<string, mixed>|null  $layout
     */
    public function __construct(
        private readonly Stylesheet $stylesheet,
        public array $banner = [],
        ?array $layout = null,
        public ?bool $eager = null,
    ) {
        $this->options = $layout ?? banners_layout(is_string($banner['place'] ?? null) ? $banner['place'] : null);
        $this->breakpoint = max(0, (int) ($this->options['breakpoint'] ?? 768));

        $ratio = self::ratio($this->options['ratio'] ?? null) ?? '16/6';
        $ratioMobile = self::ratio($this->options['ratio_mobile'] ?? null) ?? $ratio;
        $vars = ["--ratio:{$ratio}", "--ratio-mobile:{$ratioMobile}"];

        // The stage has the picture's proportions; a picture the library has no size for stands
        // in the frame as it is, and the stage with it.
        $vars[] = '--img:'.(self::number($banner['ratio'] ?? null) ?? "calc({$ratio})");
        $vars[] = '--img-m:'.(self::number($banner['ratio_mobile'] ?? null) ?? "calc({$ratioMobile})");

        $this->frameStyle = implode(';', $vars);
    }

    public function shouldRender(): bool
    {
        return $this->banner !== [];
    }

    public function render(): View
    {
        // Asked for here and not in the constructor: a banner that does not render must not
        // mark the page's stylesheet as printed.
        return view('webx-banners::components.banner', [
            'css' => $this->stylesheet->base().$this->stylesheet->breakpoint($this->breakpoint),
            'scripts' => is_array($this->banner['video'] ?? null) ? $this->stylesheet->scripts() : '',
        ]);
    }

    /** `16/6`, `16 / 6` or `2.4` — anything else is not a ratio and is not printed into CSS. */
    private static function ratio(mixed $value): ?string
    {
        $value = is_numeric($value) ? (string) $value : (is_string($value) ? trim($value) : '');

        return preg_match('~^\d+(\.\d+)?(\s*/\s*\d+(\.\d+)?)?$~', $value) === 1 ? $value : null;
    }

    private static function number(mixed $value): ?string
    {
        return is_numeric($value) && (float) $value > 0 ? (string) round((float) $value, 4) : null;
    }
}
