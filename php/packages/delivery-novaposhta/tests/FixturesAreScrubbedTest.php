<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as Base;
use WebxUi\Delivery\NovaPoshta\Recording\Scrubber;

/**
 * Every recorded answer (§13.1) is safe to publish: no key, no real phone or e-mail, and in the
 * account's answers no id outside the fake range and no name but the placeholders.
 */
final class FixturesAreScrubbedTest extends Base
{
    /** @return list<string> */
    private static function files(): array
    {
        return glob(__DIR__.'/Fixtures/api/*.json') ?: [];
    }

    /**
     * Every string of an answer with the key it sits under.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function strings(mixed $value, string $key = ''): array
    {
        if (is_string($value)) {
            return [[$key, $value]];
        }

        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $inner => $item) {
            array_push($strings, ...self::strings($item, is_string($inner) ? $inner : $key));
        }

        return $strings;
    }

    #[Test]
    public function the_answers_are_there_and_each_is_the_carriers_json(): void
    {
        $this->assertGreaterThanOrEqual(20, count(self::files()));

        foreach (self::files() as $file) {
            $this->assertMatchesRegularExpression('/^[A-Za-z]+\.[A-Za-z]+\.[a-z0-9-]+\.json$/', basename($file));
            $body = json_decode((string) file_get_contents($file), true);
            $this->assertIsArray($body, basename($file));
            $this->assertArrayHasKey('success', $body, basename($file));
            $this->assertArrayHasKey('data', $body, basename($file));
        }
    }

    #[Test]
    public function no_file_holds_a_key_a_phone_or_an_e_mail(): void
    {
        foreach (self::files() as $file) {
            $text = (string) file_get_contents($file);
            $name = basename($file);

            $this->assertStringNotContainsStringIgnoringCase('apiKey', $text, $name);
            // A key of the carrier is 32 hex digits.
            $this->assertDoesNotMatchRegularExpression('/\b[0-9a-f]{32}\b/i', $text, $name);
            $this->assertDoesNotMatchRegularExpression('/\+?38\s?\(?0\d{2}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}/', $text, $name);

            foreach (self::strings(json_decode($text, true)) as [$key, $value]) {
                if (preg_match('/phone/i', $key) === 1 && $value !== '') {
                    $this->assertSame(Scrubber::PHONE, $value, "{$name}: {$key}");
                }

                if (str_contains($value, '@')) {
                    $this->assertStringEndsWith('@example.com', $value, "{$name}: {$key}");
                }
            }
        }
    }

    #[Test]
    public function answers_of_the_account_hold_only_fake_ids_and_placeholder_names(): void
    {
        $account = 0;

        foreach (self::files() as $file) {
            $model = explode('.', basename($file))[0];

            if (! in_array($model, Scrubber::ACCOUNT_MODELS, true)) {
                continue;
            }

            $account++;
            $body = json_decode((string) file_get_contents($file), true);

            foreach (self::strings($body['data'] ?? []) as [$key, $value]) {
                if (preg_match(Scrubber::UUID, $value) === 1) {
                    $this->assertMatchesRegularExpression(Scrubber::FAKE_UUID, $value, basename($file).": {$key}");
                }

                if (preg_match('/^(Description|LastName|FirstName|MiddleName)$/', $key) === 1 && $value !== '') {
                    $this->assertMatchesRegularExpression('/^(Відправник|Контакт|Одержувач|Прізвище|Імʼя|По батькові) \S+$/u', $value, basename($file).": {$key}");
                }

                if (preg_match('/edrpou/i', $key) === 1 && $value !== '') {
                    $this->assertSame(Scrubber::CODE, $value);
                }
            }
        }

        $this->assertGreaterThan(0, $account);
    }
}
