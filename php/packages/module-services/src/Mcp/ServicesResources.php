<?php

declare(strict_types=1);

namespace WebxUi\Services\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * What an agent reads before it writes a service (§4.8): the whole catalogue in one message.
 *
 * Every category, hidden ones too, each with its services in that category's own order, and the
 * services filed nowhere at the end. Drafts are in it and say so — the point of reading this first
 * is not to write a second "Dental implants" beside one that is half-written, and a catalogue of
 * what is live would hide exactly that one.
 *
 * In the language the agent is working in, one title per row: this is a map to find things on,
 * and `services_get` has every language of the one it picks.
 */
final class ServicesResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'services://catalog',
                'Services catalog',
                'Every category of services in its order, with the services in each in that category\'s own '
                .'order, their addresses and whether they are on the site; the services in no category at the '
                .'end. Read it before creating a service or a category, so that you reuse rather than duplicate.',
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
        $locale = $locales->current();
        $prefix = (string) config('webx-services.prefix', 'services');

        $categories = ServiceCategory::query()->ordered()->get();

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'prefix' => $prefix,
            'index_url' => $prefix === '' ? null : url($prefix),
            'categories' => $categories->map(fn (ServiceCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => (string) $category->getTranslation('title', $locale),
                'slug' => (string) $category->getTranslation('slug', $locale),
                'url' => $category->hasUrlIn($locale) ? $category->url($locale) : null,
                'visible' => (bool) $category->is_visible,
                'services' => $this->rows(Service::query()->orderedIn((int) $category->getKey())->with('routes')->get()->all(), $locale),
            ])->values()->all(),
            'uncategorised' => $this->rows(
                Service::query()->whereDoesntHave('categories')->orderedIn()->with('routes')->get()->all(),
                $locale,
            ),
        ];
    }

    /**
     * @param  list<Service>  $services
     * @return list<array<string, mixed>>
     */
    private function rows(array $services, string $locale): array
    {
        return array_map(static function (Service $service) use ($locale): array {
            $shown = $service->hasDraft() ? $service->withDraft() : $service;

            return [
                'id' => (int) $service->getKey(),
                'title' => (string) $shown->getTranslation('title', $locale),
                'url' => $service->hasUrlIn($locale) ? $service->url($locale) : null,
                'status' => $service->status(),
            ];
        }, $services);
    }
}
