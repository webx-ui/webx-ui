<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Testing\FakeGateway;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * `webx:demo --module=payments` (spec §12, §21): the two built-in methods on, and — the fake
 * gateway being on here — gateway A's three methods with its test key; `--remove` switches the
 * built-in ones off and takes away the methods it made.
 */
final class DemoTest extends PanelCase
{
    #[Test]
    public function the_demo_switches_the_shop_and_gateway_a_on_and_remove_takes_it_back(): void
    {
        // A site that has never had gateway A's methods.
        PaymentMethod::query()->whereIn('key', FakeGatewaySite::METHODS)->delete();
        $module = $this->app->make(ModuleRegistry::class)->get('payments');
        $this->assertInstanceOf(ProvidesDemo::class, $module);
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('payments');

        $module->seed($ledger);

        $methods = PaymentMethod::query()->orderBy('position')->get()->keyBy('key');
        $this->assertTrue($methods[OnReceipt::METHOD]->enabled);
        $this->assertTrue($methods[BankTransfer::METHOD]->enabled);
        foreach (FakeGatewaySite::METHODS as $type => $key) {
            $this->assertSame([$type, true, FakeGateway::SECRET], [$methods[$key]->type, $methods[$key]->enabled, $methods[$key]->credentials['secret_key'] ?? null]);
        }

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, PaymentMethod::query()->whereIn('key', FakeGatewaySite::METHODS)->count());
        $this->assertFalse(PaymentMethod::query()->where('key', OnReceipt::METHOD)->sole()->enabled);
        $this->assertFalse(PaymentMethod::query()->where('key', BankTransfer::METHOD)->sole()->enabled);
    }

    #[Test]
    public function gateway_a_methods_the_site_had_come_back_as_they_were(): void
    {
        PaymentMethod::query()->whereIn('key', FakeGatewaySite::METHODS)->update(['enabled' => false]);
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('payments');
        $module = $this->app->make(ModuleRegistry::class)->get('payments');
        $this->assertInstanceOf(ProvidesDemo::class, $module);

        $module->seed($ledger);
        $this->assertSame(3, PaymentMethod::query()->whereIn('key', FakeGatewaySite::METHODS)->where('enabled', true)->count());

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $kept = PaymentMethod::query()->whereIn('key', FakeGatewaySite::METHODS)->get();
        $this->assertCount(3, $kept);
        $this->assertTrue($kept->every(static fn (PaymentMethod $method): bool => ! $method->enabled && ($method->credentials['secret_key'] ?? null) === FakeGateway::SECRET));
    }
}
