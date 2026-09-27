<?php

declare(strict_types=1);

namespace WebxUi\Events\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Rendering\When;
use WebxUi\Routing\Models\Route;

/**
 * Events, for whatever points at an entity: a menu entry, a link field, a block (§3 of the menu
 * spec). The hint is the date, which is what tells two events of a series apart.
 */
final class EventLinkSource implements LinkSource
{
    public function type(): string
    {
        return Event::TYPE;
    }

    public function model(): string
    {
        return Event::class;
    }

    public function title(): string
    {
        return (string) __('webx-events::module.events');
    }

    public function icon(): string
    {
        return 'calendar';
    }

    public function order(): int
    {
        return 240;
    }

    public function permission(): string
    {
        return 'events.view';
    }

    public function search(string $query, string $locale, int $limit): array
    {
        $events = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->where(static function (Builder $nested) use ($query): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
            }))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($events, $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Event>
     */
    private function query(string $locale): Builder
    {
        return Event::query()->with([
            'routes' => static fn ($routes) => $routes
                ->where('locale', $locale)
                ->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Event>  $events
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $events, string $locale): array
    {
        $candidates = [];

        foreach ($events as $event) {
            $id = (int) $event->getKey();
            $canonical = $event->routes->first();
            $when = When::of($event, $locale);

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: self::name($event, $locale),
                url: $canonical instanceof Route ? $event->urlOf($canonical->path, $locale) : null,
                available: $event->isPublished() && $canonical instanceof Route,
                hint: $when === '' ? null : $when,
            );
        }

        return $candidates;
    }

    private static function name(Event $event, string $locale): string
    {
        foreach (['title', 'slug'] as $attribute) {
            $candidate = $event->getTranslation($attribute, $locale);

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$event->getKey();
    }
}
