<?php

declare(strict_types=1);

namespace WebxUi\Media\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use Rhukster\DomSanitizer\DOMSanitizer;

/**
 * An SVG made safe to keep, or a refusal.
 *
 * An SVG is a document, not a picture: opened by its address it runs whatever `<script>`, `on*`
 * handler or `javascript:` link it carries, on the site's own origin. So every SVG that enters
 * the library goes through here first.
 *
 * Two passes. The first is `rhukster/dom-sanitizer` — MIT, no dependencies beyond ext-dom, its
 * lists taken from DOMPurify and kept up with published bypasses: elements and attributes outside
 * an allow-list go, and so do `javascript:` and script-capable `data:` addresses and CSS that
 * reaches outside. The second is this module's own and stricter, because a picture in a library
 * has no business pointing anywhere: every `href` is a fragment of the same document (`#id`),
 * except an `<image>` holding an inline raster, and no attribute is a handler. `<use>` is let back
 * in for that reason — the library drops it wholesale, which breaks every icon sprite, and a `use`
 * that can only point inside its own document is harmless.
 *
 * What does not parse as XML with an `<svg>` root is refused rather than repaired: a sanitizer
 * that guesses at a broken document is one browsers can disagree with.
 */
final class SvgSanitizer
{
    /** An inline picture an `<image>` may carry: rasters only, nothing that can hold a document. */
    private const INLINE_RASTER = '~^data:image/(?:png|jpe?g|gif|webp);~i';

    public function sanitize(string $svg): ?SanitizedSvg
    {
        $before = $this->parse($svg);

        if ($before === null) {
            return null;
        }

        $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);
        $sanitizer->setDisallowedTags(array_values(array_diff($sanitizer->getDisallowedTags(), ['use'])));
        $sanitizer->addAllowedTags(['use']);

        $after = $this->parse($sanitizer->sanitize($svg, ['compress-output' => false]));

        if ($after === null) {
            return null;
        }

        $this->confine($after);

        $removed = array_values(array_diff_key(
            $this->inventory($before),
            $this->inventory($after),
        ));

        return new SanitizedSvg((string) $after->saveXML($after->documentElement), $removed);
    }

    /**
     * Whether these bytes are an SVG this class can make safe — what the upload rule asks before
     * the file is accepted, so the refusal comes as a validation message.
     */
    public function accepts(string $svg): bool
    {
        return $this->sanitize($svg) !== null;
    }

    private function parse(string $svg): ?DOMDocument
    {
        if (trim($svg) === '' || preg_match('/<!ENTITY/i', $svg) === 1) {
            // Entities are how an XML file grows a billion times on parsing; no picture needs one.
            return null;
        }

        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($svg, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internal);
        }

        $root = $document->documentElement;

        if (! $loaded || $root === null || strtolower($root->localName) !== 'svg') {
            return null;
        }

        return $document;
    }

    /** The second pass: handlers, scripts and addresses that reach outside the document. */
    private function confine(DOMDocument $document): void
    {
        /** @var list<DOMElement> $elements */
        $elements = iterator_to_array($document->getElementsByTagName('*'), false);

        foreach ($elements as $element) {
            if (in_array(strtolower($element->localName), ['script', 'foreignobject', 'iframe', 'embed', 'object'], true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            /** @var list<DOMAttr> $attributes */
            $attributes = iterator_to_array($element->attributes ?? [], false);

            foreach ($attributes as $attribute) {
                if (! $this->allowed($element, $attribute)) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
    }

    private function allowed(DOMElement $element, DOMAttr $attribute): bool
    {
        $name = strtolower($attribute->localName);
        $value = (string) preg_replace('/[\x00-\x20]+/', '', $attribute->value);

        if (str_starts_with($name, 'on') || stripos($value, 'javascript:') !== false) {
            return false;
        }

        if ($name !== 'href') {
            return true;
        }

        return str_starts_with($value, '#')
            || (strtolower($element->localName) === 'image' && preg_match(self::INLINE_RASTER, $value) === 1);
    }

    /**
     * Every element and attribute of a document as a key, so two of them can be compared:
     * `<script>`, `<a href>`. Counted, so the second of two handlers going is not lost.
     *
     * @return array<string, string>
     */
    private function inventory(DOMDocument $document): array
    {
        $seen = [];
        $inventory = [];

        foreach ($document->getElementsByTagName('*') as $element) {
            $tag = strtolower($element->nodeName);
            $this->count($inventory, $seen, "<{$tag}>");

            foreach ($element->attributes ?? [] as $attribute) {
                $this->count($inventory, $seen, "<{$tag} ".strtolower($attribute->nodeName).'>');
            }
        }

        return $inventory;
    }

    /**
     * @param  array<string, string>  $inventory
     * @param  array<string, int>  $seen
     */
    private function count(array &$inventory, array &$seen, string $what): void
    {
        $seen[$what] = ($seen[$what] ?? 0) + 1;
        $inventory[$what.'#'.$seen[$what]] = $what;
    }
}
