<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Payments\Demo\PaymentsDemo;

/**
 * The first entry of «Payments» — the methods — which also seeds the section's demo: the two
 * built-in methods switched on, under the module's own id `payments`.
 */
class PaymentsHome extends PaymentsSection implements ProvidesDemo
{
    public function __construct(private readonly PaymentsDemo $demo)
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
