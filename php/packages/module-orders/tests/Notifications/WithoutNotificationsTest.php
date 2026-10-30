<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Notifications;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Orders\Doctor\OrdersNotificationsCheck;
use WebxUi\Orders\Notifications\DefaultChannels;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\Panel\SettingsScreen;
use WebxUi\Orders\Tests\PlaceTestCase;

/**
 * Without `webx-ui/module-notifications` (decision 29): the order is placed and moved as ever,
 * nobody is told, no channel is made, the settings offer none — and `webx:doctor` says so.
 */
final class WithoutNotificationsTest extends PlaceTestCase
{
    #[Test]
    public function the_order_stands_and_the_doctor_says_nobody_is_told(): void
    {
        $order = $this->place($this->cart());

        $this->assertNotNull($order->number);
        $this->assertFalse($this->app->make(OrderChannels::class)->available());
        $this->assertSame([], $this->app->make(DefaultChannels::class)->install());
        $this->assertSame([], $this->app->make(OrderSettings::class)->get('orders.notify.placed'));

        $this->assertContains(OrdersNotificationsCheck::class, $this->app->make(DoctorChecks::class)->all());

        $diagnoses = $this->app->make(OrdersNotificationsCheck::class)->run();

        $this->assertCount(1, $diagnoses);
        $this->assertSame('note', $diagnoses[0]->level());
        $this->assertStringContainsString('module-notifications is not installed', $diagnoses[0]->detail);

        // No module-settings either: the screen has nowhere to keep the channels, and no card for them.
        $targets = array_column($this->app->make(SettingsScreen::class)->patch(), 'target');
        $this->assertContains('notify-card', $targets);
    }
}
