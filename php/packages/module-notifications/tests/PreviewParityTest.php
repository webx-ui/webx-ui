<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Providers;
use WebxUi\Notifications\Sending\Renderer;
use WebxUi\Notifications\Templates\TemplateError;

/**
 * The panel draws a channel's message as it is typed, with `mustache.js` (stage 4, §7.5); the
 * server sends it with `mustache/mustache`. Both read the cases of `fixtures/render.json` — this
 * test here, `packages/module-notifications/src/render.test.ts` there — so the preview cannot show
 * one message while the chat gets another without one of the two going red.
 */
final class PreviewParityTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        /** @var array{cases: list<array<string, mixed>>} $fixtures */
        $fixtures = json_decode((string) file_get_contents(__DIR__.'/fixtures/render.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($fixtures['cases'] as $case) {
            yield (string) $case['name'] => [$case];
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[Test]
    #[DataProvider('cases')]
    public function the_server_renders_what_the_preview_shows(array $case): void
    {
        /** @var array<string, mixed> $values */
        $values = $case['values'];

        if (isset($case['repeat']) && is_array($case['repeat'])) {
            $values[(string) $case['repeat']['name']] = str_repeat((string) $case['repeat']['text'], (int) $case['repeat']['times']);
        }

        $provider = $this->app->make(Providers::class)->find((string) $case['provider']);
        $this->assertNotNull($provider);

        $channel = new Channel([
            'provider' => $case['provider'],
            'template' => ['en' => $case['template']],
            'subject' => isset($case['subject']) ? ['en' => $case['subject']] : null,
        ]);

        if (isset($case['error'])) {
            try {
                $this->app->make(Renderer::class)->render($channel, $provider, $values, 'en', null);
                $this->fail('The template should have been refused: '.$case['template']);
            } catch (TemplateError $error) {
                $this->assertSame($case['error'], $error->key);
            }

            return;
        }

        $rendered = $this->app->make(Renderer::class)->render($channel, $provider, $values, 'en', null);
        $message = $rendered['message'];

        if (isset($case['body'])) {
            $this->assertSame($case['body'], $message->body);
        }

        if (isset($case['plain'])) {
            $this->assertSame($case['plain'], $message->plain);
        }

        if (isset($case['subject_out'])) {
            $this->assertSame($case['subject_out'], $message->subject);
        }

        if (isset($case['truncated'])) {
            $this->assertSame($case['truncated'], $message->truncated);
            $this->assertSame($case['length'], mb_strlen($message->plain));
            $this->assertSame($provider->escape($message->plain), $message->body);
        }

        $this->assertSame($case['unknown'] ?? [], $rendered['unknown']);
    }
}
