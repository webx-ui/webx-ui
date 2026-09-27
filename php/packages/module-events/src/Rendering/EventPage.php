<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Events\Models\Event;
use WebxUi\Localization\Locales;
use WebxUi\Services\Rendering\ServiceQuery;

/**
 * Everything the parts of an event page print, worked out once (§4.5) — so a part a site
 * publishes and rewrites is markup over plain data, and no part asks the database for itself.
 *
 *     $event         the model — for `extra()`, SEO and anything a site's part wants of it
 *     $pictures      the gallery, resolved; the first is the cover
 *     $title, $lead
 *     $when          the date in words (§4.6) — '' for an event without one and without a note
 *     $past          over: no booking, a note saying so
 *     $online        an online event has no place
 *     $venue, $address, $map_url
 *     $price         words, '' where there are none
 *     $booking_url   null when there is none or the event is over
 *     $ics_url       null for an event without a date
 *     $categories    [{ id, title, url }] — visible, with an address in this language
 *     $description   HTML as stored (the field type cleaned it on the way in)
 *     $highlights    [{ title, text }] — the cards written in this language
 *     $services      cards of the visible related services, when `module-services` is here
 */
final class EventPage
{
    public function __construct(
        private readonly Cards $cards,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Event $event): array
    {
        $locale = $this->locales->current();
        $past = $event->isPast();
        $booking = is_string($event->booking_url) && $event->booking_url !== '' && ! $past ? $event->booking_url : null;
        $place = $event->hasPlace();

        return [
            'event' => $event,
            'pictures' => $event->pictures($locale),
            'title' => $event->text('title', $locale),
            'lead' => $event->text('lead', $locale),
            'when' => When::of($event, $locale),
            'past' => $past,
            'online' => $event->attendance === Event::ONLINE,
            'venue' => $place ? $event->text('venue', $locale) : '',
            'address' => $place ? $event->text('address', $locale) : '',
            'map_url' => $place && is_string($event->map_url) && $event->map_url !== '' ? $event->map_url : null,
            'price' => $event->text('price', $locale),
            'booking_url' => $booking,
            'ics_url' => $this->cards->icsUrl($event, $locale),
            'categories' => $this->cards->categoryLinks($event, $locale),
            'description' => $event->descriptionHtml($locale),
            'highlights' => $event->highlights($locale),
            'services' => $this->services($event, $locale),
        ];
    }

    /**
     * The services this event is related to, as `services()` shows them — visible, in the order
     * chosen. None without `module-services`: the field is not on the form then, either.
     *
     * @return list<array<string, mixed>>
     */
    private function services(Event $event, string $locale): array
    {
        if (! class_exists(ServiceQuery::class)) {
            return [];
        }

        $ids = $event->related(Event::SERVICES)->map(static fn (Model $service): int => (int) $service->getKey())->all();

        if ($ids === []) {
            return [];
        }

        return Container::getInstance()->make(ServiceQuery::class)->only($ids)->locale($locale)->get();
    }
}
