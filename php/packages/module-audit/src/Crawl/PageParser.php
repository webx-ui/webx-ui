<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use Dom\Element;
use Dom\HTMLDocument;
use WebxUi\Audit\Hosts\UrlFinder;
use WebxUi\Audit\Runs\AuditLink;

/**
 * One HTML page read once (decision 1): the `<head>`, the headings, the text, and every address
 * the page leads to — links, pictures, scripts, styles, forms, social tags and structured data.
 *
 * `Dom\HTMLDocument` is PHP's HTML5 parser — the same tree a browser builds, with `<p>` closed
 * where a browser closes it — so what is counted is what a visitor's browser sees, not what a
 * forgiving XML reader guessed.
 */
final class PageParser
{
    /** The most addresses kept of one page: a mega-menu is a few hundred, a spam page is not. */
    /** How many elements a finding quotes, and how much of each. */
    private const EXCERPTS = 5;

    private const EXCERPT_LENGTH = 200;

    private const LINKS = 2000;

    /** The most H1 texts kept. */
    private const H1 = 10;

    /** Characters of a JSON-LD block kept for the card — enough to see what is wrong. */
    private const JSON_LD_SOURCE = 4000;

    public function __construct(private readonly UrlFinder $finder) {}

    /**
     * @param  string|null  $charset  From the `Content-Type` header, when it names one.
     */
    public function parse(string $html, string $url, ?string $charset = null): ParsedPage
    {
        $document = HTMLDocument::createFromString(
            trim($html) === '' ? '<!doctype html><html></html>' : $html,
            LIBXML_NOERROR | \Dom\HTML_NO_DEFAULT_NS,
            $charset !== null && $charset !== '' ? $charset : null,
        );

        $base = $url;

        foreach ($document->querySelectorAll('base[href]') as $element) {
            $base = Urls::resolve($url, (string) $element->getAttribute('href')) ?? $url;

            break;
        }

        $links = new LinkList($base, self::LINKS);
        $facts = [];

        $titles = array_values(array_filter(
            iterator_to_array($document->querySelectorAll('title'), false),
            static fn (Element $title): bool => $title->closest('svg') === null,
        ));
        $facts['titles'] = count($titles);

        $meta = $this->meta($document, $links);
        $head = $this->links($document, $links);

        $facts['viewport'] = isset($meta['viewport']);
        $facts['favicon'] = $head['favicon'];
        $facts['canonicals'] = $head['canonicals'];

        [$headings, $h1, $skipped] = $this->headings($document);
        $facts['headings_skipped'] = $skipped;

        $facts['links_empty'] = $this->anchors($document, $links);
        $facts += $this->images($document, $links);
        $facts += $this->controls($document);

        foreach ($document->querySelectorAll('script[src]') as $element) {
            $links->add((string) $element->getAttribute('src'), AuditLink::SCRIPT);
        }

        $iframesUntitled = 0;

        foreach ($document->querySelectorAll('iframe') as $element) {
            if ($element->hasAttribute('src')) {
                $links->add((string) $element->getAttribute('src'), AuditLink::IFRAME);
            }

            if (self::clean($element->getAttribute('title')) === '' && self::clean($element->getAttribute('aria-label')) === '') {
                $iframesUntitled++;
            }
        }

        $facts['iframes_untitled'] = $iframesUntitled;

        foreach ($document->querySelectorAll('form[action]') as $element) {
            $links->add((string) $element->getAttribute('action'), AuditLink::FORM);
        }

        $this->styles($document, $links);
        $jsonLd = $this->jsonLd($document, $links);

        $text = $this->text($document);
        $words = $text === '' ? 0 : (int) preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text);
        $facts['text_ratio'] = strlen($html) === 0 ? 0.0 : round(100 * strlen($text) / strlen($html), 1);

        $canonical = $head['canonicals'][0] ?? null;

        return new ParsedPage([
            'title' => $titles === [] ? null : self::cut(self::clean($titles[0]->textContent), 1000),
            'description' => isset($meta['description']) ? self::cut($meta['description'], 1000) : null,
            'h1' => $h1,
            'headings' => $headings,
            'canonical' => $canonical === null ? null : self::cut(Urls::resolve($base, $canonical) ?? $canonical, 2048),
            'robots_meta' => isset($meta['robots']) ? self::cut($meta['robots'], 255) : null,
            'lang' => self::cut(self::clean($document->documentElement?->getAttribute('lang')), 32) ?: null,
            'hreflang' => $head['hreflang'],
            'og' => $meta['og'],
            'twitter' => $meta['twitter'],
            'json_ld' => $jsonLd,
            'word_count' => $words,
            'text_hash' => $words === 0 ? null : sha1(mb_strtolower($text)),
            'images' => $facts['images'],
            'images_without_alt' => $facts['images_without_alt'],
        ], $facts, $links->all());
    }

    /**
     * The meta tags that matter: description, robots, viewport, Open Graph and Twitter — and the
     * addresses among them.
     *
     * @return array{og: array<string, string>, twitter: array<string, string>, description?: string, robots?: string, viewport?: string}
     */
    private function meta(HTMLDocument $document, LinkList $links): array
    {
        $found = ['og' => [], 'twitter' => []];

        foreach ($document->querySelectorAll('meta') as $element) {
            $name = strtolower(trim((string) ($element->getAttribute('property') ?? $element->getAttribute('name') ?? '')));
            $content = self::clean($element->getAttribute('content'));

            if ($name === '') {
                continue;
            }

            if (in_array($name, ['description', 'robots', 'viewport'], true)) {
                $found[$name] ??= $content;
            } elseif (str_starts_with($name, 'og:')) {
                $found['og'][substr($name, 3)] ??= self::cut($content, 1000);
            } elseif (str_starts_with($name, 'twitter:')) {
                $found['twitter'][substr($name, 8)] ??= self::cut($content, 1000);
            }

            if (in_array($name, ['og:image', 'og:url', 'twitter:image'], true) && $content !== '') {
                $links->add($content, AuditLink::META, rel: $name);
            }
        }

        return $found;
    }

    /**
     * `<link>`: canonical, hreflang, the icon — and every one as an address.
     *
     * @return array{canonicals: list<string>, hreflang: list<array{lang: string, url: string}>, favicon: string|null}
     */
    private function links(HTMLDocument $document, LinkList $links): array
    {
        $canonicals = [];
        $hreflang = [];
        $favicon = null;

        foreach ($document->querySelectorAll('link[href]') as $element) {
            $href = (string) $element->getAttribute('href');
            $rel = strtolower(self::clean($element->getAttribute('rel')));
            $tokens = preg_split('/\s+/', $rel) ?: [];

            if (in_array('canonical', $tokens, true)) {
                $canonicals[] = trim($href);
            }

            if (in_array('alternate', $tokens, true) && $element->hasAttribute('hreflang')) {
                $absolute = $links->resolve($href);

                if ($absolute !== null) {
                    $hreflang[] = ['lang' => self::cut(self::clean($element->getAttribute('hreflang')), 32), 'url' => $absolute];
                }
            }

            if (in_array('icon', $tokens, true) || in_array('apple-touch-icon', $tokens, true)) {
                $favicon ??= $links->resolve($href);
            }

            $links->add($href, AuditLink::LINK, rel: $rel === '' ? null : $rel);
        }

        return ['canonicals' => $canonicals, 'hreflang' => $hreflang, 'favicon' => $favicon];
    }

    /**
     * Counts by level, the H1 texts, and the first level skipped on the way down.
     *
     * @return array{0: array<string, int>, 1: list<string>, 2: string|null}
     */
    private function headings(HTMLDocument $document): array
    {
        $counts = ['h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0];
        $h1 = [];
        $previous = 0;
        $skipped = null;

        foreach ($document->querySelectorAll('h1, h2, h3, h4, h5, h6') as $element) {
            $tag = strtolower($element->localName);
            $level = (int) substr($tag, 1);
            $counts[$tag]++;

            if ($level === 1 && count($h1) < self::H1) {
                $h1[] = self::cut(self::clean($element->textContent), 500);
            }

            if ($skipped === null && $previous > 0 && $level > $previous + 1) {
                $skipped = 'H'.$previous.' → H'.$level;
            }

            $previous = $level;
        }

        return [$counts, $h1, $skipped];
    }

    /**
     * Links, with what a screen reader would call them — and how many have no name at all.
     *
     * @return array{text: int, images: int}
     */
    private function anchors(HTMLDocument $document, LinkList $links): array
    {
        $empty = ['text' => 0, 'images' => 0];

        foreach ($document->querySelectorAll('a[href], area[href]') as $element) {
            $name = self::name($element);

            if ($name === '' && $element->localName === 'a') {
                $element->querySelector('img') !== null ? $empty['images']++ : $empty['text']++;
            }

            $links->add(
                (string) $element->getAttribute('href'),
                AuditLink::A,
                anchor: self::cut($name, 255) ?: null,
                rel: self::cut(strtolower(self::clean($element->getAttribute('rel'))), 64) ?: null,
                target: self::cut(self::clean($element->getAttribute('target')), 32) ?: null,
            );
        }

        return $empty;
    }

    /**
     * @return array{images: int, images_without_alt: int, images_without_size: int, images_without_alt_markup: list<string>}
     */
    private function images(HTMLDocument $document, LinkList $links): array
    {
        $count = 0;
        $withoutAlt = 0;
        $withoutSize = 0;
        $markup = [];

        foreach ($document->querySelectorAll('img') as $element) {
            $count++;
            if (! $element->hasAttribute('alt')) {
                $withoutAlt++;
                self::excerpt($document, $element, $markup);
            }

            $withoutSize += $element->hasAttribute('width') && $element->hasAttribute('height') ? 0 : 1;

            if ($element->hasAttribute('src')) {
                // A JPEG inside a <picture> that offers WebP or AVIF is the fallback for old
                // browsers, not the format visitors get — `images.format` leaves it alone.
                $links->add(
                    (string) $element->getAttribute('src'),
                    AuditLink::IMG,
                    anchor: self::cut(self::clean($element->getAttribute('alt')), 255) ?: null,
                    rel: self::modern($element) ? AuditLink::MODERN : null,
                );
            }
        }

        foreach ($document->querySelectorAll('img[srcset], source[srcset]') as $element) {
            foreach (explode(',', (string) $element->getAttribute('srcset')) as $candidate) {
                $address = strtok(trim($candidate), " \t\n");

                if ($address !== false) {
                    $links->add($address, AuditLink::SRCSET);
                }
            }
        }

        return ['images' => $count, 'images_without_alt' => $withoutAlt, 'images_without_size' => $withoutSize, 'images_without_alt_markup' => $markup];
    }

    /** Whether the picture's `<picture>` has a WebP or AVIF source. */
    private static function modern(Element $image): bool
    {
        $picture = $image->closest('picture');

        if ($picture === null) {
            return false;
        }

        foreach ($picture->querySelectorAll('source') as $source) {
            $type = strtolower((string) $source->getAttribute('type'));
            $srcset = strtolower((string) $source->getAttribute('srcset'));

            if (str_contains($type, 'webp') || str_contains($type, 'avif') || preg_match('~\.(webp|avif)(\?|\s|,|$)~', $srcset) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Buttons without a name and fields without a label (§5.5, `a11y.*`).
     *
     * @return array{buttons_unnamed: int, fields_unlabeled: int, buttons_unnamed_markup: list<string>, fields_unlabeled_markup: list<string>}
     */
    private function controls(HTMLDocument $document): array
    {
        $buttons = 0;
        $buttonsMarkup = [];

        foreach ($document->querySelectorAll('button, input[type="button"], input[type="image"]') as $element) {
            $name = $element->localName === 'input'
                ? self::clean($element->getAttribute('value') ?? $element->getAttribute('alt'))
                : self::name($element);

            if ($name === '' && self::clean($element->getAttribute('aria-label') ?? $element->getAttribute('title')) === '' && ! $element->hasAttribute('aria-labelledby')) {
                $buttons++;
                self::excerpt($document, $element, $buttonsMarkup);
            }
        }

        $labelled = [];

        foreach ($document->querySelectorAll('label[for]') as $label) {
            $labelled[(string) $label->getAttribute('for')] = true;
        }

        $fields = 0;
        $fieldsMarkup = [];

        foreach ($document->querySelectorAll('input, select, textarea') as $element) {
            $type = strtolower((string) ($element->getAttribute('type') ?? 'text'));

            if ($element->localName === 'input' && in_array($type, ['hidden', 'submit', 'button', 'reset', 'image'], true)) {
                continue;
            }

            $named = self::clean($element->getAttribute('aria-label')) !== ''
                || $element->hasAttribute('aria-labelledby')
                || self::clean($element->getAttribute('title')) !== ''
                || $element->closest('label') !== null
                || isset($labelled[(string) $element->getAttribute('id')]);

            if (! $named) {
                $fields++;
                self::excerpt($document, $element, $fieldsMarkup);
            }
        }

        return [
            'buttons_unnamed' => $buttons,
            'fields_unlabeled' => $fields,
            'buttons_unnamed_markup' => $buttonsMarkup,
            'fields_unlabeled_markup' => $fieldsMarkup,
        ];
    }

    /** `url()` in `style` attributes and `<style>` blocks. */
    private function styles(HTMLDocument $document, LinkList $links): void
    {
        $css = [];

        foreach ($document->querySelectorAll('[style]') as $element) {
            $css[] = (string) $element->getAttribute('style');
        }

        foreach ($document->querySelectorAll('style') as $element) {
            $css[] = $element->textContent;
        }

        if (preg_match_all('~url\(\s*(["\']?)([^"\')]+)\1\s*\)~i', implode("\n", $css), $matches) > 0) {
            foreach ($matches[2] as $address) {
                $links->add($address, AuditLink::STYLE);
            }
        }
    }

    /**
     * The JSON-LD blocks: their types, whether they parse at all, what the types search engines
     * show lack ({@see JsonLdRules}), and the source, cut, for the card's structured-data tab.
     * The addresses inside are classified too: a stand's address in the structured data is as
     * wrong as one in a link.
     *
     * @return list<array{types: list<string>, error: string|null, items: list<array{type: string, missing: list<string>, recommended: list<string>}>, source: string}>
     */
    private function jsonLd(HTMLDocument $document, LinkList $links): array
    {
        $blocks = [];

        foreach ($document->querySelectorAll('script[type="application/ld+json"]') as $element) {
            $source = trim($element->textContent);
            $data = json_decode($source, true);

            $kept = self::cut($source, self::JSON_LD_SOURCE);

            if (! is_array($data)) {
                $blocks[] = ['types' => [], 'error' => json_last_error() === JSON_ERROR_NONE ? 'Not an object.' : json_last_error_msg(), 'items' => [], 'source' => $kept];

                continue;
            }

            $blocks[] = ['types' => self::types($data), 'error' => null, 'items' => JsonLdRules::inspect($data), 'source' => $kept];

            foreach ($this->finder->find($source) as $address) {
                $links->add($address, AuditLink::JSON_LD);
            }
        }

        return $blocks;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private static function types(array $data): array
    {
        $items = array_is_list($data) ? $data : (isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : [$data]);
        $types = [];

        foreach ($items as $item) {
            foreach ((array) (is_array($item) ? ($item['@type'] ?? []) : []) as $type) {
                if (is_string($type)) {
                    $types[$type] = true;
                }
            }
        }

        return array_keys($types);
    }

    /** The visible text of the body: no scripts, styles, templates or drawings. */
    private function text(HTMLDocument $document): string
    {
        foreach (iterator_to_array($document->querySelectorAll('script, style, noscript, template, svg'), false) as $element) {
            $element->remove();
        }

        // Not `$document->body`: that looks for <body> in the HTML namespace, and without the
        // default namespace (which keeps the CSS selectors plain) it finds nothing.
        return self::clean($document->querySelector('body')?->textContent);
    }

    /** What a link or a button is called: its text, its label, or the `alt` of its picture. */
    private static function name(Element $element): string
    {
        $text = self::clean($element->textContent);

        if ($text !== '') {
            return $text;
        }

        $label = self::clean($element->getAttribute('aria-label') ?? $element->getAttribute('title'));

        if ($label !== '') {
            return $label;
        }

        foreach ($element->querySelectorAll('img[alt]') as $image) {
            $alt = self::clean($image->getAttribute('alt'));

            if ($alt !== '') {
                return $alt;
            }
        }

        return '';
    }

    private static function clean(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    /**
     * The element as the page wrote it, cut short — "where exactly" for a finding that counts
     * elements (§12, decided: no stored HTML, an excerpt in the finding instead). A few per page
     * are enough to find the template that makes them.
     *
     * @param  list<string>  $into
     */
    private static function excerpt(HTMLDocument $document, Element $element, array &$into): void
    {
        if (count($into) < self::EXCERPTS) {
            // Parsed without the HTML namespace, a void element comes back with a closing tag the
            // page never wrote: `<img src="…"></img>`.
            $html = (string) preg_replace('~></(?:area|base|br|col|embed|hr|img|input|link|meta|source|track|wbr)>~i', '>', $document->saveHtml($element));
            $into[] = self::cut(self::clean($html), self::EXCERPT_LENGTH);
        }
    }

    private static function cut(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
