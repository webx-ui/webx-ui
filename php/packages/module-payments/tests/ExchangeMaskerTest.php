<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeMasker;
use WebxUi\Payments\Exchange\ExchangeSecrets;

/**
 * Masking by value (spec §20.5, decision 40): a key of the method is wiped however the gateway's
 * body wraps it, the signature stays, and a card number is starred.
 */
final class ExchangeMaskerTest extends PlainTestCase
{
    private const string SECRET = 'fake-secret-key/0001+abc=';

    /** @return iterable<string, array{string}> */
    public static function encodings(): iterable
    {
        $key = self::SECRET;

        yield 'as is in a form' => ['private_key='.$key.'&amount=100'];
        yield 'url-encoded' => ['private_key='.urlencode($key).'&amount=100'];
        yield 'raw url-encoded' => ['https://gateway-a.test/api?key='.rawurlencode($key)];
        yield 'json-escaped' => [(string) json_encode(['key' => $key, 'amount' => 100])];
        yield 'json unescaped' => [(string) json_encode(['key' => $key], JSON_UNESCAPED_SLASHES)];
        yield 'base64 of the key' => ['token='.base64_encode($key)];
        yield 'base64 inside a json at offset 0' => ['data='.base64_encode('{"k":"'.$key.'","amount":100}')];
        yield 'base64 inside a json at offset 1' => ['data='.base64_encode('{"kk":"'.$key.'","amount":100}')];
        yield 'base64 inside a json at offset 2' => ['data='.base64_encode('{"kkk":"'.$key.'","amount":100}')];
        yield 'base64url' => ['data='.rtrim(strtr(base64_encode('{"kk":"'.$key.'"}'), '+/', '-_'), '=')];
        yield 'twice' => [$key.' and again '.$key];
    }

    #[Test]
    #[DataProvider('encodings')]
    public function a_key_is_wiped_in_every_encoding(string $body): void
    {
        $counts = [];
        $masked = (string) (new ExchangeMasker)->text($body, $this->secrets(), $counts, body: true);

        $this->assertNoSecret($masked);
        $this->assertStringContainsString('[secret:secret_key]', $masked);
        $this->assertGreaterThan(0, $counts['secret:secret_key'] ?? 0);
    }

    #[Test]
    public function a_body_without_secrets_stays_byte_for_byte(): void
    {
        $body = '{"order_id":"01JC0000000000000000000000","amount":"120.00","signature":"c2lnbmF0dXJlLW9mLXRoZS1nYXRld2F5","email":"buyer@example.com","phone":"+800 1234 5678"}';
        $counts = [];

        $this->assertSame($body, (new ExchangeMasker)->text($body, $this->secrets(), $counts, body: true));
        $this->assertSame([], $counts);
    }

    #[Test]
    public function the_signature_is_kept_even_when_it_is_made_of_the_key(): void
    {
        // A signature is a hash of the key, never the key: it stays for whoever checks it later.
        $signature = base64_encode(sha1('data'.self::SECRET.'data', true));
        $counts = [];

        $masked = (string) (new ExchangeMasker)->text('signature='.$signature, $this->secrets(), $counts, body: true);

        $this->assertSame('signature='.$signature, $masked);
    }

    #[Test]
    public function a_short_key_is_not_looked_for(): void
    {
        $secrets = new ExchangeSecrets(Credentials::of(['pin' => '1234']));
        $counts = [];

        $this->assertSame('amount=1234', (new ExchangeMasker)->text('amount=1234', $secrets, $counts));
    }

    #[Test]
    public function secret_headers_are_wiped_whole_and_others_by_value(): void
    {
        $counts = [];
        $headers = (new ExchangeMasker)->headers([
            'Authorization' => ['Basic '.base64_encode('shop-a:'.self::SECRET)],
            'Cookie' => 'session=abc',
            'X-Token' => ['token-of-the-gateway-a'],
            'X-Echo' => ['key '.self::SECRET],
            'Content-Type' => ['application/json'],
        ], $this->secrets(headers: ['X-Token']), $counts);

        $this->assertSame([ExchangeMasker::HEADER], $headers['Authorization'] ?? null);
        $this->assertSame([ExchangeMasker::HEADER], $headers['Cookie'] ?? null);
        $this->assertSame([ExchangeMasker::HEADER], $headers['X-Token'] ?? null);
        $this->assertSame(['key [secret:secret_key]'], $headers['X-Echo'] ?? null);
        $this->assertSame(['application/json'], $headers['Content-Type'] ?? null);
        $this->assertSame(1, $counts['header:authorization'] ?? null);
        $this->assertSame(1, $counts['header:x-token'] ?? null);
    }

    #[Test]
    public function a_secret_field_of_one_answer_is_wiped_from_every_later_event_of_the_trace(): void
    {
        $masker = new ExchangeMasker;
        $secrets = $this->secrets(fields: ['access_token']);
        $counts = [];

        $answer = (string) $masker->text('{"access_token":"oauth-token-of-gateway-a-0001","expires_in":3600}', $secrets, $counts, body: true);
        $next = (string) $masker->text('Bearer oauth-token-of-gateway-a-0001', $secrets, $counts);
        $form = (string) $masker->text('access_token=form-token-of-gateway-a&x=1', $secrets, $counts, body: true);

        $this->assertSame('{"access_token":"[secret:access_token]","expires_in":3600}', $answer);
        $this->assertSame('Bearer [secret:access_token]', $next);
        $this->assertSame('access_token=[secret:access_token]&x=1', $form);
    }

    #[Test]
    public function a_secret_made_at_run_time_is_wiped_like_a_key(): void
    {
        $secrets = $this->secrets();
        $secrets->add('runtime-secret-0001', 'session_key');
        $counts = [];

        $this->assertSame('k=[secret:session_key]', (new ExchangeMasker)->text('k=runtime-secret-0001', $secrets, $counts));
    }

    #[Test]
    public function a_card_number_keeps_only_its_last_four_digits(): void
    {
        $counts = [];
        $masked = (string) (new ExchangeMasker)->text(
            '{"pan":"4111111111111111","masked":"4444****1111","order":"4111111111111112","long":"41111111111111110000","ts":1730000000000}',
            $this->secrets(),
            $counts,
            body: true,
        );

        // Luhn passes — starred; the gateway's own mask, a number failing Luhn and a part of a
        // longer number — untouched.
        $this->assertStringContainsString('"pan":"************1111"', $masked);
        $this->assertStringContainsString('"masked":"4444****1111"', $masked);
        $this->assertStringContainsString('"order":"4111111111111112"', $masked);
        $this->assertStringContainsString('"long":"41111111111111110000"', $masked);
        $this->assertSame(1, $counts['pan'] ?? null);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     */
    private function secrets(array $headers = [], array $fields = []): ExchangeSecrets
    {
        return new ExchangeSecrets(Credentials::of(['secret_key' => self::SECRET, 'shop_id' => 'shop-a']), $headers, $fields);
    }

    private function assertNoSecret(string $text): void
    {
        $this->assertStringNotContainsString(self::SECRET, $text);
        $this->assertStringNotContainsString(urlencode(self::SECRET), $text);
        $this->assertStringNotContainsString(rawurlencode(self::SECRET), $text);
        $this->assertStringNotContainsString(substr((string) json_encode(self::SECRET), 1, -1), $text);
        // The whole of the key cannot be read back out of any base64 left in the text.
        foreach ([$text, ...(preg_match_all('/[A-Za-z0-9+\/_-]{8,}={0,2}/', $text, $m) ? $m[0] : [])] as $chunk) {
            $decoded = (string) base64_decode(strtr($chunk, '-_', '+/'), false);
            $this->assertStringNotContainsString(self::SECRET, $decoded);
        }
    }
}
