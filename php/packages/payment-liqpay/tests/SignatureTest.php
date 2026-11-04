<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as Base;
use WebxUi\Payment\LiqPay\Api\Signature;

/** The version decides the algorithm of a request; a notification may come with either (spec §3.2, §8.1). */
final class SignatureTest extends Base
{
    use Recorded;

    /** @return array<string, array{0: string, 1: int}> */
    public static function requests(): array
    {
        return [
            'version 7 is SHA3-256' => ['api/sig.status.sha3.v7', 7],
            'version 3 is SHA1' => ['api/sig.status.sha1.v3', 3],
        ];
    }

    #[Test]
    #[DataProvider('requests')]
    public function a_request_is_signed_as_the_sandbox_took_it(string $fixture, int $version): void
    {
        $request = self::fixture($fixture)['request'];

        $this->assertSame($request['signature'], Signature::sign($request['data'], self::PRIVATE_KEY, $version));
    }

    #[Test]
    public function versions_three_to_six_are_sha1_and_nothing_else_has_a_signature(): void
    {
        $sha1 = self::signed('data', 'sha1');

        foreach ([3, 4, 5, 6] as $version) {
            $this->assertSame($sha1, Signature::sign('data', self::PRIVATE_KEY, $version));
        }

        $this->expectException(InvalidArgumentException::class);
        Signature::sign('data', self::PRIVATE_KEY, 8);
    }

    /** @return array<string, array{0: string}> */
    public static function notifications(): array
    {
        return array_map(static fn (string $name): array => [$name], [
            'success' => 'success',
            'failure' => 'failure',
            'try-again' => 'try-again',
            'hold-wait' => 'hold-wait',
            'hold-completed' => 'hold-completed',
            'reversed' => 'reversed',
        ]);
    }

    #[Test]
    #[DataProvider('notifications')]
    public function every_recorded_notification_passes_with_either_algorithm(string $name): void
    {
        $notification = self::fixture('notify/'.$name);

        $this->assertTrue(Signature::verify($notification['data'], $notification['signature_sha3_256'], self::PRIVATE_KEY));
        $this->assertTrue(Signature::verify($notification['data'], $notification['signature_sha1'], self::PRIVATE_KEY));
    }

    #[Test]
    public function the_signature_covers_the_string_and_not_what_it_decodes_to(): void
    {
        $data = base64_encode('{"status":"success", "amount":1}');
        $sameJson = base64_encode('{"status":"success","amount":1}');

        $this->assertFalse(Signature::verify($sameJson, self::signed($data), self::PRIVATE_KEY));
    }

    #[Test]
    public function another_key_or_a_changed_signature_does_not_pass(): void
    {
        $this->assertFalse(Signature::verify('data', self::signed('data', key: 'another-key'), self::PRIVATE_KEY));
        $this->assertFalse(Signature::verify('data', self::signed('data').'x', self::PRIVATE_KEY));
        $this->assertFalse(Signature::verify('data', base64_encode(hash('sha256', self::PRIVATE_KEY.'data'.self::PRIVATE_KEY, true)), self::PRIVATE_KEY));
    }
}
