<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use WebxUi\Events\Models\Event;

/**
 * What an editor read, as a short string, so a save can say whether somebody wrote in between.
 *
 * A hash of the event as it is being edited — the draft where there is one, the columns where
 * there is not — with the categories and the services as the editor last left them: they wait in
 * the draft like the text, and a service added by somebody else is a change to the event as much
 * as a moved date is.
 */
final class Revision
{
    /** The columns of the content, in this order. */
    private const CONTENT = [
        'title', 'slug', 'lead', 'gallery', 'starts_at', 'ends_at', 'all_day', 'date_note', 'attendance',
        'venue', 'address', 'map_url', 'description', 'highlights', 'price', 'price_amount', 'booking_url', 'extra',
    ];

    public static function of(Event $event): string
    {
        $shown = $event->hasDraft() ? $event->withDraft() : $event;

        $content = [];

        foreach (self::CONTENT as $column) {
            $value = $shown->getAttribute($column);
            $content[$column] = $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
        }

        $content['published_at'] = $event->published_at?->toAtomString();
        $content['categories'] = $event->draftedCategoryIds();
        $content['services'] = $event->draftedRelatedIds(Event::SERVICES);

        return substr(sha1(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }
}
