<?php

declare(strict_types=1);

namespace WebxUi\Services\Rendering;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * A service as a template reads it — plain data, not the model.
 *
 *     id, anchor, categories   what every element of a `wx-collection` carries
 *     title, url, lead         in the language asked for; `lead` is plain text
 *     cover                    what a `wx-media` field hands over: url, thumb, width, height…
 *     fields                   the project's own fields (a patch on `services.form`), by name
 *
 * Not the model, because a model in a template is the draft one call away, the bin one relation
 * away, and a query per card nobody sees: every card here is built from what was loaded with the
 * list, whatever its length.
 */
final class Cards
{
    /** What a list of services is loaded with, so that no card goes back to the database. */
    public const RELATIONS = ['cover', 'routes', 'categories'];

    public function __construct(
        private readonly MediaFiles $files,
        private readonly MediaValues $media,
    ) {}

    /**
     * @param  list<Service>  $services
     * @return list<array<string, mixed>>
     */
    public function services(array $services, string $locale): array
    {
        $this->preload($services);

        return array_map(fn (Service $service): array => $this->service($service, $locale), $services);
    }

    /**
     * A category with the services it lists, already chosen and ordered by the caller.
     *
     * @param  list<Service>  $services
     * @return array<string, mixed>
     */
    public function category(ServiceCategory $category, array $services, string $locale): array
    {
        $this->preload([$category, ...$services]);

        return [
            'id' => (int) $category->getKey(),
            'anchor' => $this->text($category, 'slug', $locale),
            'title' => $this->text($category, 'title', $locale),
            'url' => $this->url($category, $locale),
            'lead' => $category->leadHtml($locale),
            'cover' => $this->cover($category, $locale),
            'services' => array_map(fn (Service $service): array => $this->service($service, $locale), $services),
        ];
    }

    /** @return array<string, mixed> */
    private function service(Service $service, string $locale): array
    {
        $fields = [];

        foreach (array_keys((array) ($service->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $service->extra((string) $name, $locale);
        }

        return [
            'id' => (int) $service->getKey(),
            'anchor' => $this->text($service, 'slug', $locale),
            'categories' => $service->categories->map(static fn (ServiceCategory $category): int => (int) $category->getKey())->values()->all(),
            'title' => $this->text($service, 'title', $locale),
            'url' => $this->url($service, $locale),
            'lead' => $this->text($service, 'lead', $locale),
            'cover' => $this->cover($service, $locale),
            'fields' => $fields,
        ];
    }

    /**
     * Every cover of the list in one query of the library rather than one per card.
     *
     * @param  list<Service|ServiceCategory>  $models
     */
    private function preload(array $models): void
    {
        $paths = [];

        foreach ($models as $model) {
            $cover = $model->cover;

            if ($cover instanceof MediaFile) {
                $paths[] = $cover->path;
            }
        }

        $this->files->load($paths);
    }

    /** @return array<string, mixed>|null */
    private function cover(Service|ServiceCategory $model, string $locale): ?array
    {
        $cover = $model->cover;

        return $cover instanceof MediaFile ? $this->media->resolve(['path' => $cover->path], $locale) : null;
    }

    /** From the loaded registry rows: `url()` would ask the registry again for every card. */
    private function url(Service|ServiceCategory $model, string $locale): string
    {
        $row = $model->routes->first(
            static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL,
        );

        return $row instanceof Route ? $model->urlOf($row->path, $locale) : $model->url($locale);
    }

    private function text(Model $model, string $attribute, string $locale): string
    {
        /** @var Service|ServiceCategory $model */
        $value = $model->getTranslation($attribute, $locale);

        return is_string($value) ? $value : '';
    }
}
