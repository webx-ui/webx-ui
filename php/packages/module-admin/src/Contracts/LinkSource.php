<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Links\LinkCandidate;

/**
 * A kind of thing the panel can be asked to link to.
 *
 * The address registry answers "what is this entity's address"; it does not answer "what can I
 * link to at all" — a `RouteType` has a model, a formatter and a handler, and nowhere in it is a
 * title to show or a way to search. That second question is the panel's, which is why this lives
 * here and not in `webx-ui/routing`, where there is deliberately nothing about a panel.
 *
 * A source does its own querying. It knows which of its records an editor is allowed to see, what
 * they are called in a half-translated language, and — the part nothing else can answer — whether
 * the site would actually show one right now (§2, decision 15): a draft page has an address in
 * the registry, and a menu that trusted the registry would link to a page nobody can open.
 *
 * Modules register on boot, by the same pattern as `SeoSources` and `RouteTypes`:
 *
 *     $this->app->make(LinkSources::class)->register($this->app->make(PageLinkSource::class));
 */
interface LinkSource
{
    /** The morph alias, the same one `routes.entity_type` stores. */
    public function type(): string;

    /**
     * The model behind the alias.
     *
     * Not for querying — a source does its own — but so that a caller who depends on what this
     * source answers can subscribe to it. A menu is cached, and publishing a page changes what
     * the menu shows without touching the address registry at all.
     *
     * @return class-string<Model>
     */
    public function model(): string;

    /** What the picker calls this section — translated. */
    public function title(): string;

    public function icon(): ?string;

    /** Lower first. The picker shows the sections in this order. */
    public function order(): int;

    /** The permission that opens this section of the picker, or null for everybody. */
    public function permission(): ?string;

    /**
     * Candidates matching what somebody typed. An empty query means "the first few of these".
     *
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array;

    /**
     * The chosen entities, in one query per type.
     *
     * @param  list<int>  $ids
     * @return array<int, LinkCandidate> Keyed by id; a missing key is an entity that is gone.
     */
    public function resolve(array $ids, string $locale): array;
}
