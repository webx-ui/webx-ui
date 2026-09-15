<?php

declare(strict_types=1);

namespace WebxUi\Routing\Aliases;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The trail every rename leaves behind, for whoever shows it.
 *
 * `module-seo` puts these on the redirects screen beside the rules an editor wrote by hand, and
 * this narrow thing is all it gets: a page of rows and a filter. It has no business creating an
 * alias, deleting one, or knowing that `routes` is a table — aliases are made and unmade by the
 * entity they belong to, and a panel that could edit them would be a panel that can make the
 * registry disagree with the site.
 */
interface RouteAliases
{
    /**
     * @param  string|null  $term  Matched against the old address and the address it leads to.
     * @param  string|null  $locale  One language, or every one of them.
     * @return LengthAwarePaginator<int, Alias>
     */
    public function search(?string $term = null, ?string $locale = null, int $perPage = 25, int $page = 1): LengthAwarePaginator;
}
