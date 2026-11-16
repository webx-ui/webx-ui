<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Foundation\Application;

/**
 * The critical CSS of the site (spec §13.3): the rules the first screen of its pages needs,
 * which `webx:theme:critical` reads off the pages rendered in a browser and writes to one file.
 *
 * With the file there, `@webxTheme` prints it inline and every stylesheet of the head — the
 * widgets', the modules', the layers', Vite's, the blocks' — stops holding the first paint: it
 * is preloaded and applied once it arrives (`media="print"` + `onload`), with a `<noscript>`
 * link for a browser without scripts ({@see defer()}). Without the file nothing changes: the
 * page links its stylesheets as it always did.
 *
 * The file belongs to the site, not to a theme: it is made of everything the site's pages load,
 * its blocks and modules too. It lives at `webx-themes.critical` (`theme/critical.css` by
 * default) and goes into git with the theme; `webx:theme:critical --check` says whether it
 * still draws the first screen as the full CSS does.
 */
final class ThemeCritical
{
    /** The attribute of the inline `<style>`: the response is rewritten only when it is there. */
    public const string ATTRIBUTE = 'data-webx-critical';

    /** The two screens the command reads and checks, as in the prototype: a desktop and a phone. */
    public const array VIEWPORTS = [[1440, 900], [390, 844]];

    /** How far below the screen a rule still counts as the first screen's: a quarter of it. */
    public const float BELOW = 1.25;

    private ?string $css = null;

    public function __construct(private readonly Application $app) {}

    /** The file, absolute: `webx-themes.critical` read from the project root unless it is absolute. */
    public function path(): string
    {
        $path = $this->app->make('config')->get('webx-themes.critical');
        $path = is_string($path) && trim($path) !== '' ? trim($path) : 'theme/critical.css';

        return preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $path) === 1 ? $path : $this->app->basePath($path);
    }

    /** The critical CSS, or `''` while there is no file — then the head stays as it was. */
    public function css(): string
    {
        if ($this->css !== null) {
            return $this->css;
        }

        $path = $this->path();

        return $this->css = is_file($path) ? trim((string) file_get_contents($path)) : '';
    }

    /** The inline `<style>` for `@webxTheme`, or `''`. */
    public function style(): string
    {
        $css = $this->css();

        // `</style` inside a value would end the element early; a stylesheet never needs it.
        return $css === '' ? '' : '<style '.self::ATTRIBUTE.'>'.str_ireplace('</style', '<\/style', $css).'</style>';
    }

    /** Whether a page printed the critical CSS — so its stylesheets may stop blocking. */
    public static function printed(string $html): bool
    {
        return str_contains($html, '<style '.self::ATTRIBUTE.'>');
    }

    /**
     * The page with every blocking stylesheet of its `<head>` turned into one that does not block:
     *
     *     <link rel="preload" href="…" as="style">
     *     <link rel="stylesheet" href="…" media="print" onload="this.media='all'">
     *     <noscript><link rel="stylesheet" href="…"></noscript>
     *
     * A link that already has a `media` (a widget held for later, a print sheet) and the links
     * inside a `<noscript>` are left as they are. Only the head: a stylesheet in the body is the
     * page's own business.
     */
    public static function defer(string $html): string
    {
        $end = stripos($html, '</head>');

        if ($end === false) {
            return $html;
        }

        $head = substr($html, 0, $end);
        $preloaded = [];

        preg_match_all('~<link\b[^>]*\brel=["\']?preload\b[^>]*>~i', $head, $preloads);

        foreach ($preloads[0] as $tag) {
            if (($href = self::attribute($tag, 'href')) !== null) {
                $preloaded[$href] = true;
            }
        }

        $head = (string) preg_replace_callback('~<noscript\b.*?</noscript>|<link\b[^>]*>~is', static function (array $match) use (&$preloaded): string {
            $tag = $match[0];

            if (stripos($tag, '<noscript') === 0
                || strtolower((string) self::attribute($tag, 'rel')) !== 'stylesheet'
                || self::attribute($tag, 'media') !== null
                || ($href = self::attribute($tag, 'href')) === null) {
                return $tag;
            }

            $deferred = (string) preg_replace('~\s*/?>$~', ' media="print" onload="this.media=\'all\'">', $tag);
            $preload = isset($preloaded[$href]) ? '' : '<link rel="preload" href="'.e($href).'" as="style">';
            $preloaded[$href] = true;

            return $preload.$deferred.'<noscript>'.$tag.'</noscript>';
        }, $head);

        return $head.substr($html, $end);
    }

    private static function attribute(string $tag, string $name): ?string
    {
        if (preg_match('~\s'.$name.'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $match) !== 1) {
            return null;
        }

        return html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? '').($match[3] ?? ''), ENT_QUOTES | ENT_HTML5);
    }
}
