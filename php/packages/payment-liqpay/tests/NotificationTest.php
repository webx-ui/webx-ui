<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\InvalidNotification;
use WebxUi\Payments\Method;
use WebxUi\Payments\Notification;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Reported;

/** `readNotification()` step by step (spec §8.1) and what each status of a notification becomes (§9.2). */
final class NotificationTest extends TestCase
{
    use Recorded;

    private const string UUID = 'f21b0233-01b4-4ef9-948b-fa54c2a299dd';

    /** @return array<string, array{0: string}> */
    public static function algorithms(): array
    {
        return ['SHA3-256' => ['sha3-256'], 'SHA1' => ['sha1']];
    }

    #[Test]
    #[DataProvider('algorithms')]
    public function a_notification_signed_either_way_is_a_paid_status_of_our_attempt(string $algorithm): void
    {
        $data = self::dataOf('success');
        $notification = $this->read($data, self::signed($data, $algorithm));

        $this->assertTrue($notification->isStatus());
        $this->assertSame([self::UUID, '2950756317'], [$notification->uuid, $notification->gatewayRef]);
        $this->assertSame(Notification::keyOf($data), $notification->eventKey);

        $status = $notification->status;
        $this->assertNotNull($status);
        $this->assertSame([Reported::Paid, 1234, 'UAH', '2950756317', null], [$status->status, $status->amount, $status->currency, $status->gatewayRef, $status->reason]);
        $this->assertSame(1793780487123, $status->paidAt?->getTimestampMs());
        $this->assertEquals(['paytype' => 'card', 'sender_card_mask2' => '424242*42', 'sender_card_type' => 'visa', 'is_3ds' => false, 'receiver_commission' => 0.16], $status->meta);
    }

    #[Test]
    public function the_same_data_is_the_same_event_whichever_way_it_was_signed(): void
    {
        $data = self::dataOf('success');

        $this->assertSame($this->read($data, self::signed($data))->eventKey, $this->read($data, self::signed($data, 'sha1'))->eventKey);
    }

    #[Test]
    public function a_bad_signature_is_refused_and_names_the_attempt_it_claims(): void
    {
        $data = self::dataOf('success');

        try {
            $this->read($data, self::signed($data, key: 'another-key'));
            $this->fail('A bad signature was believed.');
        } catch (InvalidNotification $e) {
            $this->assertSame([InvalidNotification::SIGNATURE, self::UUID], [$e->reason, $e->claimedUuid]);
        }
    }

    #[Test]
    public function the_key_of_the_other_mode_is_a_bad_signature(): void
    {
        $data = self::dataOf('success');
        $both = Credentials::of(['test_private_key' => self::PRIVATE_KEY, 'live_private_key' => 'fake-live-private-key-0000']);

        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessage('The signature does not match');

        $this->read($data, self::signed($data), self::liqpayMethod(['mode' => 'live']), $both);
    }

    #[Test]
    public function a_method_without_the_key_of_its_mode_believes_nothing(): void
    {
        $data = self::dataOf('success');

        $this->expectException(InvalidNotification::class);

        $this->read($data, self::signed($data), self::liqpayMethod(['mode' => 'live']));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function malformedBodies(): array
    {
        return [
            'nothing' => [[]],
            'no signature' => [['data' => 'e30=']],
            'no data' => [['signature' => 'abc']],
            'empty' => [['data' => '', 'signature' => '']],
            'lists' => [['data' => ['a'], 'signature' => ['b']]],
        ];
    }

    /** @param  array<string, mixed>  $body */
    #[Test]
    #[DataProvider('malformedBodies')]
    public function a_body_without_the_two_strings_is_malformed(array $body): void
    {
        try {
            $this->readRequest(Request::create('/payments/notify/liqpay-checkout', 'POST', $body));
            $this->fail('A malformed body was read.');
        } catch (InvalidNotification $e) {
            $this->assertSame(InvalidNotification::MALFORMED, $e->reason);
        }
    }

    #[Test]
    public function signed_rubbish_is_malformed_and_unsigned_rubbish_a_bad_signature(): void
    {
        foreach (['not base64!', base64_encode('not json'), base64_encode('"a string"')] as $data) {
            try {
                $this->read($data, self::signed($data));
                $this->fail('Rubbish was read.');
            } catch (InvalidNotification $e) {
                $this->assertSame(InvalidNotification::MALFORMED, $e->reason);
            }

            try {
                $this->read($data, 'bad');
                $this->fail('Rubbish was read.');
            } catch (InvalidNotification $e) {
                $this->assertSame([InvalidNotification::SIGNATURE, false], [$e->reason, $e->claims()]);
            }
        }
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function subscriptions(): array
    {
        return [
            'subscribe' => [['action' => 'subscribe']],
            'regular' => [['action' => 'regular']],
            'by a token' => [['action' => 'recurringbytoken']],
            'status subscribed' => [['status' => 'subscribed']],
            'status unsubscribed' => [['status' => 'unsubscribed']],
        ];
    }

    /** @param  array<string, mixed>  $changes */
    #[Test]
    #[DataProvider('subscriptions')]
    public function what_is_not_about_a_payment_is_unrelated(array $changes): void
    {
        $data = self::dataOf('success', $changes);

        $notification = $this->read($data, self::signed($data));

        $this->assertTrue($notification->isUnrelated());
        $this->assertStringStartsWith('Not about a payment: ', (string) $notification->reason);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function pokes(): array
    {
        return [
            'reversed' => ['reversed', []],
            'a refund of an unknown action' => ['reversed', ['action' => 'refund']],
            'success with a refund' => ['success', ['refund_amount' => 1.5]],
        ];
    }

    /** @param  array<string, mixed>  $changes */
    #[Test]
    #[DataProvider('pokes')]
    public function a_refund_or_a_release_is_only_a_reason_to_ask(string $fixture, array $changes): void
    {
        $data = self::dataOf($fixture, $changes);
        $notification = $this->read($data, self::signed($data));

        $this->assertTrue($notification->isPoke());
        $this->assertNull($notification->status);
        $this->assertNotNull($notification->uuid);
    }

    /** @return array<string, array{0: string, 1: Reported}> */
    public static function statuses(): array
    {
        return [
            'failure' => ['failure', Reported::Failed],
            'try_again' => ['try-again', Reported::Pending],
            'hold_wait' => ['hold-wait', Reported::Authorized],
            'hold completed' => ['hold-completed', Reported::Paid],
        ];
    }

    #[Test]
    #[DataProvider('statuses')]
    public function a_recorded_notification_reads_by_the_table(string $fixture, Reported $reported): void
    {
        $data = self::fixture('notify/'.$fixture)['data'];

        $this->assertSame($reported, $this->read($data, self::signed($data))->status?->status);
    }

    #[Test]
    public function a_refusal_is_in_words_and_expired_is_not_a_plain_failure(): void
    {
        $data = self::dataOf('failure', ['err_code' => 'limit']);
        $this->assertSame('The bank refused: a limit of the card was reached', $this->read($data, self::signed($data))->status?->reason);

        $data = self::dataOf('failure', ['err_code' => 'expired']);
        $this->assertSame(Reported::Expired, $this->read($data, self::signed($data))->status?->status);

        // A code of the dictionary written as a number.
        $data = self::dataOf('failure', ['err_code' => 9859]);
        $this->assertSame('The card has too little money', $this->read($data, self::signed($data))->status?->reason);

        // A code nobody translated keeps LiqPay's own words, and one with a dot reads no other branch.
        foreach (['never_seen', 'status.wait_accept'] as $code) {
            $data = self::dataOf('failure', ['err_code' => $code, 'err_description' => 'As LiqPay says it']);
            $this->assertSame('As LiqPay says it', $this->read($data, self::signed($data))->status?->reason);
        }
    }

    #[Test]
    public function wait_accept_is_paid_with_the_mark_in_the_reason(): void
    {
        $data = self::dataOf('success', ['status' => 'wait_accept']);
        $status = $this->statusOf($data);

        $this->assertSame(Reported::Paid, $status->status);
        $this->assertSame('LiqPay has not checked the shop yet: the money comes after the check', $status->reason);
    }

    #[Test]
    public function an_unknown_status_is_pending_and_keeps_its_word(): void
    {
        $data = self::dataOf('success', ['status' => 'never_seen']);
        $status = $this->statusOf($data);

        $this->assertSame(Reported::Pending, $status->status);
        $this->assertSame('Unknown status of LiqPay: never_seen', $status->reason);
    }

    #[Test]
    public function the_amount_stays_in_the_currency_of_the_order_and_loses_no_cent(): void
    {
        foreach ([[1234.56, 123456], [0.07, 7], [19.99, 1999], [5, 500], [1000000.01, 100000001]] as [$amount, $minor]) {
            $data = self::dataOf('success', ['amount' => $amount, 'currency' => 'EUR', 'amount_debit' => 99999.99, 'currency_debit' => 'UAH']);
            $status = $this->statusOf($data);

            $this->assertSame([$minor, 'EUR'], [$status->amount, $status->currency]);
        }
    }

    #[Test]
    public function a_notification_without_our_number_goes_to_the_base_by_the_gateways(): void
    {
        $data = self::dataOf('success', ['order_id' => null]);
        $notification = $this->read($data, self::signed($data));

        $this->assertNull($notification->uuid);
        $this->assertSame('2950756317', $notification->gatewayRef);
    }

    private function statusOf(string $data): GatewayStatus
    {
        $status = $this->read($data, self::signed($data))->status;
        $this->assertNotNull($status);

        return $status;
    }

    private function read(string $data, string $signature, ?Method $method = null, ?Credentials $credentials = null): Notification
    {
        return $this->readRequest(Request::create('/payments/notify/liqpay-checkout', 'POST', ['data' => $data, 'signature' => $signature]), $method, $credentials);
    }

    private function readRequest(Request $request, ?Method $method = null, ?Credentials $credentials = null): Notification
    {
        $type = $this->app->make(LiqPayCheckoutType::class);
        $method ??= self::liqpayMethod();
        $credentials ??= self::testKey();
        $http = $this->app->make(GatewayCalls::class)->http($type, $method, $credentials, ExchangeKind::Status, 'notify');

        return $type->readNotification($method, $credentials, $request, $http);
    }
}
