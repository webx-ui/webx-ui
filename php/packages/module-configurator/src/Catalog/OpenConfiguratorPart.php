<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Screens\Types\LinkType;
use WebxUi\Catalog\Storefront\StorefrontPart;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Site\LiveConfigurators;

/**
 * «Calculate in the configurator» on the page of a product (`catalog.product.aside`, §22.3): a link
 * to the page the configurator stands on, with the product's preset in the address.
 *
 * Printed only when all of it is there: the product names a configurator, the configurator is on
 * the site, and its page is set and open — a button to a page that is not there is worse than none.
 * One query for the products of the page; the configurator and its page come from the cache of
 * published configurators and the link's own resolution.
 */
final class OpenConfiguratorPart implements StorefrontPart
{
    public function __construct(
        private readonly LiveConfigurators $live,
        private readonly LinkType $links,
    ) {}

    public function point(): string
    {
        return 'catalog.product.aside';
    }

    public function view(): string
    {
        return 'webx-configurator::open';
    }

    public function prepare(Collection $products): array
    {
        $rows = DB::table(Configurator::LINKS)->whereIn('product_id', $products->modelKeys())->get();

        if ($rows->isEmpty()) {
            return ['configurators' => []];
        }

        $locale = app()->getLocale();
        $pages = [];
        $open = [];

        foreach ($rows as $row) {
            $key = (string) $row->configurator;
            $pages[$key] ??= $this->page($key, $locale);

            if ($pages[$key] === null) {
                continue;
            }

            $decoded = is_string($row->preset) ? json_decode($row->preset, true) : null;

            $open[(int) $row->product_id] = [
                'key' => $key,
                'url' => PresetAddress::to($pages[$key], $key, ConfiguratorPart::preset($decoded)),
            ];
        }

        return ['configurators' => $open];
    }

    /**
     * The address of the configurator's page, or null while there is nowhere to send anybody: it
     * is not published, it has no page, or the page is not on the site.
     */
    private function page(string $key, string $locale): ?string
    {
        $live = $this->live->find($key);

        if ($live === null || ! is_array($live['page'] ?? null)) {
            return null;
        }

        $link = $this->links->resolve($live['page'], [], $locale);

        return is_array($link) && ($link['available'] ?? false) === true && is_string($link['url'] ?? null) && $link['url'] !== ''
            ? $link['url']
            : null;
    }
}
