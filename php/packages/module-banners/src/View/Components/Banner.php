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
 *
 * The video is the widgets' backdrop, `<x-webx-video variant="background">` (§10 of the widgets
 * spec): muted, looping, with its pause button, played only on screen and never under reduced
 * motion or on a connection saving data. It has no poster of its own: the banner's `<picture>`
 * is the poster — the one image of the frame, sized and as eager as the first slide asks — and
 * the video fades in over it with its first frame. A phone has its own video (`video_mobile`),
 * else the main one where the place says `video_on_mobile`, else none: the two are two
 * backdrops, and the breakpoint's CSS hides the one that is not for this width — hidden, it is
 * never on screen, so the widget neither plays nor loads it.
 *
 * Where the place says `parallax`, the picture and the video move slower than the page (the
 * widgets' `data-webx-parallax`, §14 of their spec): they are wrapped in a second
 * `webx-banner__picture` that carries the shift, so every rule written for the picture's children
 * holds as it is, and the outer one crops the overscan even in a place whose layers step out of
 * the frame. The stage is not inside it: the layers stay where the editor put them in the frame.
 * The option is the strength too: `true` or `medium`, `soft`, `strong` ({@see strength()}).
 */
final class Banner extends Component
{
    /** @var array<string, mixed> */
    public array $options;

    public int $breakpoint;

    public string $frameStyle;

    /**
     * How strongly the picture and the video move slower than the page — `soft`, `medium` or
     * `strong` — or null where they stand still.
     */
    public ?string $parallax;

    /**
     * What the frame shows before a video and where none plays: the poster on a banner whose
     * video plays here, else the picture.
     *
     * @var array<string, mixed>|null
     */
    public ?array $still = null;

    /**
     * The same below the breakpoint, when it is another file than above it; null — one for both.
     *
     * @var array<string, mixed>|null
     */
    public ?array $stillMobile = null;

    /**
     * The backdrops to print: the file, and the class that says the width it is for.
     *
     * @var list<array{file: array<string, mixed>, class: string}>
     */
    public array $videos = [];

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
        $this->parallax = self::strength($this->options['parallax'] ?? false);

        $ratio = self::ratio($this->options['ratio'] ?? null) ?? '16/6';
        $ratioMobile = self::ratio($this->options['ratio_mobile'] ?? null) ?? $ratio;
        $vars = ["--ratio:{$ratio}", "--ratio-mobile:{$ratioMobile}"];

        // The stage has the picture's proportions; a picture the library has no size for stands
        // in the frame as it is, and the stage with it.
        $vars[] = '--img:'.(self::number($banner['ratio'] ?? null) ?? "calc({$ratio})");
        $vars[] = '--img-m:'.(self::number($banner['ratio_mobile'] ?? null) ?? "calc({$ratioMobile})");

        $this->frameStyle = implode(';', $vars);

        $this->media($banner);
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
        ]);
    }

    /**
     * Which video plays above the breakpoint and which below it, and what stands in the frame
     * before them. A banner without a video of its own for phones and without a poster gets
     * exactly what it got before both were there.
     *
     * @param  array<string, mixed>  $banner
     */
    private function media(array $banner): void
    {
        $image = self::file($banner['image'] ?? null);
        $poster = self::file($banner['poster'] ?? null);
        $wide = self::file($banner['video'] ?? null);
        $narrow = null;

        if ($this->breakpoint > 1) {
            $narrow = self::file($banner['video_mobile'] ?? null)
                ?? (($this->options['video_on_mobile'] ?? false) ? $wide : null);
        }

        $this->still = ($wide !== null ? $poster : null) ?? $image;

        if ($this->breakpoint > 1) {
            $mobile = self::file($banner['image_mobile'] ?? null)
                ?? (($narrow !== null ? $poster : null) ?? $image);

            $this->stillMobile = $mobile !== null && ($mobile['url'] ?? null) !== ($this->still['url'] ?? null) ? $mobile : null;
        }

        if ($wide !== null && $narrow !== null && $wide['url'] === $narrow['url']) {
            $this->videos[] = ['file' => $wide, 'class' => ''];

            return;
        }

        if ($wide !== null) {
            $this->videos[] = ['file' => $wide, 'class' => 'webx-banner__video--wide'];
        }

        if ($narrow !== null) {
            $this->videos[] = ['file' => $narrow, 'class' => 'webx-banner__video--narrow'];
        }
    }

    /**
     * A file of the card — a picture or a video — or null when there is none to print.
     *
     * @return array<string, mixed>|null
     */
    private static function file(mixed $value): ?array
    {
        return is_array($value) && is_string($value['url'] ?? null) && $value['url'] !== '' ? $value : null;
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

    /**
     * The strength a `parallax` option asks for: `soft` and `strong` as they are, any other yes
     * (`true`, `medium`, `"1"`) `medium`, and a no (`false`, `off`, `"0"`, empty) null.
     */
    public static function strength(mixed $option): ?string
    {
        if (is_string($option)) {
            $option = strtolower(trim($option));

            if (in_array($option, ['soft', 'medium', 'strong'], true)) {
                return $option;
            }
        }

        return filter_var($option, FILTER_VALIDATE_BOOLEAN) ? 'medium' : null;
    }
}
