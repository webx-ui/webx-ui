<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

/**
 * What a page says about itself.
 *
 * Immutable, and merged field by field rather than source by source: a rule written for one
 * address that fills in nothing but a title must not take the description away from whatever
 * stands below it. Merging whole objects is the version of this that looks like the engine is
 * broken — one rule, and half the page's markup disappears.
 *
 * @phpstan-type Og array<string, string>
 * @phpstan-type JsonLdBlock array<string, mixed>
 */
final class SeoData
{
    /**
     * @param  Og  $og  Open Graph properties without the `og:` prefix.
     * @param  list<JsonLdBlock>  $jsonLd  Blocks, each printed as its own script tag.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $h1 = null,
        public readonly ?string $description = null,
        public readonly ?string $keywords = null,
        public readonly ?string $canonical = null,
        public readonly ?string $robots = null,
        public readonly array $og = [],
        public readonly array $jsonLd = [],
    ) {}

    /**
     * From a loose array — a database row, a settings block, a test.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function make(array $fields): self
    {
        return new self(
            title: self::text($fields['title'] ?? null),
            h1: self::text($fields['h1'] ?? null),
            description: self::text($fields['description'] ?? null),
            keywords: self::text($fields['keywords'] ?? null),
            canonical: self::text($fields['canonical'] ?? null),
            robots: self::text($fields['robots'] ?? null),
            og: self::strings($fields['og'] ?? []),
            jsonLd: self::blocks($fields['jsonLd'] ?? $fields['json_ld'] ?? []),
        );
    }

    public static function empty(): self
    {
        return new self;
    }

    /**
     * This one wins where it has something to say; `$lower` fills in the rest.
     *
     * `jsonLd` is the exception and adds up: an Organization block from the defaults and a
     * FAQPage block from a rule are both true about the page at the same time.
     */
    public function mergeOver(self $lower): self
    {
        return new self(
            title: $this->title ?? $lower->title,
            h1: $this->h1 ?? $lower->h1,
            description: $this->description ?? $lower->description,
            keywords: $this->keywords ?? $lower->keywords,
            canonical: $this->canonical ?? $lower->canonical,
            robots: $this->robots ?? $lower->robots,
            og: $this->og + $lower->og,
            jsonLd: array_merge($this->jsonLd, $lower->jsonLd),
        );
    }

    /**
     * A copy with some fields replaced. Named arguments make the call read as the change.
     *
     * @param  Og|null  $og
     * @param  list<JsonLdBlock>|null  $jsonLd
     */
    public function with(
        ?string $title = null,
        ?string $h1 = null,
        ?string $description = null,
        ?string $keywords = null,
        ?string $canonical = null,
        ?string $robots = null,
        ?array $og = null,
        ?array $jsonLd = null,
    ): self {
        return new self(
            title: $title ?? $this->title,
            h1: $h1 ?? $this->h1,
            description: $description ?? $this->description,
            keywords: $keywords ?? $this->keywords,
            canonical: $canonical ?? $this->canonical,
            robots: $robots ?? $this->robots,
            og: $og ?? $this->og,
            jsonLd: $jsonLd ?? $this->jsonLd,
        );
    }

    public function isEmpty(): bool
    {
        return $this->title === null
            && $this->h1 === null
            && $this->description === null
            && $this->keywords === null
            && $this->canonical === null
            && $this->robots === null
            && $this->og === []
            && $this->jsonLd === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'h1' => $this->h1,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'og' => $this->og,
            'json_ld' => $this->jsonLd,
        ];
    }

    /** An empty string is nothing written, not a value that overrides what is below. */
    private static function text(mixed $value): ?string
    {
        if (is_array($value)) {
            return null;
        }

        $text = $value === null ? '' : trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return Og
     */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $key => $one) {
            $text = self::text($one);

            if ($text !== null) {
                $strings[(string) $key] = $text;
            }
        }

        return $strings;
    }

    /**
     * One block or a list of them, whichever the caller had. A rule holds whatever an editor
     * pasted into the JSON-LD field, and that is as often an object as a list.
     *
     * @return list<JsonLdBlock>
     */
    private static function blocks(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || $value === []) {
            return [];
        }

        if (! array_is_list($value)) {
            /** @var JsonLdBlock $value */
            return [$value];
        }

        $blocks = [];

        foreach ($value as $block) {
            if (is_array($block) && $block !== []) {
                /** @var JsonLdBlock $block */
                $blocks[] = $block;
            }
        }

        return $blocks;
    }
}
