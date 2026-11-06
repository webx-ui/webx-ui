<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

/**
 * What became of a page of products, whoever rendered it: a build keeps it in the journal, a trial
 * shows it to the person at the form.
 */
final readonly class FeedPage
{
    /**
     * @param  array<int, string>  $rejected  product id => the reason it stays out
     * @param  list<int>  $filtered  the products `only_in_stock` left out
     * @param  array<string, int>  $warnings
     * @param  list<int>  $categories  of what was written
     * @param  array<int, list<string>>  $labels  label => the values it took here, each once
     */
    public function __construct(
        public array $rejected,
        public array $filtered,
        public array $warnings,
        public array $categories,
        public array $labels = [],
    ) {}

    /**
     * @return array<string, int>
     */
    public function reasons(): array
    {
        return array_count_values($this->rejected);
    }
}
