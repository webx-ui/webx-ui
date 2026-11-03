<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeDecision;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\JournalUnavailable;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\Exchange\SignatureCheck;
use WebxUi\Payments\Models\PaymentGatewayEvent;

/**
 * The journal (spec §20.2–§20.4): written on its own connection, so a rolled-back status change
 * keeps its event; a body kept up to the limit with its whole size and hash; a failed write logged
 * and survived, except where the event must come first.
 */
final class ExchangeJournalTest extends FileDatabaseTestCase
{
    #[Test]
    public function the_journal_writes_through_its_own_connection(): void
    {
        $this->assertSame(ExchangeConnection::COPY, $this->app->make(ExchangeConnection::class)->name());
        $this->assertSame(
            DB::connection()->getDatabaseName(),
            DB::connection(ExchangeConnection::COPY)->getDatabaseName(),
            'A copy of the default connection: the same database, a second session.',
        );
    }

    #[Test]
    public function an_event_outlives_the_rolled_back_transaction_around_it(): void
    {
        $journal = $this->app->make(ExchangeJournal::class);

        try {
            DB::transaction(function () use ($journal): void {
                $journal->record($this->notification('{"status":"paid"}'), $this->secrets());

                // settle() fails half-way: its transaction goes, the event must stay.
                throw new RuntimeException('settle() failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, DB::transactionLevel());
        $event = PaymentGatewayEvent::query()->sole();
        $this->assertSame('{"status":"paid"}', $event->request_body);
        $this->assertSame('in', $event->direction);
        $this->assertSame('notify', $event->kind);
    }

    #[Test]
    public function the_raw_half_is_masked_and_the_decision_is_written_once(): void
    {
        $journal = $this->app->make(ExchangeJournal::class);
        $secrets = $this->secrets();

        $id = $journal->record(new ExchangeEntry(
            kind: ExchangeKind::Notify,
            trace: ExchangeContext::newTrace(),
            methodKey: 'gateway-a-card',
            httpMethod: 'POST',
            url: 'https://shop.example.com/payments/notify/gateway-a-card?key='.rawurlencode(self::SECRET),
            ip: '192.0.2.10',
            requestHeaders: ['Content-Type' => ['application/json'], 'Authorization' => ['Bearer '.self::SECRET]],
            requestBody: '{"key":"'.self::SECRET.'","signature":"c2ln"}',
        ), $secrets);

        $this->assertIsInt($id);

        $this->assertTrue($journal->decide($id, new ExchangeDecision(
            Outcome::Applied,
            paymentId: 7,
            orderId: 3,
            signature: SignatureCheck::Valid,
            reported: 'paid',
            statusBefore: 'pending',
            statusAfter: 'paid',
            eventKey: hash('sha256', 'body'),
            responseStatus: 200,
            responseHeaders: ['Content-Type' => ['text/plain']],
            responseBody: 'OK',
        ), $secrets));

        // Written once: a second decision changes nothing.
        $this->assertFalse($journal->decide($id, new ExchangeDecision(Outcome::Error, message: 'late'), $secrets));

        $event = PaymentGatewayEvent::query()->findOrFail($id);
        $this->assertSame('https://shop.example.com/payments/notify/gateway-a-card?key=[secret:secret_key]', $event->url);
        $this->assertSame(['[secret:header]'], $event->request_headers['Authorization'] ?? null);
        $this->assertSame('{"key":"[secret:secret_key]","signature":"c2ln"}', $event->request_body);
        $this->assertSame(['header:authorization' => 1, 'secret:secret_key' => 2], $event->masked);
        $this->assertSame('applied', $event->outcome);
        $this->assertSame('valid', $event->signature);
        $this->assertSame(7, $event->payment_id);
        $this->assertSame(200, $event->response_status);
        $this->assertSame('OK', $event->response_body);
        $this->assertSame(2, $event->response_size);
        $this->assertNull($event->message);
    }

    #[Test]
    public function a_body_over_the_limit_is_cut_and_keeps_its_whole_size_and_hash(): void
    {
        $body = str_repeat('a', 1024 * 1024).'tail'.self::SECRET;
        $masked = str_repeat('a', 1024 * 1024).'tail[secret:secret_key]';

        $id = $this->app->make(ExchangeJournal::class)->record($this->notification($body), $this->secrets());

        $event = PaymentGatewayEvent::query()->findOrFail($id);
        $this->assertTrue($event->request_truncated);
        $this->assertSame(1024 * 1024, strlen((string) $event->request_body));
        $this->assertSame(strlen($masked), $event->request_size);
        $this->assertSame(hash('sha256', $masked), $event->request_sha256);
        $this->assertSame(['secret:secret_key' => 1], $event->masked);
    }

    #[Test]
    public function a_body_within_the_limit_is_kept_whole(): void
    {
        $body = str_repeat('b', 1024 * 1024);

        $id = $this->app->make(ExchangeJournal::class)->record($this->notification($body), $this->secrets());

        $event = PaymentGatewayEvent::query()->findOrFail($id);
        $this->assertFalse($event->request_truncated);
        $this->assertSame($body, $event->request_body);
        $this->assertSame(hash('sha256', $body), $event->request_sha256);
    }

    #[Test]
    public function a_failed_write_is_logged_and_survived(): void
    {
        $log = Log::spy();
        $this->app['config']->set('webx-payments.exchange.connection', 'nowhere');
        $this->app->forgetInstance(ExchangeConnection::class);
        $this->app->forgetInstance(ExchangeJournal::class);
        $this->app['config']->set('database.connections.nowhere', ['driver' => 'sqlite', 'database' => '/nonexistent/dir/db.sqlite']);

        $journal = $this->app->make(ExchangeJournal::class);

        $this->assertNull($journal->record($this->notification('{"key":"'.self::SECRET.'"}'), $this->secrets()));
        $this->assertFalse($journal->decide(1, new ExchangeDecision(Outcome::Applied), $this->secrets()));

        $log->shouldHaveReceived('error')->withArgs(static fn (string $message, array $context): bool => $message === 'webx-payments: journal write failed'
            && ! str_contains((string) json_encode($context), self::SECRET))->twice();

        // An incoming notification is not read without its event (decision 48): the controller answers 503.
        $this->expectException(JournalUnavailable::class);
        $journal->recordOrFail($this->notification('{}'), $this->secrets());
    }

    private function notification(string $body): ExchangeEntry
    {
        return new ExchangeEntry(
            kind: ExchangeKind::Notify,
            trace: ExchangeContext::newTrace(),
            gateway: 'gateway-a',
            methodKey: 'gateway-a-card',
            source: 'notify',
            httpMethod: 'POST',
            url: 'https://shop.example.com/payments/notify/gateway-a-card',
            ip: '192.0.2.10',
            requestHeaders: ['Content-Type' => ['application/json']],
            requestBody: $body,
        );
    }
}
