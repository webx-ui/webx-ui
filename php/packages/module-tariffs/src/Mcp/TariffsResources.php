<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Tariffs\Currencies;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\Panel\TariffNames;
use WebxUi\Tariffs\Variants;

/**
 * What an agent reads before it writes a tariff (§5.5): every tariff in one message.
 *
 * The currencies and the button looks of the site head it, because anything else is refused. Then
 * every group, hidden ones too, each with its tariffs in that group's own order, and the tariffs
 * in no group at the end. Unpublished tariffs are in it and say so — the point of reading this
 * first is not to type "Starter" in a second time beside the copy that is not out yet. A tariff in
 * two groups is listed under both: that is where a reader meets it.
 *
 * One name and one price line per row, in the language the agent works in; `written_in` says
 * where the name and the description are, and `tariffs_get` has every language of the one it
 * picks.
 */
final class TariffsResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'tariffs://catalog',
                'Tariffs catalog',
                'The currencies and button looks this site accepts, then every tariff group in its order with its '
                .'tariffs in that group\'s own order — the name, the price in one line, whether each is published '
                .'or recommended, the languages its name and description are written in, the services it is linked '
                .'to — and the tariffs in no group at the end. Tariffs have no page of their own: a "Tariffs" block '
                .'on a page, or tariffs() in a template of the site, prints them. Read it before adding a tariff or '
                .'a group, so that you reuse rather than duplicate.',
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

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'currencies' => $this->container->make(Currencies::class)->all(),
            'variants' => $this->container->make(Variants::class)->keys(),
            'groups' => TariffCategory::query()->ordered()->get()->map(fn (TariffCategory $group): array => [
                'id' => (int) $group->getKey(),
                'title' => $group->displayName($locale),
                'visible' => (bool) $group->is_visible,
                'tariffs' => $this->rows(Tariff::query()->orderedIn((int) $group->getKey())->get()->all(), $locales),
            ])->values()->all(),
            'ungrouped' => $this->rows(
                Tariff::query()->whereDoesntHave('categories')->orderedIn()->get()->all(),
                $locales,
            ),
        ];
    }

    /**
     * @param  list<Tariff>  $tariffs
     * @return list<array<string, mixed>>
     */
    private function rows(array $tariffs, Locales $locales): array
    {
        $currencies = $this->container->make(Currencies::class);
        $services = $this->container->make(RelationTargets::class)->find('service') !== null;
        $codes = $locales->codes();

        return array_map(static function (Tariff $tariff) use ($locales, $currencies, $services, $codes): array {
            $row = [
                'id' => (int) $tariff->getKey(),
                'name' => TariffNames::of($tariff, $locales),
                'price' => PriceLine::of($tariff, $currencies, $locales->current(), $locales->defaultCode()),
                'published' => $tariff->published,
                'featured' => $tariff->featured,
                'written_in' => TariffTools::writtenIn($tariff, $codes),
            ];

            if ($services) {
                $row['services'] = $tariff->relatedIds(Tariff::SERVICES);
            }

            return $row;
        }, $tariffs);
    }
}
