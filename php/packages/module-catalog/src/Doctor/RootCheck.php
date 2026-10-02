<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * The root of the catalogue against the pages that were there before it (§4).
 *
 * The root is a route, and a route wins before the registry's fallback is reached: switched on
 * over a page called `catalog`, it hides that page and everything under its address. A page saved
 * after the switch is refused by `Reserved`; one saved before it is not, and only `webx:routes:check`
 * would notice. The root is the setting somebody turned on, so it stays the winner — what is
 * said here is which addresses it took, and the two ways out.
 */
final class RootCheck implements Check
{
    /** Enough to recognise the page; the rest is in `webx:routes:check`. */
    private const SHOWN = 5;

    public function __construct(private readonly Config $config) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        if (! (bool) $this->config->get('webx-catalog.root.enabled', false)) {
            return [];
        }

        $prefix = UrlNormaliser::key((string) $this->config->get('webx-catalog.root.prefix', 'catalog'));

        if ($prefix === '') {
            return [];
        }

        /** @var list<string> $taken */
        $taken = Route::query()
            ->canonical()
            ->where(static fn (Builder $query): Builder => $query->where('path', $prefix)->orWhere('path', 'like', $prefix.'/%'))
            ->orderBy('path')
            ->limit(self::SHOWN)
            ->pluck('path')
            ->all();

        if ($taken === []) {
            return [Diagnosis::ok('Catalogue root', "/{$prefix} is the catalogue, and no page of the registry stands there.")];
        }

        $addresses = implode(', ', array_map(static fn (string $path): string => '/'.$path, $taken));

        return [Diagnosis::warn('Catalogue root', "/{$prefix} is the catalogue, so these addresses never open: {$addresses} — give them another address, or move the root with WEBX_CATALOG_ROOT_PREFIX.")];
    }
}
