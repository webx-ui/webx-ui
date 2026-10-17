<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Events\Models\Event;
use WebxUi\Seo\Rendering\Seo;

/**
 * The list of the index and of a category page (§4.4): one query for the events to come, the page
 * worked out of them, whole cards for that page only, and an `ItemList` of what it shows.
 */
final class ListPage
{
    public function __construct(
        private readonly Cards $cards,
        private readonly Seo $seo,
        private readonly Config $config,
    ) {}

    /**
     * @param  iterable<Model>  $categories  The categories the page around the list has loaded
     *                                       with their addresses — the navigation of the index,
     *                                       the category of its own page — so that a card under
     *                                       one of them does not ask the registry for it again.
     *
     * @throws NotFoundHttpException A page past the last one.
     */
    public function build(Request $request, EventQuery $query, iterable $categories = []): Listing
    {
        $locale = $query->resolvedLocale();

        /** @var list<Event> $events */
        $events = $query->upcoming()->models()->all();

        [$listing, $shown] = Listing::paged($events, (int) $this->config->get('webx-events.per-page', 24), $request);

        if ($listing->outOfRange()) {
            throw new NotFoundHttpException;
        }

        /** @var list<Event> $shown */
        $this->shareRoutes($shown, $categories);
        $cards = $this->cards->events($shown, $locale);

        $this->pushItemList($cards);

        return $listing->withItems($cards);
    }

    /**
     * The registry rows the page already holds for a category, handed to the same category under
     * the events shown — another instance of the same row, which would otherwise be read again.
     *
     * @param  list<Event>  $events
     * @param  iterable<Model>  $categories
     */
    private function shareRoutes(array $events, iterable $categories): void
    {
        $known = [];

        foreach ($categories as $category) {
            if ($category->relationLoaded('routes')) {
                $known[(int) $category->getKey()] = $category->getRelation('routes');
            }
        }

        if ($known === []) {
            return;
        }

        foreach ($events as $event) {
            if (! $event->relationLoaded('categories')) {
                continue;
            }

            foreach ($event->getRelation('categories') as $category) {
                if (! $category->relationLoaded('routes') && isset($known[(int) $category->getKey()])) {
                    $category->setRelation('routes', $known[(int) $category->getKey()]);
                }
            }
        }
    }

    /**
     * An `ItemList` of what this page shows — about the page rather than about an entity, so the
     * page pushes it.
     *
     * @param  list<array<string, mixed>>  $cards
     */
    private function pushItemList(array $cards): void
    {
        $urls = array_values(array_filter(array_map(
            static fn (array $card): ?string => is_string($card['url'] ?? null) && $card['url'] !== '' ? $card['url'] : null,
            $cards,
        )));

        if ($urls === []) {
            return;
        }

        $this->seo->push([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(
                static fn (string $url, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $url],
                $urls,
                array_keys($urls),
            ),
        ]);
    }
}
