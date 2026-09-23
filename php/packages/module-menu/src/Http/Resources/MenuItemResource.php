<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Resources;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Menu\Models\MenuItem;

/**
 * One item of the tree, with its target already resolved.
 *
 * `resolved` is here so that the screen does not go looking for a name (§10): an item keeps a
 * morph pair, and a row has to draw a title and an address, neither of which is in the columns.
 * They are asked for once per read of the tree and one query per kind of entity, which is the
 * same promise the render makes to the site — a menu of forty items is not forty queries.
 *
 * The label is translated and comes back as the whole map rather than as one string, because
 * this is what the form edits: a field with a language chip needs every language, and a string
 * would be the language the panel happens to be open in, saved over the other nine.
 */
final readonly class MenuItemResource
{
    /**
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @param  list<self>  $children
     */
    public function __construct(
        private MenuItem $item,
        private LinkUrls $urls,
        private string $locale,
        private array $candidates = [],
        private array $children = [],
    ) {}

    /**
     * The whole tree of a menu, nested, out of one ordered walk.
     *
     * @param  EloquentCollection<int, MenuItem>  $items
     * @return list<self>
     */
    public static function tree(EloquentCollection $items, LinkUrls $urls, string $locale): array
    {
        $links = [];

        foreach ($items as $item) {
            $links[] = $item->link();
        }

        return self::level($items, $urls, $locale, $urls->candidates($links, $locale), null);
    }

    /**
     * @param  EloquentCollection<int, MenuItem>  $items
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @return list<self>
     */
    private static function level(
        EloquentCollection $items,
        LinkUrls $urls,
        string $locale,
        array $candidates,
        ?int $parentId,
    ): array {
        $level = [];

        foreach ($items as $item) {
            if ($item->parent_id !== $parentId) {
                continue;
            }

            $level[] = new self(
                $item,
                $urls,
                $locale,
                $candidates,
                self::level($items, $urls, $locale, $candidates, (int) $item->getKey()),
            );
        }

        return $level;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $item = $this->item;
        $link = $item->link();
        $candidate = $link->entityType === null || $link->entityId === null
            ? null
            : ($this->candidates[$link->entityType][$link->entityId] ?? null);

        return [
            'id' => (int) $item->getKey(),
            'parent_id' => $item->parent_id,
            'depth' => $item->depth,
            'title' => $item->getTranslations('title'),
            // What the row says when the item has no label of its own: the name of the thing it
            // points at, which is what the site would print and what the form offers as a
            // placeholder.
            'label' => $this->label($candidate),
            'target' => $item->target,
            'entity_type' => $item->entity_type,
            'entity_id' => $item->entity_id,
            'url' => $item->url,
            'hash' => $item->hash,
            // The address as the site would print it, language prefix and anchor included. It is
            // the line under the label, so it is worked out here rather than in the browser.
            'href' => $this->urls->hrefWith($link, $candidate, $this->locale),
            'variant' => $item->variant,
            'is_heading' => $item->is_heading,
            'new_tab' => $item->new_tab,
            'rel' => $item->rel ?? [],
            'locales' => $item->locales ?? [],
            'visible' => $item->visible,
            // Whether the site would show what this points at. A draft is a legitimate target —
            // menus are built before the pages in them are published — so the row is drawn
            // dimmed rather than left out of the panel.
            'available' => $link->target !== LinkTarget::Entity
                || ($candidate instanceof LinkCandidate && $candidate->available),
            'resolved' => $candidate?->toArray(),
            'children' => array_map(static fn (self $child): array => $child->toArray(), $this->children),
        ];
    }

    private function label(?LinkCandidate $candidate): string
    {
        $written = $this->item->getTranslation('title', $this->locale, false);

        if (is_string($written) && trim($written) !== '') {
            return trim($written);
        }

        if ($candidate instanceof LinkCandidate && trim($candidate->title) !== '') {
            return trim($candidate->title);
        }

        return '';
    }
}
