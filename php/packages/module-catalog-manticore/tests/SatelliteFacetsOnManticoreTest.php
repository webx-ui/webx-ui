<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use WebxUi\Catalog\Engine\Indexer;

/**
 * The satellites' facets on a live Manticore, answering as the database does.
 */
final class SatelliteFacetsOnManticoreTest extends SatelliteFacetScenarios
{
    use UsesManticore;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->useManticore($app);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipWithoutManticore();
    }

    protected function tearDown(): void
    {
        $this->dropOwnTables();
        parent::tearDown();
    }

    protected function settle(): void
    {
        $this->app->make(Indexer::class)->run();
    }
}
