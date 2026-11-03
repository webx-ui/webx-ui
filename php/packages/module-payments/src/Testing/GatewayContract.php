<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Contracts\CompletesApproval;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\InvalidNotification;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\PaymentLine;
use WebxUi\Payments\Reported;
use WebxUi\Payments\StartRequest;

/**
 * The contract of a gateway's satellite (spec §21.3), for its PHPUnit test on a Testbench case
 * with `module-payments` and the satellite installed — on the gateway's answers and notifications
 * recorded once, never on the live API:
 *
 *     final class GatewayContractTest extends TestCase
 *     {
 *         use GatewayContract;
 *
 *         protected function gateway(): Gateway { return $this->app->make(Gateways::class)->get('gateway-b'); }
 *         protected function methodTypes(): array { return [$this->app->make(CardType::class)]; }
 *         protected function credentials(ChargesOnline $type): Credentials { return Credentials::of([...]); }
 *         protected function answerStatus(…): void { Http::fake([... the recorded status answer ...]); }
 *         protected function notification(…): ?Request { return the recorded notification, spoiled as asked; }
 *         …
 *     }
 *
 * What the base trusts a satellite with, checked here rather than found on a client's site: the
 * keys of the gateway and its types; every request through `GatewayHttp` — one event `out` per
 * request, and not one key in the journal; a trusted notification checked by its signature, an
 * untrusted one never believed; a repeat with the same key; the gateway's answers; its statuses
 * mapped by its own table, an unknown one Pending; amounts without a float; a handoff without card
 * fields and a start with no more than `needs()` asked for; a hold captured in full and in part,
 * refused above itself and let go; an approval completed once; a gateway's error in words.
 *
 * @phpstan-require-extends TestCase
 */
trait GatewayContract
{
    /** The satellite's gateway as the site has it registered. */
    abstract protected function gateway(): Gateway;

    /**
     * The satellite's online types.
     *
     * @return list<ChargesOnline>
     */
    abstract protected function methodTypes(): array;

    /** Test keys of the gateway for a type — the ones the recorded answers were signed with. */
    abstract protected function credentials(ChargesOnline $type): Credentials;

    /** The next status request about the payment answers this status of the gateway. */
    abstract protected function answerStatus(ChargesOnline $type, Payment $payment, string $gatewayStatus): void;

    /**
     * The gateway's statuses and what they are — the satellite's table (§3.3 of the review).
     *
     * @return array<string, Reported>
     */
    abstract protected function statusTable(ChargesOnline $type): array;

    /**
     * A notification about the payment as the gateway posts it: `valid` — of a paid payment;
     * spoiled — `signature`, `amount`, `status` (the body changed under the old signature);
     * `unrelated` — an event that is not about a payment's status. Null — the gateway has no such.
     */
    abstract protected function notification(ChargesOnline $type, Payment $payment, string $variant): ?Request;

    /**
     * The next request answers an error of the gateway (a 5xx).
     *
     * @return string a piece of its body that the words for the panel must not carry
     */
    abstract protected function answerError(ChargesOnline $type, Payment $payment): string;

    /** The payment is held at the gateway for its whole amount (`CapturesPayments`). */
    protected function answerAuthorized(ChargesOnline $type, Payment $payment): void
    {
        $this->markTestIncomplete('A satellite with CapturesPayments answers a held payment: override answerAuthorized().');
    }

    /** The gateway approved the payment and waits for the shop (`CompletesApproval`). */
    protected function answerApproved(ChargesOnline $type, Payment $payment): void
    {
        $this->markTestIncomplete('A satellite with CompletesApproval answers an approved payment: override answerApproved().');
    }

    /** How many times the gateway charged the payment: null — the recording cannot say. */
    protected function charges(ChargesOnline $type, Payment $payment): ?int
    {
        return null;
    }

    /** Requests that reached the gateway's (recorded) API so far. */
    protected function requestsMade(): int
    {
        return count(Http::recorded());
    }

    /** The answers the gateway waits for, against the recorded samples. */
    protected function checkAnswers(ChargesOnline $type, Response $accept, Response $reject): void {}

    /** The method of the site for a type, as the base hands it over. */
    protected function method(ChargesOnline $type): Method
    {
        return new Method(
            key: str_replace('.', '-', $type->key()),
            type: $type->key(),
            titles: ['en' => 'Contract'],
            descriptions: [],
            icon: null,
            enabled: true,
            position: 0,
            deliveryRule: ['mode' => 'any'],
            minAmount: null,
            maxAmount: null,
            settings: [],
            hasCredentials: true,
        );
    }

    /** An attempt as the base keeps it — not saved: a type reads it, never writes it. */
    protected function payment(ChargesOnline $type, int $amount = 12345): Payment
    {
        $payment = new Payment;
        $payment->forceFill([
            'uuid' => (string) Str::ulid(),
            'order_id' => 0,
            'method_key' => $this->method($type)->key,
            'type' => $type->key(),
            'amount' => $amount,
            'currency' => $this->currency($type),
            'status' => 'pending',
            'hold' => false,
            'source' => 'contract',
        ]);

        return $payment;
    }

    #[Test]
    public function the_gateway_and_its_types_name_themselves(): void
    {
        $gateway = $this->gateway();

        $this->assertMatchesRegularExpression(Gateways::KEY, $gateway->key(), 'The key of a gateway: lowercase letters, digits and dashes, at most 32.');
        $this->assertNotSame('store', $gateway->key(), '«store» is the shop itself.');
        $this->assertNotSame('', trim($gateway->title('en')));
        $this->assertTrue($this->app->make(Gateways::class)->has($gateway->key()), 'The satellite registers its gateway (Gateways::register).');

        foreach ([...$gateway->settings(), ...$gateway->credentials()] as $field) {
            $this->assertIsString($field['name'] ?? null, 'Every field of the settings and the keys has a name.');
        }

        foreach ($gateway->credentials() as $field) {
            $this->assertTrue(($field['secret'] ?? false) === true, "The key field {$field['name']} is secret: true.");
        }

        $this->assertNotSame([], $this->methodTypes());

        foreach ($this->methodTypes() as $type) {
            $this->assertMatchesRegularExpression(MethodTypes::KEY, $type->key());
            $this->assertSame($gateway->key(), $type->provider(), "{$type->key()} is the gateway's own type.");
            $this->assertStringStartsWith($gateway->key().'.', $type->key());
            $this->assertTrue($this->app->make(MethodTypes::class)->has($type->key()), "The satellite registers {$type->key()} (MethodTypes::register).");
            $this->assertSame($gateway->key(), $this->app->make(GatewayCalls::class)->gateway($type)->key(), 'An online type comes with its gateway.');
        }
    }

    #[Test]
    public function every_request_goes_through_gateway_http_and_no_key_reaches_the_journal(): void
    {
        Http::preventStrayRequests();

        foreach ($this->methodTypes() as $type) {
            $payment = $this->payment($type);
            $before = $this->requestsMade();
            $events = $this->outEvents();

            $handoff = $type->start($this->method($type), $this->credentials($type), $this->startRequest($type, $payment), $this->http($type, ExchangeKind::Start, $payment));
            $payment->gateway_ref = $handoff->gatewayRef;
            $this->answerStatus($type, $payment, $this->paidStatus($type));
            $type->fetchStatus($this->method($type), $this->credentials($type), $payment, $this->http($type, ExchangeKind::Status, $payment));

            $this->assertSame(
                $this->requestsMade() - $before,
                $this->outEvents() - $events,
                "{$type->key()}: every request to the gateway is one event of the journal — a request past GatewayHttp is not.",
            );
        }

        $this->assertNoSecretInTheJournal();
    }

    #[Test]
    public function the_handoff_carries_no_card_and_start_takes_no_more_than_it_needs(): void
    {
        foreach ($this->methodTypes() as $type) {
            $payment = $this->payment($type);
            $request = $this->startRequest($type, $payment);
            $handoff = $type->start($this->method($type), $this->credentials($type), $request, $this->http($type, ExchangeKind::Start, $payment));

            $this->assertMatchesRegularExpression('#^https?://#', $handoff->url);

            foreach (array_keys($handoff->fields) as $name) {
                $this->assertFalse(Handoff::isCardField($name), "{$type->key()}: the form to the gateway has a card field «{$name}».");
            }

            $needs = $type->needs();
            $this->assertSame($needs->email, $request->customerEmail !== null);
            $this->assertSame($needs->phone, $request->customerPhone !== null);
            $this->assertSame($needs->name, $request->customerName !== null);
            $this->assertSame($needs->lines, $request->lines !== []);
        }
    }

    #[Test]
    public function a_trusted_notification_is_believed_only_signed_and_an_untrusted_one_never(): void
    {
        foreach ($this->methodTypes() as $type) {
            $payment = $this->payment($type);
            $this->answerStatus($type, $payment, $this->paidStatus($type));
            $read = fn (Request $request) => $type->readNotification($this->method($type), $this->credentials($type), $request, $this->http($type, ExchangeKind::Status, $payment));

            $valid = $this->notification($type, $payment, 'valid');
            $this->assertNotNull($valid, "{$type->key()}: a notification of a paid payment is recorded.");
            $notification = $read($valid);

            if (! $type->trustsNotifications()) {
                $this->assertTrue($notification->isPoke(), "{$type->key()} does not trust its notifications: only a reason to ask (decision 33).");

                foreach (['signature', 'amount', 'status'] as $variant) {
                    $spoiled = $this->notification($type, $payment, $variant);

                    if ($spoiled !== null) {
                        $this->assertTrue($read($spoiled)->isPoke(), "{$type->key()}: «{$variant}» spoiled is still only a poke — its status is never applied.");
                    }
                }

                continue;
            }

            $this->assertTrue($notification->isStatus(), "{$type->key()}: a signed notification carries a status.");
            $this->assertSame($payment->uuid, $notification->uuid, "{$type->key()}: the notification names our attempt.");
            $this->assertSame(Reported::Paid, $notification->status?->status);

            foreach (['signature', 'amount', 'status'] as $variant) {
                $spoiled = $this->notification($type, $payment, $variant);
                $this->assertNotNull($spoiled, "{$type->key()}: a notification spoiled by «{$variant}» is recorded.");

                try {
                    $read($spoiled);
                    $this->fail("{$type->key()}: a notification with a spoiled {$variant} was believed.");
                } catch (InvalidNotification) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    #[Test]
    public function a_repeat_has_the_same_key_and_an_event_about_something_else_is_unrelated(): void
    {
        foreach ($this->methodTypes() as $type) {
            $payment = $this->payment($type);
            $this->answerStatus($type, $payment, $this->paidStatus($type));
            $read = fn (Request $request) => $type->readNotification($this->method($type), $this->credentials($type), $request, $this->http($type, ExchangeKind::Status, $payment));

            $first = $read($this->notificationOf($type, $payment, 'valid'));
            $second = $read($this->notificationOf($type, $payment, 'valid'));
            $this->assertSame($first->eventKey, $second->eventKey, "{$type->key()}: the same notification twice has the same key.");
            $this->assertNotSame('', $first->eventKey);

            $other = $this->notification($type, $payment, 'unrelated');

            if ($other !== null) {
                $this->assertTrue($read($other)->isUnrelated(), "{$type->key()}: an event not about a payment is unrelated, not an error.");
            }

            $accept = $type->acceptResponse($this->method($type), $this->credentials($type), $first);
            $reject = $type->rejectResponse($this->method($type), $this->credentials($type), InvalidNotification::signature());
            $this->assertTrue($accept->isSuccessful(), "{$type->key()}: an accepted notification is answered 2xx.");
            $this->assertGreaterThanOrEqual(200, $reject->getStatusCode());
            $this->checkAnswers($type, $accept, $reject);
        }
    }

    #[Test]
    public function the_gateways_statuses_map_by_its_table_and_an_unknown_one_is_pending(): void
    {
        foreach ($this->methodTypes() as $type) {
            $table = $this->statusTable($type);
            $this->assertNotSame([], $table);
            $this->assertContains(Reported::Paid, $table, "{$type->key()}: some status of the gateway is «paid».");

            foreach ([...$table, 'contract-unknown-status' => Reported::Pending] as $gatewayStatus => $expected) {
                $payment = $this->payment($type);
                $this->answerStatus($type, $payment, (string) $gatewayStatus);
                $status = $type->fetchStatus($this->method($type), $this->credentials($type), $payment, $this->http($type, ExchangeKind::Status, $payment));

                $this->assertSame($expected, $status->status, "{$type->key()}: the gateway's «{$gatewayStatus}».");

                if ($status->status === Reported::Paid) {
                    $this->assertNotNull($status->amount, "{$type->key()}: «paid» says how much was taken.");
                }

                if (! isset($table[$gatewayStatus])) {
                    $this->assertNotNull($status->reason, "{$type->key()}: an unknown status keeps the gateway's word in the reason.");
                }
            }
        }
    }

    #[Test]
    public function amounts_go_to_the_gateway_and_back_without_a_float(): void
    {
        foreach (['JPY' => ['1234' => 1234], 'EUR' => ['12.34' => 1234, '0.05' => 5, '1000000.00' => 100000000], 'KWD' => ['1.234' => 1234, '0.005' => 5]] as $currency => $cases) {
            foreach ($cases as $decimal => $minor) {
                $this->assertSame((string) $decimal, GatewayAmount::toDecimal($minor, $currency));
                $this->assertSame($minor, GatewayAmount::fromDecimal((string) $decimal, $currency));
            }
        }

        foreach ($this->methodTypes() as $type) {
            $payment = $this->payment($type, 100001);
            $this->answerStatus($type, $payment, $this->paidStatus($type));
            $status = $type->fetchStatus($this->method($type), $this->credentials($type), $payment, $this->http($type, ExchangeKind::Status, $payment));
            $this->assertSame(100001, $status->amount, "{$type->key()}: the amount came back other than it went.");
        }
    }

    #[Test]
    public function a_hold_is_captured_in_full_or_in_part_never_above_itself_and_let_go(): void
    {
        $types = array_filter($this->methodTypes(), static fn (ChargesOnline $type): bool => $type instanceof CapturesPayments);

        if ($types === []) {
            $this->markTestSkipped('No type of the gateway holds money.');
        }

        foreach ($types as $type) {
            /** @var CapturesPayments $type */
            $this->assertGreaterThan(0, $type->maxHoldDays());
            $capture = fn (Payment $payment, int $amount) => $type->capture($this->method($type), $this->credentials($type), $payment, $amount, $this->http($type, ExchangeKind::Capture, $payment));

            $full = $this->payment($type);
            $this->answerAuthorized($type, $full);
            $status = $capture($full, $full->amount);
            $this->assertSame([Reported::Paid, $full->amount], [$status->status, $status->amount], "{$type->key()}: the whole hold is captured.");

            if ($type->capturesPartially()) {
                $part = $this->payment($type);
                $this->answerAuthorized($type, $part);
                $status = $capture($part, 4000);
                $this->assertSame([Reported::Paid, 4000], [$status->status, $status->amount], "{$type->key()}: a part of the hold is captured.");
            }

            $above = $this->payment($type);
            $this->answerAuthorized($type, $above);

            try {
                $capture($above, $above->amount + 1);
                $this->fail("{$type->key()}: a capture above the hold went through.");
            } catch (GatewayRefused) {
                $this->addToAssertionCount(1);
            }

            $status = $type->void($this->method($type), $this->credentials($type), $above, $this->http($type, ExchangeKind::Void, $above));
            $this->assertSame(Reported::Cancelled, $status->status, "{$type->key()}: the hold is let go.");
        }
    }

    #[Test]
    public function an_approval_is_completed_once(): void
    {
        $types = array_filter($this->methodTypes(), static fn (ChargesOnline $type): bool => $type instanceof CompletesApproval);

        if ($types === []) {
            $this->markTestSkipped('No type of the gateway waits for the shop to complete.');
        }

        foreach ($types as $type) {
            /** @var CompletesApproval $type */
            $payment = $this->payment($type);
            $this->answerApproved($type, $payment);

            $approved = $type->fetchStatus($this->method($type), $this->credentials($type), $payment, $this->http($type, ExchangeKind::Status, $payment));
            $this->assertNotSame(Reported::Paid, $approved->status, "{$type->key()}: approved is not paid.");

            $first = $type->complete($this->method($type), $this->credentials($type), $payment, $this->http($type, ExchangeKind::Complete, $payment));
            $second = $type->complete($this->method($type), $this->credentials($type), $payment, $this->http($type, ExchangeKind::Complete, $payment));
            $this->assertSame([Reported::Paid, Reported::Paid], [$first->status, $second->status]);

            $charges = $this->charges($type, $payment);

            if ($charges !== null) {
                $this->assertSame(1, $charges, "{$type->key()}: two completions charged twice.");
            }
        }
    }

    #[Test]
    public function an_error_of_the_gateway_is_words_for_the_panel_without_its_body(): void
    {
        foreach ($this->methodTypes() as $type) {
            $payment = $this->payment($type);
            $this->answerStatus($type, $payment, $this->paidStatus($type));
            $body = $this->answerError($type, $payment);

            try {
                $type->fetchStatus($this->method($type), $this->credentials($type), $payment, $this->http($type, ExchangeKind::Status, $payment));
                $this->fail("{$type->key()}: an error of the gateway passed as a status.");
            } catch (GatewayUnavailable $e) {
                $this->assertNotSame('', $e->getMessage());
                $this->assertStringNotContainsString($body, $e->getMessage(), "{$type->key()}: the words for the panel carry the gateway's body.");
            }
        }
    }

    /** What the base would ask the gateway to open: of the buyer only what the type needs. */
    protected function startRequest(ChargesOnline $type, Payment $payment): StartRequest
    {
        $needs = $type->needs();
        $base = 'https://shop.test';

        return new StartRequest(
            uuid: $payment->uuid,
            orderNumber: '1024',
            amount: $payment->amount,
            currency: $payment->currency,
            description: 'Order 1024',
            returnUrl: $base.'/pay/return/'.$payment->uuid,
            notifyUrl: $base.'/payments/notify/'.$this->method($type)->key,
            locale: 'en',
            hold: $payment->hold && $type instanceof CapturesPayments,
            customerEmail: $needs->email ? 'buyer@example.com' : null,
            customerPhone: $needs->phone ? '+800 1234 5678' : null,
            customerName: $needs->name ? 'Buyer A' : null,
            lines: $needs->lines ? [new PaymentLine('Product A', 1, $payment->amount, $payment->amount, 'SKU-A')] : [],
        );
    }

    protected function http(ChargesOnline $type, ExchangeKind $kind, Payment $payment): GatewayHttp
    {
        return $this->app->make(GatewayCalls::class)->http($type, $this->method($type), $this->credentials($type), $kind, 'contract', $payment);
    }

    /** The first status of the table that is «paid». */
    private function paidStatus(ChargesOnline $type): string
    {
        $paid = array_search(Reported::Paid, $this->statusTable($type), true);
        $this->assertIsString($paid, "{$type->key()}: no status of the gateway is «paid».");

        return $paid;
    }

    private function notificationOf(ChargesOnline $type, Payment $payment, string $variant): Request
    {
        $request = $this->notification($type, $payment, $variant);
        $this->assertNotNull($request, "{$type->key()}: a notification «{$variant}» is recorded.");

        return $request;
    }

    private function currency(ChargesOnline $type): string
    {
        $currencies = $type->currencies();

        return $currencies === null || $currencies === [] ? 'EUR' : strtoupper($currencies[0]);
    }

    private function outEvents(): int
    {
        return PaymentGatewayEvent::query()->where('direction', 'out')->count();
    }

    /** Not one value of the keys, and no value of a secret field, in anything the journal kept. */
    private function assertNoSecretInTheJournal(): void
    {
        $keys = [];

        foreach ($this->methodTypes() as $type) {
            foreach ($this->credentials($type)->reveal() as $value) {
                if (strlen($value) >= ExchangeSecrets::MIN_LENGTH) {
                    $keys[] = $value;
                }
            }
        }

        $fields = $this->gateway()->secretFields();

        foreach (PaymentGatewayEvent::query()->orderBy('id')->get() as $event) {
            $kept = implode("\n", [
                (string) $event->url,
                (string) json_encode($event->request_headers),
                (string) json_encode($event->response_headers),
                (string) $event->request_body,
                (string) $event->response_body,
            ]);

            foreach (array_unique($keys) as $key) {
                $this->assertStringNotContainsString($key, $kept, "Event #{$event->id} ({$event->kind}) keeps a key of the gateway.");
            }

            foreach ($fields as $field) {
                foreach ([$event->request_body, $event->response_body] as $body) {
                    $json = is_string($body) ? json_decode($body, true) : null;

                    if (is_array($json) && is_string($json[$field] ?? null)) {
                        $this->assertStringStartsWith('[secret', $json[$field], "Event #{$event->id} keeps the secret field «{$field}».");
                    }
                }
            }
        }
    }
}
