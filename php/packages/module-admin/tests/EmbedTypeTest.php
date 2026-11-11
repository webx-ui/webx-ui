<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\Embeds;
use WebxUi\Admin\Screens\EmbedSource;
use WebxUi\Admin\Screens\ScreenValues;

/**
 * `wx-embed` through the path a screen saves by: the frame of a registered source is kept as its
 * address and never as HTML; anything else — and anything at all for a source nobody registered —
 * is refused with the source's words.
 */
final class EmbedTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Embeds::class)->register('sample', new class implements EmbedSource
        {
            public function address(string $pasted): ?string
            {
                return preg_match('~src="(https://embed\.example\.com/[a-z0-9]+)"|^(https://embed\.example\.com/[a-z0-9]+)$~', trim($pasted), $found) === 1
                    ? ($found[1] !== '' ? $found[1] : $found[2])
                    : null;
            }

            public function refusal(): string
            {
                return 'Not a frame of the sample.';
            }
        });

        Screens::register('forms.embeds', [['id' => 'card', 'type' => 'wx-card', 'children' => [
            ['id' => 'map', 'type' => 'wx-embed', 'name' => 'map', 'props' => ['source' => 'sample']],
            ['id' => 'other', 'type' => 'wx-embed', 'name' => 'other', 'props' => ['source' => 'nobody']],
        ]]]);
    }

    #[Test]
    public function the_code_is_kept_as_its_address(): void
    {
        $values = $this->app->make(ScreenValues::class);

        $this->assertSame('https://embed.example.com/abc', $values->validate('forms.embeds', ['map' => '<iframe src="https://embed.example.com/abc"></iframe>'])['map']);
        $this->assertSame('https://embed.example.com/abc', $values->validate('forms.embeds', ['map' => ' https://embed.example.com/abc '])['map']);
        $this->assertNull($values->validate('forms.embeds', ['map' => ''])['map']);
    }

    #[Test]
    public function another_frame_is_refused_with_the_sources_words(): void
    {
        try {
            $this->app->make(ScreenValues::class)->validate('forms.embeds', ['map' => '<iframe src="https://elsewhere.example.com/x"></iframe>']);
            $this->fail('A frame of another site was kept.');
        } catch (ValidationException $refused) {
            $this->assertSame(['Not a frame of the sample.'], $refused->errors()['map']);
        }
    }

    #[Test]
    public function a_source_nobody_registered_refuses_everything_but_nothing(): void
    {
        $values = $this->app->make(ScreenValues::class);

        $this->assertNull($values->validate('forms.embeds', ['other' => null])['other']);

        try {
            $values->validate('forms.embeds', ['other' => 'https://embed.example.com/abc']);
            $this->fail('A frame nobody checks was kept.');
        } catch (ValidationException $refused) {
            $this->assertStringContainsString('"nobody"', $refused->errors()['other'][0]);
        }
    }
}
