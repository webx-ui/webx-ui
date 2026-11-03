<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;

/**
 * `NotificationEvents::extend()` (WEBX_UI_MODULE_PAYMENTS.md §11.5): another module adds variables
 * to an event it does not own. They are the event's for the editor and the examples, their values
 * come from the subject at `send()`, a name of the owner is never replaced, and a failing
 * extension leaves its variables empty instead of failing the message.
 */
final class ExtendTest extends TestCase
{
    #[Test]
    public function the_variables_of_an_extension_are_the_events_own_for_the_editor_and_the_examples(): void
    {
        $this->extendShop(static fn (): array => ['pay_url' => 'https://example.test/pay/1']);

        $event = $this->events()->find('shop.order');
        $this->assertNotNull($event);
        $this->assertContains('pay_url', array_map(static fn (Variable $variable): string => $variable->name, $event->variables));
        $this->assertSame('https://example.test/pay/0', $event->examples()['pay_url']);
        $this->assertTrue($event->knows('pay_url'));
        $this->assertContains('pay_url', array_column($event->toArray()['variables'], 'name'));
        $this->assertContains('pay_url', array_column($this->events()->all()['shop.order']->toArray()['variables'], 'name'));
    }

    #[Test]
    public function an_event_registered_after_the_extension_is_extended_too(): void
    {
        $this->events()->extend(['late.*'], [new Variable('extra', 'Extra', example: 'x')], static fn (): array => ['extra' => 'y']);
        $this->events()->register(new EventDefinition('late.one', 'Late', 'late', [new Variable('number', 'Number')]));

        $this->assertSame(['number', 'extra'], array_map(static fn (Variable $one): string => $one->name, $this->events()->find('late.one')->variables ?? []));
        $this->assertNull($this->events()->find('other.one'));
    }

    #[Test]
    public function a_name_the_owner_declared_is_not_replaced_whichever_comes_first(): void
    {
        try {
            $this->events()->extend(['shop.*'], [new Variable('number', 'Number again')], static fn (): array => []);
            $this->fail('An extension replaced the owner\'s variable.');
        } catch (InvalidArgumentException $refused) {
            $this->assertStringContainsString('[number]', $refused->getMessage());
        }

        $this->events()->extend(['later.*'], [new Variable('total', 'Total')], static fn (): array => []);
        $this->expectException(InvalidArgumentException::class);
        $this->events()->register(new EventDefinition('later.one', 'Later', 'later', [new Variable('total', 'Total')]));
    }

    #[Test]
    public function send_takes_the_values_of_the_extension_from_the_subject_and_the_consumers_own_win(): void
    {
        Bus::fake();
        $seen = [];
        $this->extendShop(static function (?Model $subject, string $event) use (&$seen): array {
            $seen[] = [$subject?->getKey(), $event];

            return ['pay_url' => 'https://example.test/pay/'.$subject?->getKey()];
        });
        $channel = $this->telegram(['template' => ['en' => '{{number}} {{pay_url}}']]);

        $this->notifications()->send('shop.order', [$channel->id], ['number' => '7'], subject: $channel);

        $this->assertSame([[$channel->id, 'shop.order']], $seen);
        $row = Delivery::query()->sole();
        $this->assertSame(['pay_url' => 'https://example.test/pay/'.$channel->id, 'number' => '7'], $row->variables);
        $this->assertSame('7 https://example.test/pay/'.$channel->id, $this->notifications()->render($channel, ['number' => '7'], 'en', $channel, 'shop.order')['plain']);
    }

    #[Test]
    public function a_failing_extension_leaves_its_variables_empty_and_the_message_goes(): void
    {
        Bus::fake();
        $log = Log::spy();
        $this->extendShop(static fn (): array => throw new RuntimeException('the module of payments is broken'));
        $channel = $this->telegram();

        $dispatch = $this->notifications()->send('shop.order', [$channel->id], ['number' => '8'], subject: $channel);

        $this->assertCount(1, $dispatch->queued);
        $this->assertSame('', Delivery::query()->sole()->variables['pay_url']);
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, '[shop.order]') && str_contains($message, 'broken'))->once();
    }

    #[Test]
    public function a_message_by_hand_carries_the_extension_too(): void
    {
        Bus::fake();
        $this->extendShop(static fn (): array => ['pay_url' => 'https://example.test/pay/by-hand']);
        $channel = $this->email(['recipients' => [['variable' => 'customer_email']]]);

        $rows = $this->notifications()->sendByHand('shop.order', [$channel->id], 'customer_email', ['number' => '9', 'customer_email' => 'buyer@example.com'], $channel);

        $this->assertSame('https://example.test/pay/by-hand', $rows[0]->variables['pay_url']);
        $this->assertInstanceOf(Channel::class, $channel);
    }

    /** @param  callable(?Model, string): array<string, mixed>  $values */
    private function extendShop(callable $values): void
    {
        $this->events()->extend(['shop.*'], [new Variable('pay_url', 'Pay link', example: 'https://example.test/pay/0')], $values);
    }

    private function events(): NotificationEvents
    {
        return $this->app->make(NotificationEvents::class);
    }

    private function notifications(): Notifications
    {
        return $this->app->make(Notifications::class);
    }
}
