<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Storefront;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Routing\MissHandler;

/**
 * A deleted brand's address, with or without a filter behind it: 410, as a deleted category's
 * is. Its rows left the registry with it, so only this knows the address was ever anybody's. A
 * hidden brand keeps its row and is its handler's 404.
 */
final class BrandMisses implements MissHandler
{
    public function miss(Request $request, string $locale, string $path): ?Response
    {
        $segments = explode('/', $path);

        if (count($segments) < 2 || $segments[0] !== Brand::prefix() || $segments[1] === '') {
            return null;
        }

        if (Brand::onlyTrashed()->whereTranslation('slug', $segments[1], $locale)->exists()) {
            throw new GoneHttpException;
        }

        return null;
    }
}
