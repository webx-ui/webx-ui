<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Storefront;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Localization\Locales;

/**
 * The list of brands, `/brands/`: an ordinary route rather than a row of the registry, as the
 * catalogue's root is — so `Reserved` closes the address to pages. Every published brand with a
 * page in this language, in the order of the list.
 */
final class BrandsController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly Locales $locales,
    ) {}

    public function index(): Response
    {
        $locale = $this->locales->current();

        $brands = Brand::query()->visible()->scopes(['ordered'])->with(['logo', 'routes'])->get()
            ->filter(static fn (Brand $brand): bool => $brand->hasUrlIn($locale))
            ->values();

        return response($this->views->make('webx-catalog-brands::brands', ['brands' => $brands, 'locale' => $locale])->render());
    }
}
