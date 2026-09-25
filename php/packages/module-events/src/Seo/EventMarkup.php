<?php

declare(strict_types=1);

namespace WebxUi\Events\Seo;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Events\Models\Event;
use WebxUi\Seo\Panel\DefaultsSource;

/**
 * schema.org `Event` for an event page (§4.7) — the markup Google shows as a rich result to any
 * site. Only for an event with a start: {@see Event::structuredData()} asks first.
 *
 * The dates are moments with their offset, or only days for an event of days. The place is a
 * `Place` where people come, a `VirtualLocation` where they watch, both where they may do either
 * (decision 16). The price is the number an editor wrote for machines — the words on the page are
 * for people and say "from", "per person", "on request" (decision 8) — and only with the site's
 * currency: a number with no currency is no price.
 */
final class EventMarkup
{
    private const MODES = [
        Event::OFFLINE => 'https://schema.org/OfflineEventAttendanceMode',
        Event::ONLINE => 'https://schema.org/OnlineEventAttendanceMode',
        Event::MIXED => 'https://schema.org/MixedEventAttendanceMode',
    ];

    public function __construct(
        private readonly DefaultsSource $defaults,
        private readonly Config $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function of(Event $event, string $locale): array
    {
        $url = $event->url($locale);

        $markup = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->text('title', $locale),
            'url' => $url,
            ...$this->dates($event),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => self::MODES[$event->attendance] ?? self::MODES[Event::OFFLINE],
        ];

        $lead = $event->text('lead', $locale);

        if ($lead !== '') {
            $markup['description'] = $lead;
        }

        $images = array_values(array_filter(array_map(
            static fn (array $picture): ?string => is_string($picture['url'] ?? null) ? $picture['url'] : null,
            $event->pictures($locale),
        )));

        if ($images !== []) {
            $markup['image'] = $images;
        }

        $location = $this->location($event, $locale, $url);

        if ($location !== []) {
            $markup['location'] = count($location) === 1 ? $location[0] : $location;
        }

        if ($event->price_amount !== null && (float) $event->price_amount === 0.0) {
            $markup['isAccessibleForFree'] = true;
        }

        $offer = $this->offer($event);

        if ($offer !== null) {
            $markup['offers'] = $offer;
        }

        $organisation = $this->defaults->organizationId($locale);

        if ($organisation !== null) {
            $markup['organizer'] = ['@id' => $organisation];
        }

        return $markup;
    }

    /**
     * Moments with their offset; for an event of days, the days alone — its end is the last day,
     * not the midnight after it.
     *
     * @return array<string, string>
     */
    private function dates(Event $event): array
    {
        $start = $event->starts_at;

        if ($start === null) {
            return [];
        }

        if ($event->all_day) {
            $end = $event->ends_at ?? $start;

            return [
                'startDate' => $start->toDateString(),
                'endDate' => $end->toDateString(),
            ];
        }

        $dates = ['startDate' => $start->toAtomString()];

        if ($event->ends_at !== null) {
            $dates['endDate'] = $event->ends_at->toAtomString();
        }

        return $dates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function location(Event $event, string $locale, string $url): array
    {
        $location = [];

        if ($event->attendance !== Event::ONLINE) {
            $venue = $event->text('venue', $locale);
            $address = $event->text('address', $locale);

            if ($venue !== '' || $address !== '') {
                $place = ['@type' => 'Place', 'name' => $venue !== '' ? $venue : $address];

                if ($address !== '') {
                    $place['address'] = $address;
                }

                $location[] = $place;
            }
        }

        if ($event->attendance !== Event::OFFLINE) {
            $location[] = [
                '@type' => 'VirtualLocation',
                // The broadcast is for those who booked (decision 16): where to book is the way in.
                'url' => is_string($event->booking_url) && $event->booking_url !== '' ? $event->booking_url : $url,
            ];
        }

        return $location;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function offer(Event $event): ?array
    {
        $currency = strtoupper(trim((string) $this->config->get('webx-events.currency', '')));
        $booking = is_string($event->booking_url) && $event->booking_url !== '' ? $event->booking_url : null;
        $priced = $event->price_amount !== null && $currency !== '';

        if ($booking === null && ! $priced) {
            return null;
        }

        $offer = ['@type' => 'Offer'];

        if ($booking !== null) {
            $offer['url'] = $booking;
        }

        if ($priced) {
            $offer['price'] = self::amount((float) $event->price_amount);
            $offer['priceCurrency'] = $currency;
        }

        return $offer;
    }

    /** `480`, `480.5` — the shortest that is exact; schema.org takes the number as text. */
    private static function amount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
