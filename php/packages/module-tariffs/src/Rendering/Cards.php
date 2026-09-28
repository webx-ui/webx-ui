<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Rendering;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Tariffs\Currencies;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\Variants;

/**
 * A tariff as a template reads it — plain data, not the model (§4.1 of the tariffs spec).
 *
 *     id, anchor, categories   what every element of a `wx-collection` carries; the ids of its groups
 *     name, badge, period      in the language asked for, else in the default one; '' when none
 *     price_text               the same — printed when there is no number
 *     price, amount            the number or null, and the number as the language writes it ('' without)
 *     currency, symbol         the code or null, and its symbol — the code when the config lost it
 *     features                 the rows of "what is included" written in the language, in order
 *     description              plain text in the language asked for, else ''
 *     button                   label, url, new_tab, rel, variant — or null
 *     featured                 the "recommended" mark
 *     service_links            id, title, url of the services the tariff is for; [] without them
 *     fields                   the project's own fields (a patch on `tariffs.form`), by name
 *
 * Every card is built from what was loaded with the list, so a list of any length is the same few
 * queries: the relations once, the entities behind the buttons once per kind, the addresses of
 * the services once.
 */
final class Cards
{
    /** What a list of tariffs is loaded with, so that no card goes back to the database. */
    public const RELATIONS = ['categories'];

    public function __construct(
        private readonly Locales $locales,
        private readonly LinkUrls $urls,
        private readonly Currencies $currencies,
        private readonly Variants $variants,
    ) {}

    /**
     * @param  list<Tariff>  $tariffs
     * @return list<array<string, mixed>>
     */
    public function tariffs(array $tariffs, string $locale): array
    {
        $links = [];

        foreach ($tariffs as $tariff) {
            if (is_array($tariff->button_link)) {
                $links[] = Link::fromArray($tariff->button_link);
            }
        }

        $candidates = $this->urls->candidates($links, $locale);
        $this->loadServices($tariffs);

        $default = $this->locales->defaultCode();

        return array_map(fn (Tariff $tariff): array => $this->tariff($tariff, $locale, $default, $candidates), $tariffs);
    }

    /**
     * A group with the tariffs it lists, already chosen and ordered by the caller.
     *
     * @param  list<Tariff>  $tariffs
     * @return array<string, mixed>
     */
    public function category(TariffCategory $category, array $tariffs, string $locale): array
    {
        return [
            'id' => (int) $category->getKey(),
            'title' => $category->displayName($locale),
            'tariffs' => $this->tariffs($tariffs, $locale),
        ];
    }

    /**
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @return array<string, mixed>
     */
    private function tariff(Tariff $tariff, string $locale, string $default, array $candidates): array
    {
        $fields = [];

        foreach (array_keys((array) ($tariff->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $tariff->extra((string) $name, $locale);
        }

        $price = $tariff->price === null ? null : (float) $tariff->price;

        return [
            'id' => (int) $tariff->getKey(),
            'anchor' => 'tariff-'.$tariff->getKey(),
            'categories' => $tariff->relationLoaded('categories') ? $tariff->categoryIds() : [],
            'name' => $tariff->wordsIn('name', $locale, $default),
            'badge' => $tariff->wordsIn('badge', $locale, $default),
            'price' => $price,
            'amount' => Amount::format($price, $locale),
            'currency' => $tariff->currency,
            'symbol' => $this->currencies->symbol($tariff->currency),
            'period' => $tariff->wordsIn('period', $locale, $default),
            'price_text' => $tariff->wordsIn('price_text', $locale, $default),
            'features' => $tariff->featuresIn($locale),
            'description' => $tariff->textIn('description', $locale),
            'button' => $this->button($tariff, $locale, $candidates),
            'featured' => $tariff->featured,
            'service_links' => $this->serviceLinks($tariff, $locale),
            'fields' => $fields,
        ];
    }

    /**
     * The one button, or null: without a label in this language, without a link, or with a link
     * to something that is not on the site now (a page in the bin or in a draft) — a button to a
     * 404 is worse than none. The tariff stays either way.
     *
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @return array{label: string, url: string, new_tab: bool, rel: string|null, variant: string|null}|null
     */
    private function button(Tariff $tariff, string $locale, array $candidates): ?array
    {
        $label = $tariff->textIn('button_label', $locale);

        if ($label === '' || ! is_array($tariff->button_link)) {
            return null;
        }

        $link = Link::fromArray($tariff->button_link);
        $candidate = $link->entityType !== null && $link->entityId !== null
            ? ($candidates[$link->entityType][$link->entityId] ?? null)
            : null;

        if ($link->target === LinkTarget::Entity && ($candidate === null || ! $candidate->available)) {
            return null;
        }

        $url = $this->urls->hrefWith($link, $candidate, $locale);

        if ($url === null) {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
            'new_tab' => $link->newTab,
            'rel' => $link->relAttribute(),
            'variant' => $this->variant($tariff->button_variant),
        ];
    }

    /** A variant the site still has, else its first one (decision 11): a button outranks its look. */
    private function variant(?string $variant): ?string
    {
        return $variant !== null && $this->variants->has($variant) ? $variant : $this->variants->first();
    }

    /**
     * The services of the whole list in one go, and their addresses with them. Copied from the
     * team's cards, which copied the recipes': `module-admin` does not know the address registry
     * (§4.1 of the tariffs spec, "Итог T1" of the team spec).
     *
     * @param  list<Tariff>  $tariffs
     */
    private function loadServices(array $tariffs): void
    {
        if ($tariffs === []) {
            return;
        }

        Relations::load($tariffs, Tariff::SERVICES);

        $services = [];

        foreach ($tariffs as $tariff) {
            foreach ($tariff->related(Tariff::SERVICES) as $service) {
                $services[spl_object_id($service)] = $service;
            }
        }

        $services = array_values(array_filter($services, static fn (Model $service): bool => method_exists($service, 'routes')));

        if ($services !== []) {
            (new Collection($services))->loadMissing('routes');
        }
    }

    /** @return list<array{id: int, title: string, url: string}> */
    private function serviceLinks(Tariff $tariff, string $locale): array
    {
        $links = [];

        foreach ($tariff->related(Tariff::SERVICES, visible: true, locale: $locale) as $service) {
            if (method_exists($service, 'hasUrlIn') && ! $service->hasUrlIn($locale)) {
                continue;
            }

            $links[] = ['id' => (int) $service->getKey(), 'title' => $this->title($service, $locale), 'url' => $this->url($service, $locale)];
        }

        return $links;
    }

    /**
     * From the loaded registry rows when the list loaded them: `url()` would ask the registry again
     * for every card.
     */
    private function url(Model $entity, string $locale): string
    {
        if (! method_exists($entity, 'url') || ! method_exists($entity, 'urlOf')) {
            return '';
        }

        if (! $entity->relationLoaded('routes')) {
            return (string) $entity->url($locale);
        }

        /** @var \Illuminate\Support\Collection<int, Route> $routes */
        $routes = $entity->getRelation('routes');
        $row = $routes->first(
            static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL,
        );

        return $row instanceof Route ? (string) $entity->urlOf($row->path, $locale) : (string) $entity->url($locale);
    }

    private function title(Model $entity, string $locale): string
    {
        if (method_exists($entity, 'displayName')) {
            return (string) $entity->displayName($locale);
        }

        $value = method_exists($entity, 'getTranslation') ? $entity->getTranslation('title', $locale) : $entity->getAttribute('title');

        return is_string($value) && trim($value) !== '' ? $value : '#'.$entity->getKey();
    }
}
