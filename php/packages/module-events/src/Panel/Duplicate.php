<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use Illuminate\Database\ConnectionInterface;
use WebxUi\Events\Models\Event;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * "Duplicate" (decision 9): the way a repeated event is made — every date its own record, and the
 * next one started as a copy of the last.
 *
 * The copy is a draft that was never published: every field of the event as its editor last left
 * it, its categories and services, the SEO card — and no history, because the history of the
 * original is not the copy's. The title stays; the address takes the next free `-2`, `-3` in
 * every language, because an address is the one thing two events cannot share. One transaction:
 * a copy refused half way — its address taken meanwhile — leaves no row behind.
 */
final class Duplicate
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function of(Event $source): Event
    {
        return $this->db->transaction(function () use ($source): Event {
            $shown = $source->hasDraft() ? $source->withDraft() : $source;

            $copy = new Event;

            foreach (Event::TRANSLATED as $field) {
                $copy->setAttribute($field, $shown->getTranslations($field));
            }

            $copy->setAttribute('slug', $this->freeSlugs($shown->getTranslations('slug')));

            foreach (['gallery', 'starts_at', 'ends_at', 'all_day', 'attendance', 'map_url', 'highlights', 'price_amount', 'booking_url'] as $field) {
                $copy->setAttribute($field, $shown->getAttribute($field));
            }

            $extra = $shown->extraRaw();

            if (is_array($extra) && $extra !== []) {
                $copy->setAttribute($copy->extraColumn(), $extra);
            }

            $copy->save();

            // Straight into the rows: the copy has never been on the site, so there is no
            // published state for a draft to differ from — and nothing lists an unpublished event.
            $copy->syncCategories($source->draftedCategoryIds());

            $services = $source->draftedRelatedIds(Event::SERVICES);

            if ($services !== []) {
                $copy->syncRelated(Event::SERVICES, 'service', $services);
            }

            $seo = $source->seoValue();

            if ($seo !== []) {
                $copy->saveSeo($seo);
            }

            return $copy->refresh();
        });
    }

    /**
     * Every language's slug with the first free suffix — `class-2`, or `class-3` when that is taken
     * — the registry refuses a taken address, and asking it first is cheaper than a refusal.
     *
     * @param  array<string, mixed>  $slugs
     * @return array<string, string>
     */
    private function freeSlugs(array $slugs): array
    {
        $prefix = UrlNormaliser::key((string) config('webx-events.prefix', 'events'));
        $free = [];

        foreach ($slugs as $locale => $slug) {
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $base = trim($slug);
            $number = 2;

            while ($this->taken($prefix.'/'.$base.'-'.$number, (string) $locale)) {
                $number++;
            }

            $free[(string) $locale] = $base.'-'.$number;
        }

        return $free;
    }

    private function taken(string $path, string $locale): bool
    {
        return Route::query()->where('locale', $locale)->where('path', UrlNormaliser::key($path))->exists();
    }
}
