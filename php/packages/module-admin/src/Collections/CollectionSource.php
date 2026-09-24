<?php

declare(strict_types=1);

namespace WebxUi\Admin\Collections;

/**
 * Records a module lets a page show as a block: the questions of a FAQ, the people of a team.
 *
 * The block's schema names a source (`{ "type": "wx-collection", "props": { "source": "faq" } }`)
 * and the page keeps only which of it to show ({@see Selection}); what the records are, which of
 * them a reader may see, in what order and what each one carries is this — the module's — answer.
 * That split is what lets one field type serve a FAQ, a team and reviews without learning any of
 * them.
 *
 * Registered in {@see CollectionSources} from the module's provider, not from its route file: a
 * cached route table never runs it.
 */
interface CollectionSource
{
    /** What a schema writes in `props.source`: `faq`. */
    public function key(): string;

    /** The name of the source in the panel's language, for the label of the field. */
    public function title(): string;

    /**
     * The path the source's categories answer at under the panel's API — what `props.source` of a
     * `wx-categories` field would say (`faq/categories`) — or null for a source without them.
     */
    public function categories(): ?string;

    /** Whether the source prints schema.org markup for what it shows, so the field offers the switch. */
    public function supportsMarkup(): bool;

    /** Any administrator who may open the section may place its records; null — anybody. */
    public function permission(): ?string;

    /**
     * What a reader of the page sees, in the order they see it.
     *
     * Every element carries `id`, `anchor` and `categories` (ids); the rest is the source's own.
     * What is unpublished, in the bin or not written in `$locale` is not here — who may be seen
     * is the source's rule, not the contract's. When `$selection->markup` is on, the source puts
     * its markup on the page itself: the contract knows nothing of `module-seo`, and must not.
     *
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array;
}
