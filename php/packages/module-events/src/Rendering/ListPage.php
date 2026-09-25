<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
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
     * @throws NotFoundHttpException A page past the last one.
     */
    public function build(Request $request, EventQuery $query): Listing
    {
        $locale = $query->resolvedLocale();

        /** @var list<Event> $events */
        $events = $query->upcoming()->models()->all();

        [$listing, $shown] = Listing::paged($events, (int) $this->config->get('webx-events.per-page', 24), $request);

        if ($listing->outOfRange()) {
            throw new NotFoundHttpException;
        }

        /** @var list<Event> $shown */
        $cards = $this->cards->events($shown, $locale);

        $this->pushItemList($cards);

        return $listing->withItems($cards);
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
