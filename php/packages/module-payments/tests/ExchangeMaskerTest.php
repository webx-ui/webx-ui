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

    /** A date in milliseconds that passes Luhn, as a gateway's answer may hold. */
    private const string DATE = '1730000000001';

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

    /** @return iterable<string, array{string}> */
    public static function declaredValues(): iterable
    {
        $date = self::DATE;

        yield 'json number' => ['{"create_date":'.$date.',"status":"success"}'];
        yield 'json string' => ['{"create_date":"'.$date.'"}'];
        yield 'json with spaces' => ['{ "create_date" : '.$date.' }'];
        yield 'json, nested' => ['{"payment":{"create_date":'.$date.'}}'];
        yield 'json, in a list' => ['[{"create_date":'.$date.'}]'];
        yield 'form, first' => ['create_date='.$date.'&status=success'];
        yield 'form, last' => ['status=success&create_date='.$date];
    }

    #[Test]
    #[DataProvider('declaredValues')]
    public function a_number_in_a_declared_field_stays_and_is_not_counted(string $body): void
    {
        $counts = [];

        $this->assertSame($body, (new ExchangeMasker)->text($body, $this->secrets(notCards: ['create_date']), $counts, body: true));
        $this->assertSame([], $counts);
    }

    /** @return iterable<string, array{string, string}> */
    public static function undeclaredValues(): iterable
    {
        $date = self::DATE;
        $star = '*********0001';

        yield 'json' => ['{"create_date":'.$date.',"other":'.$date.'}', '{"create_date":'.$date.',"other":'.$star.'}'];
        yield 'json, undeclared first' => ['{"other":"'.$date.'","create_date":"'.$date.'"}', '{"other":"'.$star.'","create_date":"'.$date.'"}'];
        yield 'form' => ['create_date='.$date.'&other='.$date, 'create_date='.$date.'&other='.$star];
        yield 'a list under the name' => ['{"create_date":['.$date.']}', '{"create_date":['.$star.']}'];
        yield 'an object under the name' => ['{"create_date":{"x":'.$date.'}}', '{"create_date":{"x":'.$star.'}}'];
        yield 'the name inside a json string' => ['{"note":"\"create_date\":'.$date.'"}', '{"note":"\"create_date\":'.$star.'"}'];
        yield 'a form inside a json string' => ['{"note":"create_date='.$date.'"}', '{"note":"create_date='.$star.'"}'];
        yield 'a longer name' => ['{"x_create_date":'.$date.'}', '{"x_create_date":'.$star.'}'];
        yield 'a form name as a value' => ['other=create_date='.$date, 'other=create_date='.$star];
    }

    #[Test]
    #[DataProvider('undeclaredValues')]
    public function the_same_number_anywhere_else_is_still_starred(string $body, string $masked): void
    {
        $counts = [];

        $this->assertSame($masked, (new ExchangeMasker)->text($body, $this->secrets(notCards: ['create_date']), $counts, body: true));
        $this->assertSame(1, $counts['pan'] ?? null);
    }

    #[Test]
    public function a_declared_field_spares_only_a_body(): void
    {
        $counts = [];
        $secrets = $this->secrets(notCards: ['create_date']);

        // A url or an error's text is not a body: nothing is parsed, the rule stands.
        $this->assertSame(
            'https://gateway-a.test/?create_date=*********0001',
            (new ExchangeMasker)->text('https://gateway-a.test/?create_date='.self::DATE, $secrets, $counts),
        );
    }

    #[Test]
    public function a_secret_in_a_declared_field_is_wiped_all_the_same(): void
    {
        $masker = new ExchangeMasker;
        $counts = [];

        $this->assertSame(
            '{"create_date":"[secret:secret_key]"}',
            $masker->text((string) json_encode(['create_date' => self::SECRET]), $this->secrets(notCards: ['create_date']), $counts, body: true),
        );
        $this->assertSame(
            'create_date=[secret:secret_key]',
            $masker->text('create_date='.urlencode(self::SECRET), $this->secrets(notCards: ['create_date']), $counts, body: true),
        );

        // A secret field and a run-time secret too, and a key that looks like a date.
        $this->assertSame(
            '{"access_token":"[secret:access_token]"}',
            $masker->text('{"access_token":"oauth-token-of-gateway-a-0001"}', $this->secrets(fields: ['access_token'], notCards: ['access_token']), $counts, body: true),
        );

        $digits = new ExchangeSecrets(Credentials::of(['secret_key' => self::DATE]), notCardFields: ['create_date']);
        $this->assertSame('{"create_date":[secret:secret_key]}', $masker->text('{"create_date":'.self::DATE.'}', $digits, $counts, body: true));

        $this->assertArrayNotHasKey('pan', $counts);
    }

    #[Test]
    public function a_wiped_bare_number_does_not_cost_the_other_fields_their_exception(): void
    {
        $secrets = new ExchangeSecrets(Credentials::of(['secret_key' => '12345678']), notCardFields: ['create_date']);
        $counts = [];

        // The wipe leaves text that is no longer JSON; the body was JSON all the same.
        $this->assertSame(
            '{"amount":[secret:secret_key],"create_date":'.self::DATE.'}',
            (new ExchangeMasker)->text('{"amount":12345678,"create_date":'.self::DATE.'}', $secrets, $counts, body: true),
        );
        $this->assertArrayNotHasKey('pan', $counts);
    }

    #[Test]
    public function a_gateway_without_the_contract_has_every_number_looked_at(): void
    {
        $counts = [];

        $this->assertSame(
            '{"create_date":*********0001}',
            (new ExchangeMasker)->text('{"create_date":'.self::DATE.'}', $this->secrets(), $counts, body: true),
        );
        $this->assertSame(1, $counts['pan'] ?? null);
    }

    #[Test]
    public function a_base64_body_is_not_opened_for_cards_and_its_secrets_are_still_wiped(): void
    {
        $secrets = $this->secrets(notCards: ['create_date']);
        $counts = [];

        // The form of a page gateway: the fields are inside base64, where the card rule does not
        // reach, so the body keeps every byte — a signature over it can still be checked.
        $body = 'data='.base64_encode('{"create_date":'.self::DATE.',"other":'.self::DATE.'}').'&signature=c2lnbmF0dXJl';
        $this->assertSame($body, (new ExchangeMasker)->text($body, $secrets, $counts, body: true));
        $this->assertSame([], $counts);

        $masked = (string) (new ExchangeMasker)->text('data='.base64_encode('{"create_date":"'.self::SECRET.'"}'), $secrets, $counts, body: true);
        $this->assertNoSecret($masked);
        $this->assertGreaterThan(0, $counts['secret:secret_key'] ?? 0);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     * @param  list<string>  $notCards
     */
    private function secrets(array $headers = [], array $fields = [], array $notCards = []): ExchangeSecrets
    {
        return new ExchangeSecrets(Credentials::of(['secret_key' => self::SECRET, 'shop_id' => 'shop-a']), $headers, $fields, $notCards);
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
