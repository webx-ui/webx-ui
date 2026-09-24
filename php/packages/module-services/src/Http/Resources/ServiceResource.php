<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;
use WebxUi\Services\Panel\Revision;

/**
 * One service as the panel knows it: a row of the list, and the record the editor opens.
 *
 * The title is the draft's — what the editor is working on, which is what they look for — and
 * the address is the registry's, because that is what the site answers at right now. A service
 * renamed in a draft shows its new title beside its old address, and that is the truth.
 *
 * @mixin Service
 */
final class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Service $service */
        $service = $this->resource;

        $locale = app(Locales::class)->current();
        $shown = $service->hasDraft() ? $service->withDraft() : $service;
        $canonical = $this->canonical($service, $locale);

        return [
            'id' => (int) $service->getKey(),
            'title' => $this->title($shown, $locale),
            'slug' => (string) $shown->getTranslation('slug', $locale, fallback: false),
            'lead' => (string) $shown->getTranslation('lead', $locale, fallback: false),
            // Null where the service names no slug in this language: it has no address there.
            'path' => $canonical?->path,
            'url' => $canonical === null ? null : $service->url($locale),
            'status' => $service->status(),
            'position' => (int) $service->position,
            'published_at' => $service->published_at?->toAtomString(),
            'updated_at' => $service->updated_at?->toAtomString(),
            'deleted_at' => $service->deleted_at?->toAtomString(),
            'cover' => $this->cover($service),
            'categories' => $this->categories($service, $locale),
            'revision' => Revision::of($service),
        ];
    }

    /** The title, the slug where there is none, the number where there is neither. */
    private function title(Service $service, string $locale): string
    {
        foreach ([$service->getTranslation('title', $locale), $service->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$service->getKey();
    }

    /** Out of what was loaded with the service: the whole list is one query for every address. */
    private function canonical(Service $service, string $locale): ?Route
    {
        if (! $service->relationLoaded('routes')) {
            return $service->routeCanonical($locale);
        }

        return $service->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL);
    }

    /**
     * The picture, never stored as an address: both are worked out from the file on every read.
     *
     * @return array<string, mixed>|null
     */
    private function cover(Service $service): ?array
    {
        $cover = $service->cover;

        if (! $cover instanceof MediaFile) {
            return null;
        }

        $urls = app(FileUrls::class);

        return [
            'id' => (int) $cover->getKey(),
            'path' => $cover->path,
            'url' => $urls->url($cover),
            'thumb' => $urls->thumbUrl($cover),
        ];
    }

    /**
     * In the order they were dragged into: the first is the main one.
     *
     * @return list<array<string, mixed>>
     */
    private function categories(Service $service, string $locale): array
    {
        return $service->categories
            ->map(static fn (ServiceCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => (string) $category->getTranslation('title', $locale),
                'slug' => (string) $category->getTranslation('slug', $locale, fallback: false),
            ])
            ->values()
            ->all();
    }
}
