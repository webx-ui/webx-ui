<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * An allowlist over a fragment of HTML: everything not named here is taken out.
 *
 * The editor already does most of this — ProseMirror parses what is pasted against a schema and
 * drops what the schema does not know, so a `<script>` never becomes part of the document. That
 * is a useful property and not a boundary: the same field can be POSTed to directly, without the
 * editor ever running, and an agent writing through a tool never runs it at all.
 *
 * What survives is what {@see RichTextType} can produce, plus the couple of things a person
 * pastes from another editor. Everything else — a tag, an attribute, an address in a scheme
 * this does not name — is removed rather than escaped: the point is a document that renders,
 * not a record of what somebody tried to send.
 */
final class Html
{
    /** Tags kept, with the attributes each of them may carry. */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        's' => [],
        'u' => [],
        'sub' => [],
        'sup' => [],
        'code' => [],
        'pre' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'ul' => [],
        'ol' => ['start', 'type'],
        'li' => [],
        'blockquote' => [],
        'hr' => [],
        'span' => [],
        'div' => ['data-youtube-video'],
        'figure' => [],
        'figcaption' => [],
        'a' => ['href', 'title', 'target', 'rel', self::KEY],
        'img' => ['src', 'alt', 'title', 'width', 'height', self::KEY],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'title'],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tfoot' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan', 'colwidth'],
        'td' => ['colspan', 'rowspan', 'colwidth'],
        'caption' => [],
    ];

    /**
     * The library key a picture is filed under, kept beside its address.
     *
     * The address is not the picture. It may be signed and about to expire, it carries a
     * version stamp that changes the moment somebody crops the image, and it is different on
     * every deployment of the same site — a CDN on the developer's machine, the application's
     * own disk in production. So what a document records is the key, and the address is worked
     * out again every time the document is read ({@see self::rewrite()}).
     */
    public const KEY = 'data-wx-path';

    /** Tags removed with everything inside them, rather than unwrapped. */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'template'];

    /** What an address may start with. Anything else is not an address worth keeping. */
    private const SCHEMES = ['http:', 'https:', 'mailto:', 'tel:', '/', '#', '.'];

    /**
     * The fragment, cleaned. An empty string for anything that had nothing in it.
     */
    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = self::parse($html);

        if ($document === null) {
            return '';
        }

        [$dom, $body] = $document;

        self::walk($body);

        return self::serialise($dom, $body);
    }

    /**
     * The fragment as a tree, or `null` when there is nothing to read.
     *
     * It is a fragment: no doctype, no `<html>`, and told it is UTF-8 by a declaration rather
     * than by a BOM, because libxml assumes Latin-1 otherwise and every non-Latin letter comes
     * back as mojibake. `LIBXML_NOERROR` because malformed markup is the normal case here — it
     * is being cleaned, not validated.
     *
     * @return array{DOMDocument, DOMElement}|null
     */
    private static function parse(string $html): ?array
    {
        $document = new DOMDocument;

        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"?><body>'.$html.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        if ($loaded === false) {
            return null;
        }

        $body = $document->getElementsByTagName('body')->item(0);

        return $body instanceof DOMElement ? [$document, $body] : null;
    }

    private static function serialise(DOMDocument $document, DOMElement $body): string
    {
        $html = '';

        foreach (iterator_to_array($body->childNodes) as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    /** Whether a fragment holds anything at all — `<p></p>` from an emptied editor does not. */
    public static function isEmpty(string $html): bool
    {
        // A picture, an embed or a rule is content with no words in it at all.
        if (preg_match('/<(img|iframe|hr)\b/i', $html) === 1) {
            return false;
        }

        return trim(str_replace("\u{a0}", ' ', strip_tags($html))) === '';
    }

    /**
     * The same document with every address that has a key behind it worked out afresh.
     *
     * `$lookup` is handed every key in the document at once and answers with an address each;
     * a key it does not know keeps whatever address it had, because a picture that has fallen
     * out of the library is better shown broken in one place than silently removed from the
     * page. An element with no key is not touched at all — that is what an external picture is.
     *
     * @param  callable(list<string>): array<string, string|null>  $lookup
     */
    public static function rewrite(string $html, callable $lookup): string
    {
        if (! str_contains($html, self::KEY)) {
            return $html;
        }

        $document = self::parse($html);

        if ($document === null) {
            return $html;
        }

        [$dom, $body] = $document;

        /** @var list<DOMElement> $carriers */
        $carriers = [];
        $keys = [];

        foreach (iterator_to_array($body->getElementsByTagName('*')) as $element) {
            $key = $element->getAttribute(self::KEY);

            if ($key !== '') {
                $carriers[] = $element;
                $keys[$key] = $key;
            }
        }

        $addresses = $lookup(array_values($keys));

        foreach ($carriers as $element) {
            $address = $addresses[$element->getAttribute(self::KEY)] ?? null;

            if ($address === null) {
                continue;
            }

            $element->setAttribute(strtolower($element->tagName) === 'a' ? 'href' : 'src', $address);
        }

        return self::serialise($dom, $body);
    }

    /**
     * Depth first, over a copy of the child list: the walk removes nodes as it goes, and a live
     * `DOMNodeList` reindexes underneath it — every second child would be skipped.
     */
    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                // Text and entities stay; comments and processing instructions go.
                if ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
                    $node->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true) && ! self::isYoutubeFrame($child)) {
                $node->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Unwrapped rather than dropped: an unknown wrapper around a paragraph is
                // somebody's markup, and the paragraph inside it is their text.
                self::walk($child);
                self::unwrap($child);

                continue;
            }

            self::attributes($child, $tag);
            self::walk($child);
        }
    }

    /**
     * The one frame that is kept: the YouTube embed the editor writes itself. It is recognised
     * by where it points rather than by the tag, so a frame pointing anywhere else is gone.
     */
    private static function isYoutubeFrame(DOMElement $element): bool
    {
        if (strtolower($element->tagName) !== 'iframe') {
            return false;
        }

        $host = parse_url($element->getAttribute('src'), PHP_URL_HOST);

        return is_string($host) && (bool) preg_match('/(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be)$/i', $host);
    }

    private static function attributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (($name === 'href' || $name === 'src') && ! self::isAddress($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        // A link that opens elsewhere without `rel` hands the other page a handle on this one.
        if ($tag === 'a' && $element->getAttribute('target') !== '') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isAddress(string $value): bool
    {
        $trimmed = strtolower(ltrim($value));

        foreach (self::SCHEMES as $scheme) {
            if (str_starts_with($trimmed, $scheme)) {
                return true;
            }
        }

        // A bare path — `page/two` — is an address too; anything with a colon before the first
        // slash is a scheme this list did not name, `javascript:` above all.
        $colon = strpos($trimmed, ':');
        $slash = strpos($trimmed, '/');

        return $colon === false || ($slash !== false && $slash < $colon);
    }

    /** Replaces an element with its own children, in place. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }
}
