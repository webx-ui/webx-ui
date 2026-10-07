<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\ScreenValues;

/**
 * `wx-input` with `props.type` — email, url, tel — holds the value to that format on the server,
 * on the screen and in the rows of a repeater alike.
 */
final class InputFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Screens::register('settings.index', [
            ['id' => 'email', 'type' => 'wx-input', 'name' => 'contacts.email', 'props' => ['type' => 'email']],
            ['id' => 'site', 'type' => 'wx-input', 'name' => 'contacts.site', 'props' => ['type' => 'url']],
            ['id' => 'phone', 'type' => 'wx-input', 'name' => 'contacts.phone', 'props' => ['type' => 'tel']],
            ['id' => 'note', 'type' => 'wx-input', 'name' => 'contacts.note'],
            [
                'id' => 'socials',
                'type' => 'wx-repeater',
                'name' => 'org.socials',
                'children' => [
                    ['id' => 'url', 'type' => 'wx-input', 'name' => 'url', 'props' => ['type' => 'url']],
                ],
            ],
        ]);
    }

    private function values(): ScreenValues
    {
        return $this->app->make(ScreenValues::class);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function good(): iterable
    {
        yield 'an email' => ['contacts.email', 'team@example.com'];
        yield 'an https address' => ['contacts.site', 'https://example.com/about'];
        yield 'an http address' => ['contacts.site', 'http://example.com'];
        yield 'an international number' => ['contacts.phone', '+44 (0)20 7946-0958'];
        yield 'a number with dots' => ['contacts.phone', '030.123.456'];
        yield 'an empty email' => ['contacts.email', ''];
        yield 'a null address' => ['contacts.site', null];
        yield 'an empty number' => ['contacts.phone', ''];
        yield 'free text without a type' => ['contacts.note', 'not-an-email'];
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function bad(): iterable
    {
        yield 'not an email' => ['contacts.email', 'not-an-email'];
        yield 'not an address' => ['contacts.site', 'example'];
        yield 'an address of another scheme' => ['contacts.site', 'ftp://example.com'];
        yield 'letters in a number' => ['contacts.phone', 'call me'];
        yield 'too few digits' => ['contacts.phone', '+1'];
    }

    #[Test]
    #[DataProvider('good')]
    public function a_value_of_the_format_is_kept(string $name, mixed $value): void
    {
        $this->assertSame([$name => $value], $this->values()->validate('settings.index', [$name => $value]));
    }

    #[Test]
    #[DataProvider('bad')]
    public function a_value_out_of_the_format_is_refused(string $name, mixed $value): void
    {
        try {
            $this->values()->validate('settings.index', [$name => $value]);
            $this->fail("[{$name}] took a value out of its format.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($name, $exception->errors());
        }
    }

    #[Test]
    public function a_row_of_a_repeater_is_held_to_its_format(): void
    {
        $this->assertSame(
            ['org.socials' => [['url' => 'https://example.com/team']]],
            $this->values()->validate('settings.index', ['org.socials' => [['url' => 'https://example.com/team']]]),
        );

        try {
            $this->values()->validate('settings.index', ['org.socials' => [['url' => 'https://example.com'], ['url' => 'nope']]]);
            $this->fail('A row with a bad address was kept.');
        } catch (ValidationException $exception) {
            $messages = implode(' ', $exception->errors()['org.socials'] ?? []);

            $this->assertStringContainsString('2', $messages);
        }
    }
}
