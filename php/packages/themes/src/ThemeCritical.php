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
 * is fetched at the lowest priority, without a preload (`media="print"`), and all of them are
 * applied together, in document order, once the last one arrives, with a `<noscript>` link for
 * a browser without scripts ({@see defer()}). Without the file nothing changes: the page links
 * its stylesheets as it always did.
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

    /** The attribute of a deferred stylesheet: what the script below switches on, all at once. */
    public const string DEFERRED = 'data-webx-deferred';

    /**
     * The script printed right before the first deferred stylesheet. Each sheet calls it once,
     * loaded or failed; the last one switches every deferred sheet to `media="all"` in one go, so
     * the page goes from the critical CSS straight to the full cascade, in document order. One
     * sheet on its own would apply over the critical rules while a later one — the site's theme —
     * is still on its way, and its rule would beat the theme's for a while (a header pushing the
     * first screen down, then a carousel jumping to its height: CLS up to 1). `%d` — how many.
     */
    public const string SCRIPT = 'webxCss=function(l){l.onload=l.onerror=null;if(++webxCss.k<webxCss.n)return;'
        ."var a=document.querySelectorAll('link[data-webx-deferred]');for(var i=0;i<a.length;i++)a[i].media='all'};"
        .'webxCss.k=0;webxCss.n=%d';

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
     *     <script>webxCss=…;webxCss.n=3</script>           (once, before the first one)
     *     <link rel="stylesheet" href="…" data-webx-deferred media="print"
     *           onload="webxCss(this)" onerror="webxCss(this)">
     *     <noscript><link rel="stylesheet" href="…"></noscript>
     *
     * None of them applies on its own: when the last one has loaded (or failed), all of them
     * switch to `media="all"` in the same task ({@see SCRIPT}). Until then the page is exactly
     * the critical CSS, then the whole cascade in document order — never a part of it.
     *
     * No `<link rel="preload" as="style">` for them, and one already in the head (Vite prints
     * its own) is dropped: a preload is fetched at the highest priority and shares the channel
     * with the document, the fonts and the picture of the first screen — the very things the
     * critical CSS is there to hurry. A `media="print"` sheet is fetched at the lowest priority,
     * still at once, and `fetchpriority="low"` on a preload only gets it down to "High", the
     * priority of the pictures (measured, §13.3).
     *
     * A link that already has a `media` (a widget held for later, a print sheet) and the links
     * inside a `<noscript>` are left as they are, and so is a preload of anything else. Only the
     * head: a stylesheet in the body is the page's own business.
     */
    public static function defer(string $html): string
    {
        $end = stripos($html, '</head>');

        if ($end === false) {
            return $html;
        }

        $head = substr($html, 0, $end);
        $deferred = [];
        $count = 0;

        preg_match_all('~<noscript\b.*?</noscript>|<link\b[^>]*>~is', $head, $tags);

        foreach ($tags[0] as $tag) {
            if (self::deferrable($tag)) {
                $deferred[(string) self::attribute($tag, 'href')] = true;
                $count++;
            }
        }

        if ($deferred === []) {
            return $html;
        }

        $first = true;
        $script = '<script>'.sprintf(self::SCRIPT, $count).'</script>';

        $head = (string) preg_replace_callback('~<noscript\b.*?</noscript>|<link\b[^>]*>~is', static function (array $match) use ($deferred, $script, &$first): string {
            $tag = $match[0];

            if (stripos($tag, '<noscript') === 0) {
                return $tag;
            }

            if (strtolower((string) self::attribute($tag, 'rel')) === 'preload'
                && strtolower((string) self::attribute($tag, 'as')) === 'style'
                && isset($deferred[(string) self::attribute($tag, 'href')])) {
                return '';
            }

            if (! self::deferrable($tag)) {
                return $tag;
            }

            $lead = $first ? $script : '';
            $first = false;

            return $lead.(string) preg_replace('~\s*/?>$~', ' '.self::DEFERRED.' media="print" onload="webxCss(this)" onerror="webxCss(this)">', $tag).'<noscript>'.$tag.'</noscript>';
        }, $head);

        return $head.substr($html, $end);
    }

    /** A blocking stylesheet of the head: `rel="stylesheet"`, an `href`, no `media` of its own. */
    private static function deferrable(string $tag): bool
    {
        return stripos($tag, '<link') === 0
            && strtolower((string) self::attribute($tag, 'rel')) === 'stylesheet'
            && self::attribute($tag, 'media') === null
            && self::attribute($tag, 'href') !== null;
    }

    private static function attribute(string $tag, string $name): ?string
    {
        if (preg_match('~\s'.$name.'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $match) !== 1) {
            return null;
        }

        return html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? '').($match[3] ?? ''), ENT_QUOTES | ENT_HTML5);
    }
}
