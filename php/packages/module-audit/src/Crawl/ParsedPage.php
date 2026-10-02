<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

/**
 * What one HTML page says about itself, read once by {@see PageParser}: the columns of
 * `audit_pages`, the facts the page checks need beyond them, and every address it points at.
 */
final readonly class ParsedPage
{
    /**
     * @param  array<string, mixed>  $columns  The markup columns of `audit_pages`, ready to store.
     * @param  array<string, mixed>  $facts  The `facts` column.
     * @param  list<FoundLink>  $links
     */
    public function __construct(
        public array $columns,
        public array $facts,
        public array $links,
    ) {}
}
