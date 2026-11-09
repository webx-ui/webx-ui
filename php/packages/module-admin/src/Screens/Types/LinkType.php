<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Admin\Screens\ChecksNewValues;
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
 *
 * A file of the library is a target only where the node asks for one with `props.files`: the
 * menu, a redirect after a form and the other places that call these rules with no node at all
 * keep the three targets they were written for.
 */
final class LinkType implements ChecksNewValues, FieldType
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

        $files = ($node['props']['files'] ?? false) === true;

        return ['nullable', Link::SHAPE, function (string $attribute, mixed $value, Closure $fail) use ($files): void {
            $problem = $this->problem($value, $files);

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
        $file = $this->urls->file($link);

        return [
            'target' => $link->target->value,
            'entity_type' => $link->entityType,
            'entity_id' => $link->entityId,
            'url' => $this->urls->href($link, $locale),
            'hash' => $link->hash,
            'path' => $link->path,
            'label' => $candidate?->title ?? $file['name'] ?? null,
            // An entity nothing answers for is gone, and so is a file the library no longer has;
            // a hand-written address is always there to try, whatever is at the other end of it.
            'available' => match ($link->target) {
                LinkTarget::Entity => $candidate !== null && $candidate->available,
                LinkTarget::File => $file !== null,
                default => true,
            },
            'new_tab' => $link->newTab,
            'rel' => $link->relAttribute(),
            // The name the file was uploaded under, for `download=""`: the key it is kept under is
            // a hash, and that is what the browser would otherwise save it as.
            'download' => $link->download && $file !== null ? $file['name'] : null,
        ];
    }

    /**
     * A file key nobody has, said only of a value being written for the first time.
     *
     * Not part of {@see rules()}: a brochure deleted from the library must not stop the page it
     * sits on from being saved again. A key that is new and unknown is one typed rather than
     * picked — what an agent sends when it guesses — and that is worth a word.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array
    {
        if (! is_array($value)) {
            return [];
        }

        $link = Link::fromArray($value);

        if ($link->target !== LinkTarget::File || $link->path === null || $this->urls->file($link) !== null) {
            return [];
        }

        return [(string) __('webx-admin::links.no-file', ['path' => $link->path])];
    }

    /** What is wrong with the value, in words — null when nothing is. */
    private function problem(mixed $value, bool $files): ?string
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
            LinkTarget::File => $files ? $this->fileProblem($value) : (string) __('webx-admin::links.files-off'),
            LinkTarget::None => null,
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function fileProblem(array $value): ?string
    {
        $path = $value['path'] ?? null;
        $download = $value['download'] ?? false;

        if (! is_bool($download) && ! in_array($download, [0, 1, '0', '1'], true)) {
            return (string) __('webx-admin::links.download');
        }

        // An unfinished choice is not an error, as with an entity: the field stores nothing.
        if ($path === null || (is_string($path) && trim($path) === '')) {
            return null;
        }

        return is_string($path) && mb_strlen($path) <= self::MAX_URL ? null : (string) __('webx-admin::links.path');
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
