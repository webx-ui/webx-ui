<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The icons of the chain as one sprite (spec §13.1): each layer's `icons/<name>.svg` becomes a
 * `<symbol id="<name>">`, the higher layer winning by name, and `<x-theme::icon name="…">` points
 * a `<use>` at it.
 *
 * Built by `webx:theme:sync` from the layers' own `icons/` rather than from a sprite each layer
 * builds into its `dist/`: a local theme has no build of its own beside the site's Vite, and one
 * file made from the sources is the same answer for both kinds of layer. It is published like the
 * modules' stylesheet ({@see ModuleStyles}) — `public/themes/icons/<hash>/icons.svg`, cached
 * forever, a new hash when any icon changes.
 *
 * An icon takes the colour of the text around it: a fill or stroke written into the file is
 * `currentColor` or `none`, and `webx:theme:check` says so about any other ({@see problems()}).
 */
class ThemeIcons
{
    public const string NAME = 'icons';

    public const string FILE = 'icons.svg';

    /** What a file under `icons/` may be called: the name the template writes, lowercase and dashed. */
    public const string NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Kept on the symbol from the file's root, so a stroke icon stays a stroke icon. */
    private const array ROOT_ATTRIBUTES = ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'fill-rule', 'clip-rule'];

    /** Paint values that follow the text, or paint nothing. */
    private const array FOLLOWING = ['currentcolor', 'none', 'inherit', 'transparent', 'context-fill', 'context-stroke'];

    /** @var array<string, array{file: string, layer: string}>|null */
    private ?array $sources = null;

    private ?string $sprite = null;

    public function __construct(
        private readonly ThemeChain $chain,
        private readonly ThemeAssets $assets,
    ) {}

    /**
     * Every icon of the chain by name, the top layer's where two have one, sorted by name.
     *
     * @return array<string, array{file: string, layer: string}>
     */
    public function sources(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $sources = [];

        // Bottom first, so the layer above overwrites the name.
        foreach (array_reverse($this->chain->layers) as $layer) {
            foreach (self::files($layer) as $name => $file) {
                if (preg_match(self::NAME_PATTERN, $name) === 1) {
                    $sources[$name] = ['file' => $file, 'layer' => $layer->name];
                }
            }
        }

        ksort($sources, SORT_STRING);

        return $this->sources = $sources;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->sources());
    }

    public function has(string $name): bool
    {
        return isset($this->sources()[$name]);
    }

    /** The sprite as sync publishes it; empty when the chain has no icons. */
    public function sprite(): string
    {
        if ($this->sprite !== null) {
            return $this->sprite;
        }

        $symbols = [];

        foreach ($this->sources() as $name => $source) {
            $symbol = self::symbol($name, (string) file_get_contents($source['file']));

            if ($symbol !== null) {
                $symbols[] = "<!-- {$source['layer']} -->\n".$symbol;
            }
        }

        return $this->sprite = $symbols === []
            ? ''
            : "<svg xmlns=\"http://www.w3.org/2000/svg\">\n".implode("\n", $symbols)."\n</svg>\n";
    }

    /** What sync has to publish: the hash of the sprite, or null when there are no icons. */
    public function hash(): ?string
    {
        $sprite = $this->sprite();

        return $sprite === '' ? null : substr(sha1($sprite), 0, 12);
    }

    /** The hash pages link to now, or null before the first sync. */
    public function current(): ?string
    {
        return $this->assets->current($this->layer());
    }

    /** @return 'published'|'unchanged'|'nothing' */
    public function publish(bool $force = false): string
    {
        $sprite = $this->sprite();
        $hash = $this->hash();

        if ($hash === null) {
            return 'nothing';
        }

        return $this->assets->place($this->layer(), $hash, static function (string $staging) use ($sprite): void {
            file_put_contents($staging.'/'.self::FILE, $sprite);
        }, $force);
    }

    public function url(UrlGenerator $urls): ?string
    {
        return $this->assets->url($this->layer(), self::FILE, $urls);
    }

    /**
     * The `icons/*.svg` of one layer by name, whatever the name — {@see sources()} keeps the valid
     * ones, the check reports the rest.
     *
     * @return array<string, string>
     */
    public static function files(ThemeManifest $layer): array
    {
        $files = [];

        foreach (glob($layer->path.'/icons/*.svg') ?: [] as $file) {
            $files[basename($file, '.svg')] = str_replace('\\', '/', $file);
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * The file as a `<symbol>`: its viewBox and paint attributes, its content without scripts,
     * styles and event handlers. Null when it is not an SVG with a viewBox — there is nothing a
     * `<use>` could scale.
     */
    public static function symbol(string $name, string $svg): ?string
    {
        $root = self::root($svg);
        $box = $root === null ? null : self::viewBox($root);

        if ($root === null || $box === null) {
            return null;
        }

        self::clean($root);

        $attributes = 'id="'.htmlspecialchars($name, ENT_XML1).'" viewBox="'.htmlspecialchars($box, ENT_XML1).'"';

        foreach (self::ROOT_ATTRIBUTES as $attribute) {
            if ($root->hasAttribute($attribute)) {
                $attributes .= ' '.$attribute.'="'.htmlspecialchars($root->getAttribute($attribute), ENT_XML1).'"';
            }
        }

        $content = '';

        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $content .= (string) $root->ownerDocument?->saveXML($child);
            }
        }

        // Every child carries the namespace it inherited; inside the sprite it is the default one.
        $content = str_replace(' xmlns="http://www.w3.org/2000/svg"', '', $content);

        return "<symbol {$attributes}>{$content}</symbol>";
    }

    /**
     * What is wrong with an icon file, for `webx:theme:check`: a name a template cannot write, a
     * file that is not an SVG or has no viewBox, a paint that does not follow the text, and what
     * the sprite drops — a script, a stylesheet, an event handler.
     *
     * @return list<string>
     */
    public static function problems(string $name, string $svg): array
    {
        $problems = [];

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $problems[] = "the name \"{$name}\" is not lowercase letters, digits and dashes — <x-theme::icon> cannot ask for it, and the sprite leaves it out";
        }

        $root = self::root($svg);

        if ($root === null) {
            return [...$problems, 'not an SVG document — the sprite leaves it out'];
        }

        if (self::viewBox($root) === null) {
            $problems[] = 'no viewBox, nor a width and height to make one of — the icon cannot be scaled, and the sprite leaves it out';
        }

        $paints = [];
        $dropped = [];

        foreach ([$root, ...self::descendants($root)] as $element) {
            if (in_array($element->localName, ['script', 'style', 'foreignObject'], true)) {
                $dropped['<'.$element->localName.'>'] = true;
            }

            foreach ($element->attributes ?? [] as $attribute) {
                if (! $attribute instanceof DOMAttr) {
                    continue;
                }

                $value = trim($attribute->value);

                if (str_starts_with(strtolower($attribute->name), 'on')) {
                    $dropped[$attribute->name] = true;
                } elseif (in_array($attribute->name, ['fill', 'stroke', 'color', 'stop-color'], true) && self::literal($value)) {
                    $paints[$attribute->name.'="'.$value.'"'] = true;
                } elseif ($attribute->name === 'style' && preg_match_all('/(?:^|;)\s*(fill|stroke|color|stop-color)\s*:\s*([^;]+)/i', $value, $found, PREG_SET_ORDER) > 0) {
                    foreach ($found as [, $property, $paint]) {
                        if (self::literal(trim($paint))) {
                            $paints[strtolower($property).': '.trim($paint)] = true;
                        }
                    }
                }
            }
        }

        foreach (array_keys($paints) as $paint) {
            $problems[] = "literal colour {$paint} — the icon does not take the colour of the text; write currentColor";
        }

        foreach (array_keys($dropped) as $what) {
            $problems[] = "{$what} — the sprite drops it; an icon is shapes only";
        }

        return $problems;
    }

    private static function literal(string $paint): bool
    {
        return ! in_array(strtolower($paint), self::FOLLOWING, true) && ! str_starts_with($paint, 'url(');
    }

    private static function root(string $svg): ?DOMElement
    {
        if (trim($svg) === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $loaded ? $document->documentElement : null;

        return $root instanceof DOMElement && $root->localName === 'svg' ? $root : null;
    }

    /** The root's viewBox, or one made of a plain width and height. */
    private static function viewBox(DOMElement $root): ?string
    {
        $box = trim($root->getAttribute('viewBox'));

        if (preg_match('/^-?[\d.]+(?:[\s,]+-?[\d.]+){3}$/', $box) === 1) {
            return (string) preg_replace('/[\s,]+/', ' ', $box);
        }

        $width = trim($root->getAttribute('width'));
        $height = trim($root->getAttribute('height'));

        if (preg_match('/^[\d.]+(?:px)?$/', $width) === 1 && preg_match('/^[\d.]+(?:px)?$/', $height) === 1) {
            return '0 0 '.(float) $width.' '.(float) $height;
        }

        return null;
    }

    /** Drops what an icon has no business carrying into every page: scripts, styles, handlers. */
    private static function clean(DOMElement $root): void
    {
        foreach (self::descendants($root) as $element) {
            if (in_array($element->localName, ['script', 'style', 'foreignObject'], true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                if ($attribute instanceof DOMAttr && str_starts_with(strtolower($attribute->name), 'on')) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
    }

    /**
     * @return list<DOMElement>
     */
    private static function descendants(DOMNode $node): array
    {
        $found = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $found[] = $child;
                array_push($found, ...self::descendants($child));
            }
        }

        return $found;
    }

    /** Where the sprite goes: the same scheme as a packaged layer, under `public/themes/icons`. */
    private function layer(): ThemeManifest
    {
        return new ThemeManifest(self::NAME, '', false);
    }
}
