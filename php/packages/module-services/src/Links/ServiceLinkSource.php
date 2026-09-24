<?php

declare(strict_types=1);

namespace WebxUi\Services\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * Services, for whatever points at an entity: a menu entry, a link field, a block (§3 of the menu
 * spec). The hint is the categories, which is what tells two services called "Consultation" apart.
 */
final class ServiceLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'service';
    }

    public function model(): string
    {
        return Service::class;
    }

    public function title(): string
    {
        return (string) __('webx-services::module.services');
    }

    public function icon(): string
    {
        return 'grid';
    }

    public function order(): int
    {
        return 220;
    }

    public function permission(): string
    {
        return 'services.view';
    }

    public function search(string $query, string $locale, int $limit): array
    {
        $services = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->where(static function (Builder $nested) use ($query): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
            }))
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($services, $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Service>
     */
    private function query(string $locale): Builder
    {
        return Service::query()->with([
            'categories',
            'routes' => static fn ($routes) => $routes
                ->where('locale', $locale)
                ->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Service>  $services
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $services, string $locale): array
    {
        $candidates = [];

        foreach ($services as $service) {
            $id = (int) $service->getKey();
            $canonical = $service->routes->first();
            $categories = $service->categories
                ->map(static fn (ServiceCategory $category): string => $category->displayName($locale))
                ->all();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: self::name($service, $locale),
                url: $canonical instanceof Route ? $service->urlOf($canonical->path, $locale) : null,
                available: $service->isPublished() && $canonical instanceof Route,
                hint: $categories === [] ? null : implode(', ', $categories),
            );
        }

        return $candidates;
    }

    private static function name(Service $service, string $locale): string
    {
        foreach (['title', 'slug'] as $attribute) {
            $candidate = $service->getTranslation($attribute, $locale);

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$service->getKey();
    }
}
