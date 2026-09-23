<?php

declare(strict_types=1);

namespace WebxUi\Blog\Support;

use Illuminate\Database\ConnectionInterface;
use WebxUi\Blog\Exceptions\BlogException;
use WebxUi\Blog\Models\Tag;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * Merging tags into one (§6).
 *
 * Within six months of an editor being allowed to make tags from the article form, the table
 * holds "belts", "belt" and "drive belts", and all three are right. This is the one operation
 * that puts them back together: the articles move over, the duplicates go, and the addresses
 * that existed can be kept alive.
 *
 * Two things about it are worth saying out loud, and the dialog says both:
 *
 * - It is irreversible. The pivot rows that went are gone, and putting a tag back would not put
 *   back which articles carried it.
 * - The aliases of `webx-ui/routing` die with the tag — they are rows keyed to the entity and
 *   the foreign key takes them. So an address that is to survive the merge has to survive as
 *   something that is not tied to the tag, which is a redirect in `seo_redirects`. That is what
 *   the checkbox writes, and why it is a checkbox rather than something done silently: a
 *   redirect is a permanent fact about the site, and a tag made by mistake this morning does
 *   not deserve one.
 */
final class TagMerge
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Move everything filed under `$merged` onto `$keep` and delete them.
     *
     * @param  list<Tag>  $merged
     * @return int How many articles ended up carrying the surviving tag.
     */
    public function merge(array $merged, Tag $keep, bool $redirect = false): int
    {
        $merged = array_values(array_filter(
            $merged,
            static fn (Tag $tag): bool => $tag->getKey() !== $keep->getKey(),
        ));

        if ($merged === []) {
            throw BlogException::tagCannotMergeIntoItself();
        }

        return (int) $this->connection->transaction(function () use ($merged, $keep, $redirect): int {
            foreach ($merged as $tag) {
                // Worked out before the tag is deleted, because deleting it takes its rows in
                // `routes` with it and there would be nothing left to redirect from.
                $addresses = $redirect ? $this->addressesOf($tag) : [];

                // `syncWithoutDetaching` rather than an `update` on the pivot: an article that
                // carried both tags would otherwise break `unique(article_id, tag_id)`, and
                // that index is exactly the guarantee this operation is built on (§6).
                $keep->articles()->syncWithoutDetaching($tag->articles()->pluck('articles.id')->all());

                // Detached by hand as well as by the cascade: sqlite ignores foreign keys
                // unless it is asked to, so the cascade is a promise that does not hold in
                // every environment this runs in.
                $tag->articles()->detach();
                $tag->delete();

                foreach ($addresses as $from) {
                    $this->redirect($from, $keep);
                }
            }

            return $keep->articles()->count();
        });
    }

    /**
     * Every address this tag answered at, one per language.
     *
     * @return list<string>
     */
    private function addressesOf(Tag $tag): array
    {
        $addresses = [];

        foreach ($tag->routes as $route) {
            $addresses[] = UrlNormaliser::normalise($route->path);
        }

        return array_values(array_unique($addresses));
    }

    /**
     * A permanent redirect onto the surviving tag, unless the site already has one for that
     * address — a merge run twice must not leave two rules arguing over the same path.
     */
    private function redirect(string $from, Tag $keep): void
    {
        $to = UrlNormaliser::normalise($keep->url());

        if ($from === $to) {
            return;
        }

        SeoRedirect::query()->firstOrCreate(
            ['match_type' => UrlMatcher::EXACT, 'pattern' => $from],
            ['target' => $to, 'status' => 301, 'is_active' => true],
        );
    }
}
