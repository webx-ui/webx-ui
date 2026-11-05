<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Taxonomy;

use RuntimeException;

/** A list that did not come in, in words for the person who asked; the one held stays. */
final class TaxonomyFailure extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function of(string $key, array $replace = []): self
    {
        return new self((string) __('webx-catalog-feeds::errors.'.$key, $replace));
    }
}
