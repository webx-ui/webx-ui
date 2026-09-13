<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

final class ValidationLinesTest extends TestCase
{
    /** Every language the panel is drawn in has to be able to refuse a form in that language. */
    private const LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    #[Test]
    public function a_refused_form_is_worded_in_the_language_it_was_read_in(): void
    {
        $english = $this->messageFor('en');

        foreach (self::LOCALES as $locale) {
            $message = $this->messageFor($locale);

            $this->assertNotSame('', $message, "[{$locale}] said nothing.");
            $this->assertStringNotContainsString('validation.', $message, "[{$locale}] said a key.");

            if ($locale !== 'en') {
                $this->assertNotSame($english, $message, "[{$locale}] answered in English.");
            }
        }
    }

    #[Test]
    public function the_same_keys_exist_in_every_language(): void
    {
        $english = $this->lines('en');

        $this->assertNotSame([], $english);

        foreach (self::LOCALES as $locale) {
            $this->assertSame(
                array_keys($english),
                array_keys($this->lines($locale)),
                "[{$locale}] carries a different set of rules.",
            );
        }
    }

    #[Test]
    public function the_application_still_has_the_last_word(): void
    {
        // `addPath` appends, so a line published into the application's own `lang/` wins. That
        // is the whole point of appending rather than replacing.
        app('translator')->addLines(['validation.required' => 'Ours.'], 'ru');

        $this->assertSame('Ours.', $this->messageFor('ru'));
    }

    private function messageFor(string $locale): string
    {
        app()->setLocale($locale);

        return (string) Validator::make([], ['title' => 'required'])->errors()->first('title');
    }

    /**
     * @return array<string, mixed>
     */
    private function lines(string $locale): array
    {
        /** @var array<string, mixed> $lines */
        $lines = require __DIR__."/../lang/{$locale}/validation.php";

        return $lines;
    }
}
