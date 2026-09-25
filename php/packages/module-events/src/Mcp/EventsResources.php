<?php

declare(strict_types=1);

namespace WebxUi\Events\Mcp;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Rendering\When;
use WebxUi\Events\Support\Moment;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it writes an event (§4.11): the categories with the events to come,
 * and only a count of the past ones.
 *
 * The past piles up for years and is not what an agent is about to duplicate by mistake; the
 * events to come are — "a spring class on 12 October" written twice is the thing to catch. So each
 * category lists its events to come in the order the site shows them (no date first, then the
 * nearest), with `when` as the site prints it, and says how many of its events are over;
 * `events_list` with `when: past` has those. Drafts are in it and say so. An event in two
 * categories is listed under both: that is where a reader meets it.
 *
 * One title per row, in the language the agent works in; `written_in` says which languages the
 * event has a title in at all, and `events_get` has every language of the one it picks.
 */
final class EventsResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'events://catalog',
                'Events catalog',
                'Every event category in its order with its events to come — their dates as the site prints them, '
                .'their addresses, whether they are on the site and the languages they are written in — and how many '
                .'of its events are over; the events in no category at the end; the site\'s timezone, in which a '
                .'date without an offset is read. Read it before creating an event or a category, so that you reuse '
                .'rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $prefix = (string) config('webx-events.prefix', 'events');

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'timezone' => Moment::zone(),
            'currency' => config('webx-events.currency'),
            'prefix' => $prefix,
            // Null when the module's index is switched off: a page may stand at that address.
            'index_url' => (bool) config('webx-events.index', true) ? url($prefix) : null,
            'categories' => EventCategory::query()->ordered()->get()->map(fn (EventCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => $category->displayName($locales->current()),
                'slug' => (string) $category->getTranslation('slug', $locales->current()),
                'url' => $category->hasUrlIn($locales->current()) ? $category->url($locales->current()) : null,
                'visible' => (bool) $category->is_visible,
                'upcoming' => $this->rows(
                    $this->inCategory((int) $category->getKey())->scopes(['upcoming'])->with('routes')->get()->all(),
                    $locales,
                ),
                'past_count' => $this->inCategory((int) $category->getKey())->scopes(['past'])->count(),
            ])->values()->all(),
            'uncategorised' => [
                'upcoming' => $this->rows(
                    Event::query()->whereDoesntHave('categories')->scopes(['upcoming'])->with('routes')->get()->all(),
                    $locales,
                ),
                'past_count' => Event::query()->whereDoesntHave('categories')->scopes(['past'])->count(),
            ],
        ];
    }

    /**
     * @return Builder<Event>
     */
    private function inCategory(int $category): Builder
    {
        return Event::query()->scopes(['inCategory' => [$category]]);
    }

    /**
     * @param  list<Event>  $events
     * @return list<array<string, mixed>>
     */
    private function rows(array $events, Locales $locales): array
    {
        $locale = $locales->current();
        $codes = $locales->codes();

        return array_map(static function (Event $event) use ($locale, $codes): array {
            $shown = $event->hasDraft() ? $event->withDraft() : $event;

            return [
                'id' => (int) $event->getKey(),
                'title' => (string) $shown->getTranslation('title', $locale),
                'when' => When::of($shown, $locale),
                'starts_at' => $shown->starts_at?->toAtomString(),
                'url' => $event->hasUrlIn($locale) ? $event->url($locale) : null,
                'status' => $event->status(),
                'written_in' => array_values(array_filter(
                    $codes,
                    static fn (string $code): bool => trim((string) $shown->getTranslation('title', $code, fallback: false)) !== '',
                )),
            ];
        }, $events);
    }
}
