<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

/**
 * An engine that builds a rebuilt index beside the live one and swaps them when it is whole
 * (decision 8 of the Manticore spec): the storefront reads the old index all the while, instead
 * of an empty one filling up.
 *
 * {@see CatalogEngine::prepare()} with `$rebuild` starts the new one, every {@see CatalogEngine::index()}
 * of the rebuild writes into it, and {@see completeRebuild()} puts it in place. A rebuild that
 * fails half-way never reaches the swap, and the live index stays as it was.
 */
interface RebuildsAside extends CatalogEngine
{
    public function completeRebuild(): void;
}
