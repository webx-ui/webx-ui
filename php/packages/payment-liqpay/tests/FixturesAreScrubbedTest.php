<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as Base;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every recording (spec §17.1) is safe to publish: only the fake keys, the placeholder card, phone
 * and address, in the open and inside the base64 of `data`.
 */
final class FixturesAreScrubbedTest extends Base
{
    use Recorded;

    private const string IP = '203.0.113.10';

    private const string PHONE = '+800 1234 5678';

    /** @return array<string, string> the name of a file => what it holds, with every `data` decoded */
    private static function recordings(): array
    {
        $recordings = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/Fixtures', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $text = (string) file_get_contents($file->getPathname());
            $recordings[substr($file->getPathname(), strlen(__DIR__.'/Fixtures/'))] = $text."\n".implode("\n", self::decoded(json_decode($text, true)));
        }

        return $recordings;
    }

    /** @return list<string> */
    private static function decoded(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $decoded = [];

        foreach ($value as $key => $item) {
            if ($key === 'data' && is_string($item)) {
                $decoded[] = (string) base64_decode($item, true);
            }

            array_push($decoded, ...self::decoded($item));
        }

        return $decoded;
    }

    #[Test]
    public function the_recordings_moved_here_whole_and_each_is_json(): void
    {
        $recordings = self::recordings();

        $this->assertCount(58, $recordings);

        foreach (array_keys($recordings) as $name) {
            $this->assertIsArray(json_decode((string) file_get_contents(__DIR__.'/Fixtures/'.$name), true), $name);
        }
    }

    #[Test]
    public function the_only_keys_are_the_fake_ones(): void
    {
        foreach (self::recordings() as $name => $text) {
            preg_match_all('/\b(?:sandbox|i)_?[A-Za-z0-9]*(?:public|private|[0-9]{8,})[A-Za-z0-9]*\b/', $text, $keys);

            foreach ($keys[0] as $key) {
                $this->assertContains($key, [self::PUBLIC_KEY, self::PRIVATE_KEY], $name);
            }

            preg_match_all('/"public_key":\s*"([^"]*)"/', $text, $public);

            foreach ($public[1] as $key) {
                $this->assertSame(self::PUBLIC_KEY, $key, $name);
            }

            $this->assertStringNotContainsString('private_key', $text, $name);
        }
    }

    #[Test]
    public function a_card_a_phone_and_an_address_are_placeholders(): void
    {
        foreach (self::recordings() as $name => $text) {
            foreach (['card' => '[test-card]', 'card_cvv' => '[test-cvv]', 'phone' => self::PHONE, 'sender_phone' => self::PHONE, 'ip' => self::IP] as $field => $placeholder) {
                preg_match_all('/"'.$field.'":\s*"([^"]*)"/', $text, $values);

                foreach ($values[1] as $value) {
                    $this->assertSame($placeholder, $value, "{$name}: {$field}");
                }
            }

            // A whole card number anywhere: the masks have a star in the middle.
            $this->assertDoesNotMatchRegularExpression('/\b4\d{15}\b/', $text, $name);
            preg_match_all('/"sender_(?:first|last)_name":\s*"([^"]*)"/', $text, $names);

            foreach ($names[1] as $value) {
                $this->assertContains($value, ['Buyer', 'Example'], $name);
            }

            preg_match_all('/"acq_id":\s*(\d+)/', $text, $acquirers);

            foreach ($acquirers[1] as $value) {
                $this->assertSame('100000', $value, $name);
            }
        }
    }

    #[Test]
    public function a_notification_says_it_is_emulated_and_is_signed_with_the_fake_key(): void
    {
        $notifications = glob(__DIR__.'/Fixtures/notify/*.json') ?: [];
        $this->assertNotSame([], $notifications);

        foreach ($notifications as $file) {
            $notification = json_decode((string) file_get_contents($file), true);

            $this->assertTrue($notification['emulated'], basename($file));
            $this->assertSame(self::signed($notification['data'], 'sha1'), $notification['signature_sha1'], basename($file));
            $this->assertSame(self::signed($notification['data']), $notification['signature_sha3_256'], basename($file));
        }
    }
}
