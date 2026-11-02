<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Delivery\Demo\DeliveryDemo;

/**
 * The first entry of «Delivery» — the methods — which also seeds the section's demo (§18): one
 * demo for the six entries, under the module's own id `delivery`, so `webx:demo --module=delivery`
 * reads the way the menu does.
 */
class DeliveryHome extends DeliverySection implements ProvidesDemo
{
    public function __construct(private readonly DeliveryDemo $demo)
    {
        parent::__construct(self::GROUP);
    }

    /** @return list<string> */
    public function requires(): array
    {
        return [];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }
}
