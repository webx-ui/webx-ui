<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tags;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * `<x-webx-block type="recipe-card" :card="$card" fallback="…" />` — a block type called from
 * any template: another block's, a module's view, the site's layout.
 *
 * A class component because a block's template is compiled by `Blade::compileString()`, which
 * reads `<x-…>` exactly as a view file does — one tag, then, for all three places, with slots,
 * in a syntax a front-end developer and an agent already know. Not `<x-dynamic-component>`: its
 * compiler is a static of the process, and here the tag's name never changes; the type is an
 * attribute.
 *
 * `type` and `fallback` are the tag's own; every other attribute is a value under its own name,
 * so `:cta_label="…"` is `$cta_label` in the template and `cta-label` is
 * `$block->value('cta-label')` — the same as a block's fields.
 */
final class BlockTag extends Component
{
    public function __construct(
        public string $type,
        public ?string $fallback = null,
    ) {}

    /**
     * Whether the tag can be written here: the package is installed and its provider has run.
     * What `@webxPart` asks before it chooses between the tag and a plain include.
     */
    public static function available(): bool
    {
        return Container::getInstance()->bound(Renderer::class)
            && (Blade::getClassComponentAliases()['webx-block'] ?? null) === self::class;
    }

    public function render(): Closure
    {
        return fn (array $data): Htmlable => $this->draw($data);
    }

    /**
     * The closure straight to the view factory, which prints an `Htmlable` as it is. Laravel's
     * own resolver would treat the returned markup as the name of a view, and failing that as
     * Blade source to compile — markup with a `{{` in it would then be evaluated a second time,
     * and each distinct card cached as a view of its own.
     */
    public function resolveView(): Closure
    {
        return $this->render();
    }

    /**
     * The slots are only known now, at the closing tag — the default one as `slot`, each
     * `<x-slot:name>` under its name — and so are the attributes: the compiled tag sets them
     * after asking for the view.
     *
     * @param  array<string, mixed>  $data
     */
    private function draw(array $data): HtmlString
    {
        $slots = [];

        foreach ($data['__laravel_slots'] ?? [] as $name => $slot) {
            if ($slot instanceof Htmlable) {
                $slots[$name === '__default' ? 'slot' : (string) $name] = $slot;
            }
        }

        $values = [];

        // A bound string arrives escaped: Blade sanitises every attribute of a class component for
        // `{{ $attributes }}`, which is not how these are printed — a value is a value, and the
        // template escapes it where it prints it. Unescaped once here, a literal written with
        // `&amp;` reads as the `&` it means, the way HTML reads it.
        foreach ($this->attributes?->getAttributes() ?? [] as $name => $value) {
            $values[(string) $name] = is_string($value) ? htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5) : $value;
        }

        return Container::getInstance()->make(Renderer::class)->tag($this->type, $values, $slots, $this->fallback);
    }
}
