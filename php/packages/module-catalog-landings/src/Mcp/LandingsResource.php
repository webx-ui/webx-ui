<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\Mcp\McpResource;

/**
 * `catalog://landings` (§11 of the landings spec): the house rules — what a landing is, when one is
 * worth making, how not to make the same one twice — and how many there are and in what state, so
 * an agent knows the ground before it writes.
 */
final class LandingsResource
{
    private const RULES = [
        'what' => 'A landing is a category\'s list (or the whole catalogue\'s) with a set of filters chosen in advance, under '
            .'an address, an H1, two texts and an SEO card of its own: «Apple laptops», «laptops under 50 000». It is the '
            .'same list a reader gets by choosing those filters — never a page built from blocks.',
        'when' => 'Make one for a selection people search for by name and the catalogue can fill: a brand in a category, a '
            .'colour, a price band. Not for every combination: a landing with a few products is a thin page, one with none '
            .'is noindex and out of the sitemap, and nobody reads it.',
        'duplicates' => 'A set is a landing once per base, in the bin included: read catalog_landings_list (or ask '
            .'catalog_landings_count, which names the holder) before making one. The same set on another base is another '
            .'landing. A filter address that spells a landing\'s set already leads to it.',
        'set' => 'Facet codes and value slugs as an address spells them, from catalog_landings_facets; the category is the '
            .'base, never a facet of the set. Stored by ids: renaming a value or a property leaves the landing as it is.',
        'bulk' => 'catalog_landings_generate makes a landing per base × value from templates ({category}, {value}). Run it '
            .'with dry_run first and read the conflicts: slug-taken, set-taken, empty, no-slug. Raise min_products rather '
            .'than making empty pages.',
        'attention' => 'A landing marked value_removed lost a value of its set to a deletion; duplicate — its set became '
            .'another landing\'s; empty_set — nothing is left of it, and it was unpublished. Fix the set (or delete the '
            .'landing); saving clears the mark.',
        'texts' => 'text_above and text_below are HTML shown on the plain landing only — not on its second page, not once a '
            .'reader adds a filter. Write for the reader of that selection; do not paste the category\'s text.',
    ];

    public function __construct(private readonly Config $config) {}

    public function resource(): McpResource
    {
        return new McpResource(
            'catalog://landings',
            'Landings of the catalogue',
            'The house rules of landings — what one is, when to make one, how not to duplicate one, what the marks of '
            .'attention mean — and how many there are: published, empty, needing attention, in the bin.',
            fn (): array => $this->read(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        return [
            'rules' => self::RULES,
            'counts' => [
                'all' => Landing::query()->count(),
                'published' => Landing::query()->where('is_published', true)->count(),
                'empty' => Landing::query()->where('products_count', 0)->count(),
                'attention' => Landing::query()->whereNotNull('attention')->count(),
                'trashed' => Landing::onlyTrashed()->count(),
                'whole_catalogue' => Landing::query()->whereNull('category_id')->count(),
            ],
            'generate' => [
                'in_the_request_up_to' => (int) $this->config->get('webx-catalog-landings.generate.sync_limit', 50),
                'at_most' => (int) $this->config->get('webx-catalog-landings.generate.max', 5000),
            ],
        ];
    }
}
