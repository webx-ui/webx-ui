<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Panel\Revision;
use WebxUi\Events\Rendering\When;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;

/**
 * One event as the panel knows it (§4.10): a row of the list, and the `event` of the form.
 *
 * The title, the dates and the categories are the draft's — what the editor is working on — and
 * the address is the registry's, because that is what the site answers at right now. The moments
 * travel with their offset (`toAtomString()`), and `when` is the date in words the site prints.
 *
 * @mixin Event
 */
final class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Event $event */
        $event = $this->resource;

        $locale = app(Locales::class)->current();
        $shown = $event->hasDraft() ? $event->withDraft() : $event;
        $canonical = $this->canonical($event, $locale);
        $cover = $shown->cover($locale);

        return [
            'id' => (int) $event->getKey(),
            'title' => $this->title($shown, $locale),
            'slug' => (string) $shown->getTranslation('slug', $locale, fallback: false),
            // Null where the event names no slug in this language: it has no address there.
            'path' => $canonical?->path,
            'url' => $canonical === null ? null : $event->url($locale),
            'cover' => $cover === null ? null : ['thumb' => $cover['thumb'] ?? $cover['url'] ?? null],
            'starts_at' => $shown->starts_at?->toAtomString(),
            'ends_at' => $shown->ends_at?->toAtomString(),
            'all_day' => (bool) $shown->all_day,
            'when' => When::of($shown, $locale),
            'past' => $shown->isPast(),
            'status' => $event->status(),
            'categories' => $this->categories($event, $locale),
            'published_at' => $event->published_at?->toAtomString(),
            'updated_at' => $event->updated_at?->toAtomString(),
            'deleted_at' => $event->deleted_at?->toAtomString(),
            'revision' => Revision::of($event),
        ];
    }

    /** The title, the slug where there is none, the number where there is neither. */
    private function title(Event $event, string $locale): string
    {
        foreach ([$event->getTranslation('title', $locale), $event->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$event->getKey();
    }

    private function canonical(Event $event, string $locale): ?Route
    {
        if (! $event->relationLoaded('routes')) {
            return $event->routeCanonical($locale);
        }

        return $event->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL);
    }

    /**
     * In the order they were put in: the first is the main one. The draft's when the draft names
     * them — the loaded rows otherwise, so the list is one query for every row that has no draft.
     *
     * @return list<array{id: int, title: string}>
     */
    private function categories(Event $event, string $locale): array
    {
        $drafted = $event->draftValues()[Event::DRAFT_CATEGORIES] ?? null;

        if (is_array($drafted)) {
            $ids = array_values(array_map(intval(...), $drafted));
            $found = EventCategory::query()->whereKey($ids)->get()->keyBy(static fn (EventCategory $category): int => (int) $category->getKey());
            $categories = array_values(array_filter(array_map(static fn (int $id): ?EventCategory => $found->get($id), $ids)));
        } else {
            $categories = $event->categories->all();
        }

        return array_map(static fn (EventCategory $category): array => [
            'id' => (int) $category->getKey(),
            'title' => $category->displayName($locale),
        ], $categories);
    }
}
