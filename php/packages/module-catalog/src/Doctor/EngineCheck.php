<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Product;

/**
 * Two things about the catalogue nothing else announces (§8.2, §8.3).
 *
 * `SqlEngine` does not break past a couple of thousand products — it slows, a filter at a time,
 * until the category page is the slowest page of the site and nobody can say when that started.
 * The threshold is `sql_engine_limit`, and past it the answer is Manticore.
 *
 * An engine with an index of its own can fall over without a save noticing (§5.2 of the
 * architecture): the queue just grows. Its length and the age of its oldest mark are what show it.
 */
final class EngineCheck implements Check
{
    /** A mark older than this means the worker is not running, not that it is busy. */
    private const STALE_MINUTES = 15;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Config $config,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $engine = (string) $this->config->get('webx-catalog.engine', 'sql');

        if (! $this->catalog->needsIndex()) {
            $limit = (int) $this->config->get('webx-catalog.sql_engine_limit', 2000);
            $live = Product::query()->count();

            return [$live > $limit
                ? Diagnosis::warn('Catalogue engine', "{$live} live products on the database engine, past its limit of {$limit} — install webx-ui/module-catalog-manticore and set WEBX_CATALOG_ENGINE=manticore.")
                : Diagnosis::ok('Catalogue engine', "the database, with {$live} live products of the {$limit} it is meant for.")];
        }

        $queue = $this->catalog->queue();

        if ($queue['waiting'] === 0) {
            return [Diagnosis::ok('Catalogue engine', "[{$engine}], and nothing waits for it.")];
        }

        $oldest = $queue['oldest'];
        $age = $oldest === null ? 0 : (int) $oldest->diffInMinutes(Carbon::now());

        return [$age > self::STALE_MINUTES
            ? Diagnosis::warn('Catalogue engine', "[{$engine}]: {$queue['waiting']} products wait, the oldest for {$age} minutes — is the scheduler running `webx:catalog:index`, and does the engine answer?")
            : Diagnosis::ok('Catalogue engine', "[{$engine}]: {$queue['waiting']} products on their way to the index.")];
    }
}
