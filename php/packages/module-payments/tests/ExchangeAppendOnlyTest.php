<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Exchange\AppendOnly;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Models\PaymentGatewayEvent;

/**
 * The journal is only added to (decision 31): no way through the model changes the raw half of an
 * event or deletes one — a row, a query, a mass update or a truncate. Routes and tools are held by
 * the panel's and MCP's stages; removal is `webx:payments:exchange-prune` alone.
 */
final class ExchangeAppendOnlyTest extends TestCase
{
    /** @return iterable<string, array{Closure(PaymentGatewayEvent): mixed}> */
    public static function writes(): iterable
    {
        yield 'save a changed body' => [static function (PaymentGatewayEvent $event): void {
            $event->forceFill(['request_body' => 'changed'])->save();
        }];
        yield 'update a row' => [static fn (PaymentGatewayEvent $event): bool => $event->forceFill(['outcome' => 'applied'])->save()];
        yield 'update through the model' => [static fn (PaymentGatewayEvent $event): bool => $event->update(['message' => 'x'])];
        yield 'increment' => [static fn (PaymentGatewayEvent $event): int => $event->increment('response_status')];
        yield 'delete a row' => [static fn (PaymentGatewayEvent $event): ?bool => $event->delete()];
        yield 'destroy by id' => [static fn (PaymentGatewayEvent $event): int => PaymentGatewayEvent::destroy($event->id)];
        yield 'mass update' => [static fn (): int => PaymentGatewayEvent::query()->where('kind', 'notify')->update(['outcome' => 'applied'])];
        yield 'mass delete' => [static fn (): mixed => PaymentGatewayEvent::query()->where('kind', 'notify')->delete()];
        yield 'truncate' => [static function (): void {
            PaymentGatewayEvent::truncate();
        }];
        yield 'create a row' => [static fn (): PaymentGatewayEvent => PaymentGatewayEvent::query()->create(['kind' => 'notify'])];
        yield 'insert rows' => [static fn (): bool => PaymentGatewayEvent::query()->insert([['kind' => 'notify']])];
    }

    /** @param  Closure(PaymentGatewayEvent): mixed  $write */
    #[Test]
    #[DataProvider('writes')]
    public function nothing_through_the_model_changes_or_removes_an_event(Closure $write): void
    {
        $id = (int) $this->app->make(ExchangeJournal::class)->record(new ExchangeEntry(
            kind: ExchangeKind::Notify,
            trace: ExchangeContext::newTrace(),
            requestBody: 'raw',
        ), $this->secrets());
        $event = PaymentGatewayEvent::query()->findOrFail($id);

        try {
            $write($event);
            $this->fail('The journal took a write that is not its own.');
        } catch (AppendOnly) {
        }

        $after = PaymentGatewayEvent::query()->sole();
        $this->assertSame('raw', $after->request_body);
        $this->assertNull($after->outcome);
        $this->assertNull($after->message);
    }
}
