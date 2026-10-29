<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * The set of statuses a site starts with is seeded from the dictionary in every language at once
 * (spec §14), so each shipped language has to name every status of it — a line missing in one is
 * a status that reads English in the middle of a Russian account.
 */
final class StatusLanguagesTest extends PhpUnitTestCase
{
    #[Test]
    public function every_shipped_language_names_the_whole_default_set(): void
    {
        $english = require __DIR__.'/../lang/en/statuses.php';

        foreach (glob(__DIR__.'/../lang/*/statuses.php') ?: [] as $file) {
            $locale = basename(dirname($file));
            /** @var array{public: array<string, array{name: string, description: string}>, internal: array<string, string>} $words */
            $words = require $file;

            $this->assertSame(array_keys($english['public']), array_keys($words['public']), "[{$locale}] public");
            $this->assertSame(array_keys($english['internal']), array_keys($words['internal']), "[{$locale}] internal");

            foreach ($words['public'] as $code => $public) {
                $this->assertNotSame('', trim($public['name']), "[{$locale}] public.{$code}.name");
                $this->assertNotSame('', trim($public['description']), "[{$locale}] public.{$code}.description");
            }

            foreach ($words['internal'] as $key => $name) {
                $this->assertNotSame('', trim($name), "[{$locale}] internal.{$key}");
            }
        }
    }
}
