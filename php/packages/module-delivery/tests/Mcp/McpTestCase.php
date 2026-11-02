<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Mcp;

use Illuminate\Foundation\Application;
use WebxUi\Cart\Purchasables;
use WebxUi\Delivery\Tests\Fakes\FakeGoods;
use WebxUi\Delivery\Tests\Panel\PanelTestCase;
use WebxUi\Mcp\McpServiceProvider;

/** The section by its other door (§15): the panel's providers and the MCP server. */
abstract class McpTestCase extends PanelTestCase
{
    use CallsTools;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Purchasables::class)->register('test.goods', new FakeGoods);
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // Without module-orders: the section and its tools work all the same, the parcels' aside (§19).
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }
}
