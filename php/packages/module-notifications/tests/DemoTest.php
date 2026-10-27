<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Notifications\Models\Channel;

/**
 * The demo (§16.2): one e-mail channel, switched off, without a secret and to a made-up address,
 * whose template shows a list — and gone again with `--remove`.
 */
final class DemoTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The demo's pictures go in as they are: what the pipeline makes of an upload is the media
        // module's test, and turning every demo photo into webp was a third of this one.
        $app['config']->set('webx-media.optimize.enabled', false);
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_one_email_channel_switched_off(): void
    {
        $this->artisan('webx:demo', ['--module' => ['notifications']])->assertSuccessful();

        $channel = Channel::query()->sole();

        $this->assertSame('Example letter (demo)', $channel->name);
        $this->assertSame('email', $channel->provider);
        $this->assertNull($channel->event);
        $this->assertFalse($channel->enabled);
        $this->assertSame([], $channel->credentialsSet());
        $this->assertSame(['mode' => 'site'], array_intersect_key($channel->options(), ['mode' => true]));
        $this->assertSame([['email' => 'manager@example.com']], $channel->recipientList());
        // Both languages of the site, the list section in each.
        $this->assertSame(['en', 'ru'], array_keys($channel->getTranslations('template')));
        $this->assertStringContainsString('{{#items}}', $channel->templateIn('en'));
        $this->assertStringContainsString('{{^items}}', $channel->templateIn('ru'));
    }

    #[Test]
    public function a_site_with_a_channel_of_its_own_gets_none(): void
    {
        $this->telegram()->delete();

        $this->artisan('webx:demo', ['--module' => ['notifications']])->assertSuccessful();

        $this->assertSame(1, Channel::withTrashed()->count());
    }

    #[Test]
    public function removing_takes_it_back_out(): void
    {
        $this->artisan('webx:demo', ['--module' => ['notifications']])->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true, '--module' => ['notifications']])->assertSuccessful();

        $this->assertSame(0, Channel::withTrashed()->count());
    }
}
