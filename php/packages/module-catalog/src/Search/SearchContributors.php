<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Search;

/**
 * Every {@see SearchContributor}, in the order they were registered.
 */
final class SearchContributors
{
    /** @var list<SearchContributor> */
    private array $contributors = [];

    public function register(SearchContributor $contributor): void
    {
        $this->contributors[] = $contributor;
    }

    /** @return list<SearchContributor> */
    public function all(): array
    {
        return $this->contributors;
    }
}
