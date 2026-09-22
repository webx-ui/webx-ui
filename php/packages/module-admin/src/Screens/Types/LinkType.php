<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-link`: somewhere to go, chosen rather than typed.
 *
 * What is stored is {@see Link} — the kind of target, the morph pair or the path, and the two
 * things about how to open it. What the site reads is that with the address worked out, because
 * an address is never the record: an entity's moves with it, and a path takes the language prefix
 * of whoever is reading.
 *
 * Not localized, and it says so rather than half-working. A link per language is already what
 * this is — the entity behind it has an address in every language, and a hand-written path gets
 * the prefix of the one being read — so a language map here would be four copies of one decision,
 * three of them forgotten.
 */
final class LinkType implements FieldType
{
    /** As long as the menu's column, which is what the same value is kept in there. */
    public const MAX_URL = 1024;

    /** An anchor is the id of something on a page; anything near this is not one. */
    public const MAX_HASH = 190;

    public function __construct(
        private readonly LinkSources $sources,
        private readonly LinkUrls $urls,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        if (($node['localized'] ?? false) === true) {
            return [static function (string $attribute, mixed $value, Closure $fail): void {
                $fail((string) __('webx-admin::links.not-localized'));
            }];
        }

        return ['nullable', Link::SHAPE, function (string $attribute, mixed $value, Closure $fail): void {
            $problem = $this->problem($value);

            if ($problem !== null) {
                $fail($problem);
            }
        }];
    }

    /**
     * What is kept: the link, normalised — and nothing at all when none was chosen.
     *
     * An empty field is `null` rather than `{"target":"none"}`, so "did anybody put a link here"
     * stays a check instead of a parse. A heading that deliberately goes nowhere is a menu item
     * and not a field, and the menu keeps its own columns.
     *
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $link = Link::fromArray($value);

        return $link->isEmpty() ? null : $link->toArray();
    }

    /**
     * What a template reads: everything it needs to print the link and nothing it would have to
     * work out for itself.
     *
     * `label` is the entity's own name, for a button whose caption was never written; `available`
     * is false for an entity that is not on the site now, which is the one thing a template has
     * to check before printing a link to a draft. The morph pair stays in the answer because a
     * template sometimes cares what it is pointing at — a current-page class, a different icon.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        if (! is_array($stored)) {
            return null;
        }

        $link = Link::fromArray($stored);

        if ($link->isEmpty()) {
            return null;
        }

        $candidate = $this->urls->candidate($link, $locale);

        return [
            'target' => $link->target->value,
            'entity_type' => $link->entityType,
            'entity_id' => $link->entityId,
            'url' => $this->urls->href($link, $locale),
            'hash' => $link->hash,
            'label' => $candidate?->title,
            // An entity nothing answers for is gone; a hand-written address is always there to
            // try, whatever is at the other end of it.
            'available' => $link->target !== LinkTarget::Entity || ($candidate !== null && $candidate->available),
            'new_tab' => $link->newTab,
            'rel' => $link->relAttribute(),
        ];
    }

    /** What is wrong with the value, in words — null when nothing is. */
    private function problem(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        if (! is_array($value)) {
            return (string) __('webx-admin::links.shape');
        }

        $target = LinkTarget::tryFrom((string) ($value['target'] ?? ''));

        if ($target === null) {
            return (string) __('webx-admin::links.target');
        }

        $anchor = $this->hashProblem($value);

        if ($anchor !== null) {
            return $anchor;
        }

        return match ($target) {
            LinkTarget::Entity => $this->entityProblem($value),
            LinkTarget::Url => $this->urlProblem($value),
            LinkTarget::None => null,
        };
    }

    /**
     * An anchor is a name on the page, not an address: no spaces, no second `#`, and short.
     *
     * Checked rather than quietly cleaned, because a fragment with a space in it is a link that
     * silently goes to the top of the page instead of where it says — which is the kind of thing
     * nobody reports and everybody works around.
     *
     * @param  array<string, mixed>  $value
     */
    private function hashProblem(array $value): ?string
    {
        $hash = $value['hash'] ?? null;

        if ($hash === null || (is_string($hash) && trim($hash) === '')) {
            return null;
        }

        if (! is_string($hash) || mb_strlen($hash) > self::MAX_HASH) {
            return (string) __('webx-admin::links.hash-length', ['max' => self::MAX_HASH]);
        }

        $hash = ltrim(trim($hash), '#');

        return preg_match('/^[^\s#]+$/u', $hash) === 1 ? null : (string) __('webx-admin::links.hash-shape');
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function entityProblem(array $value): ?string
    {
        $type = $value['entity_type'] ?? null;
        $id = $value['entity_id'] ?? null;

        // An unfinished choice is not an error: the field stores nothing and the form saves.
        if ($type === null && $id === null) {
            return null;
        }

        // A type nobody registered is a link to something this panel cannot ask about — which is
        // how a stale screen, or a hand-written API call, points at a module that is gone.
        if (! is_string($type) || $this->sources->find($type) === null) {
            return (string) __('webx-admin::links.no-source');
        }

        return is_numeric($id) && (int) $id > 0 ? null : (string) __('webx-admin::links.no-entity');
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function urlProblem(array $value): ?string
    {
        $url = $value['url'] ?? null;

        if ($url === null || (is_string($url) && trim($url) === '')) {
            return null;
        }

        if (! is_string($url) || mb_strlen($url) > self::MAX_URL) {
            return (string) __('webx-admin::links.url-length', ['max' => self::MAX_URL]);
        }

        return Link::isAcceptableUrl($url) ? null : (string) __('webx-admin::links.url-scheme');
    }
}
