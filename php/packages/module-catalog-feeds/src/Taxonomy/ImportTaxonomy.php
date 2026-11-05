<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Taxonomy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use WebxUi\CatalogFeeds\Build\BuildFeed;

/**
 * An import by address. One try: a person is looking at the form, and an address that failed is
 * theirs to ask for again. The timeout is the job's own, past the fetch and under `retry_after`,
 * for the reason {@see BuildFeed} gives.
 */
final class ImportTaxonomy implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 80;

    public function __construct(public readonly int $taxonomyId, public readonly ?string $url = null) {}

    public function handle(TaxonomyImport $import): void
    {
        $import->run($this->taxonomyId, $this->url);
    }

    public function failed(?Throwable $failure): void
    {
        app(TaxonomyImport::class)->fail($this->taxonomyId, (string) $failure?->getMessage());
    }
}
