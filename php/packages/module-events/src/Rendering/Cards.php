<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Routing\Models\Route;

/**
 * An event as a template reads it — plain data, not the model (§4.8).
 *
 *     id, url, title, lead     in the language asked for; `lead` is plain text
 *     cover, gallery           what `wx-media` hands over: url, thumb, width, height…; cover is
 *                              the first picture of the gallery, or null
 *     starts_at, ends_at       ISO 8601 with the offset, or null
 *     all_day, past            booleans
 *     when                     the date in words (§4.6) — the note where one is written
 *     attendance               offline | online | mixed
 *     venue, price             words in this language, '' where there are none
 *     booking_url              the link to book, or null
 *     ics_url                  the calendar file, or null for an event without a date
 *     categories               ids, in the order chosen — the first is the main one
 *     category_links           [{ id, title, url }] — the visible ones with an address here
 *     fields                   the project's own fields (a patch on `events.form`), by name
 *
 * Not the model, because a model in a template is the draft one call away and a query per card
 * nobody sees: every card here is built from what was loaded with the list.
 */
final class Cards
{
    /** What a list of events is loaded with, so that no card goes back to the database. */
    public const RELATIONS = ['routes', 'categories.routes'];

    public function __construct(
        private readonly MediaFiles $files,
        private readonly MediaValues $media,
    ) {}

    /**
     * @param  list<Event>  $events
     * @return list<array<string, mixed>>
     */
    public function events(array $events, string $locale): array
    {
        $paths = [];

        foreach ($events as $event) {
            foreach (is_array($event->gallery) ? $event->gallery : [] as $picture) {
                if (is_array($picture) && is_string($picture['path'] ?? null)) {
                    $paths[] = $picture['path'];
                }
            }
        }

        // Every picture of the list in one query of the library rather than one per card.
        $this->files->load($paths);

        return array_map(fn (Event $event): array => $this->event($event, $locale), $events);
    }

    /**
     * The categories an event shows, as links — a card's `category_links` and the event page's
     * `$categories` alike, so the two never disagree about which category is the main one.
     *
     * @return list<array{id: int, title: string, url: string}>
     */
    public function categoryLinks(Event $event, string $locale): array
    {
        $links = [];

        foreach ($event->shownCategories() as $category) {
            if ($category instanceof EventCategory && $category->isVisible($locale) && $category->hasUrlIn($locale)) {
                $links[] = ['id' => (int) $category->getKey(), 'title' => $category->displayName($locale), 'url' => $this->url($category, $locale)];
            }
        }

        return $links;
    }

    /** The calendar file of an event: its address with `.ics`, and none without a date. */
    public function icsUrl(Event $event, string $locale): ?string
    {
        if ($event->starts_at === null) {
            return null;
        }

        $url = $this->url($event, $locale);

        return $url === '' ? null : self::ics($url);
    }

    /** `https://site/events/class` → `https://site/events/class.ics`, a query kept after it. */
    public static function ics(string $url): string
    {
        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);

        return rtrim($path, '/').'.ics'.($query === null ? '' : '?'.$query);
    }

    /** @return array<string, mixed> */
    private function event(Event $event, string $locale): array
    {
        $fields = [];

        foreach (array_keys((array) ($event->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $event->extra((string) $name, $locale);
        }

        $gallery = $this->media->resolveList($event->gallery ?? [], $locale);
        $booking = is_string($event->booking_url) && $event->booking_url !== '' ? $event->booking_url : null;

        return [
            'id' => (int) $event->getKey(),
            'url' => $this->url($event, $locale),
            'title' => $event->text('title', $locale),
            'lead' => $event->text('lead', $locale),
            'cover' => $gallery[0] ?? null,
            'gallery' => $gallery,
            'starts_at' => $event->starts_at?->toAtomString(),
            'ends_at' => $event->ends_at?->toAtomString(),
            'all_day' => (bool) $event->all_day,
            'when' => When::of($event, $locale),
            'past' => $event->isPast(),
            'attendance' => $event->attendance,
            'venue' => $event->hasPlace() ? $event->text('venue', $locale) : '',
            'price' => $event->text('price', $locale),
            'booking_url' => $booking,
            'ics_url' => $this->icsUrl($event, $locale),
            'categories' => $event->categories->map(static fn (EventCategory $category): int => (int) $category->getKey())->values()->all(),
            'category_links' => $this->categoryLinks($event, $locale),
            'fields' => $fields,
        ];
    }

    /**
     * From the loaded registry rows when the list loaded them: `url()` would ask the registry again
     * for every card. A model loaded without them — a draft's pending category — asks.
     */
    private function url(Model $entity, string $locale): string
    {
        if (! method_exists($entity, 'url') || ! method_exists($entity, 'urlOf')) {
            return '';
        }

        if (! $entity->relationLoaded('routes')) {
            return (string) $entity->url($locale);
        }

        /** @var Collection<int, Route> $routes */
        $routes = $entity->getRelation('routes');
        $row = $routes->first(
            static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL,
        );

        return $row instanceof Route ? (string) $entity->urlOf($row->path, $locale) : (string) $entity->url($locale);
    }
}
