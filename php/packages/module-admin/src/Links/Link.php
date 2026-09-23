<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

/**
 * A link, wherever one is chosen.
 *
 * One value rather than one per place that needs it: a menu item keeps it in columns, a block
 * field and a described screen keep the same thing as JSON, and both go through one parse, one
 * validation and one normalisation. The keys are spelled the way the menu's columns are, so that
 * reading a row and reading a field are the same code.
 *
 * `rel` is a set of three and not a string. A free-text `rel` is one an editor eventually writes
 * with a typo and finds out about six months later; `noopener noreferrer` is not in the set
 * because it is not a choice — whoever renders a new tab adds it (§2, decision 8).
 */
final readonly class Link
{
    /** The whole of `rel` an editor may choose from. */
    public const REL = ['nofollow', 'sponsored', 'ugc'];

    /** What the panel, a form and a stored JSON value all call these six things. */
    public const SHAPE = 'array:target,entity_type,entity_id,url,hash,new_tab,rel';

    /**
     * @param  string|null  $hash  The fragment, without its `#`.
     * @param  list<string>  $rel
     */
    public function __construct(
        public LinkTarget $target = LinkTarget::None,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?string $url = null,
        public ?string $hash = null,
        public bool $newTab = false,
        public array $rel = [],
    ) {}

    /**
     * Read a link out of whatever arrived — a form, a column, a tool call.
     *
     * Forgiving on the way in and strict on the way out: an unknown target becomes `none` and a
     * `rel` nobody recognises is dropped, so a value that was written by hand still produces a
     * link rather than an exception. What must not pass silently is checked by the rules, which
     * run first on every path the panel writes through.
     *
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        $target = LinkTarget::tryFrom((string) ($value['target'] ?? '')) ?? LinkTarget::None;
        $id = $value['entity_id'] ?? null;
        $url = $target === LinkTarget::Url ? self::text($value['url'] ?? null) : null;
        $hash = self::hash($value['hash'] ?? null);

        // An address typed with its anchor in it: the fragment is lifted out, so that there is
        // one place a fragment lives whichever kind of target this is. Otherwise `/about#team`
        // and the anchor field would both hold one, and a link would end `#team#top`.
        if ($url !== null && str_contains($url, '#')) {
            [$url, $tail] = array_pad(explode('#', $url, 2), 2, null);

            $url = self::text($url);
            $hash ??= self::hash($tail);
        }

        return new self(
            target: $target,
            entityType: $target === LinkTarget::Entity ? self::text($value['entity_type'] ?? null) : null,
            entityId: $target === LinkTarget::Entity && is_numeric($id) ? (int) $id : null,
            url: $url,
            hash: $hash,
            newTab: (bool) ($value['new_tab'] ?? false),
            rel: self::rel($value['rel'] ?? null),
        );
    }

    /**
     * @return array{target: string, entity_type: string|null, entity_id: int|null, url: string|null, hash: string|null, new_tab: bool, rel: list<string>}
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target->value,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'url' => $this->url,
            'hash' => $this->hash,
            'new_tab' => $this->newTab,
            'rel' => $this->rel,
        ];
    }

    /**
     * Nothing has been chosen — as opposed to `none`, which is a choice.
     *
     * What a cleared field sends and what a heading sends look alike in the browser and are not
     * the same record, so a caller that stores a link asks this rather than comparing targets.
     */
    public function isEmpty(): bool
    {
        return match ($this->target) {
            LinkTarget::Entity => $this->entityType === null || $this->entityId === null,
            // An anchor with no address is a link to a place on the page it is printed on,
            // which is an ordinary thing for a button in a block to be.
            LinkTarget::Url => $this->url === null && $this->hash === null,
            LinkTarget::None => true,
        };
    }

    /** The fragment as it is printed, `#` and all — or nothing when there is none. */
    public function fragment(): string
    {
        return $this->hash === null ? '' : '#'.$this->hash;
    }

    /**
     * What goes in `rel=""`, with the protection a new tab always gets.
     *
     * `noopener noreferrer` is added here rather than offered as a checkbox: a tab that can
     * reach back into the page it came from is a defect, not a preference.
     */
    public function relAttribute(): ?string
    {
        $rel = $this->rel;

        if ($this->newTab) {
            $rel = [...$rel, 'noopener', 'noreferrer'];
        }

        return $rel === [] ? null : implode(' ', array_values(array_unique($rel)));
    }

    /**
     * Is this something a template may print into an `href`?
     *
     * A path or an ordinary address, yes. `javascript:` and `data:` are the two that matter: a
     * template prints this attribute raw, so a scheme that executes turns a link field into a
     * way of running script on the site — and an editor who needed one would not be typing it
     * into a menu. Rather than a list of everything forbidden, the schemes that are allowed are
     * named, because the next one to avoid has not been invented yet.
     */
    public static function isAcceptableUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Protocol-relative, an anchor, a query, or a plain path: no scheme to judge.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) !== 1) {
            return true;
        }

        return (bool) preg_match('#^(https?|mailto|tel):#i', $url);
    }

    /**
     * A fragment, kept without its `#`.
     *
     * Without, because that is how it is written everywhere except in the one place it is
     * printed — and a stored `#team` would turn into `##team` the first time somebody appended
     * the separator themselves. A `#` typed into the field is taken off rather than refused:
     * both spellings are what an editor means.
     */
    private static function hash(mixed $value): ?string
    {
        $value = self::text($value);

        return $value === null ? null : self::text(ltrim($value, '#'));
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function rel(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rel = [];

        foreach (self::REL as $known) {
            if (in_array($known, $value, true)) {
                $rel[] = $known;
            }
        }

        return $rel;
    }
}
