<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Checks\Mail;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Mail\MailRoutes;

/**
 * Where the site's letters go, as the doctor, the panel and the agent are told it.
 *
 * On a client's site letters seemed to go nowhere in every mode and nothing said why: a config
 * cached before `.env` was edited, a queue run once a minute, and mail pointed at a test sandbox.
 */
final class MailRoutesTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        putenv('APP_CONFIG_CACHE');

        if ($this->directory !== null) {
            File::deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    #[Test]
    public function the_log_and_array_mailers_are_traps(): void
    {
        $this->mailer('log', ['transport' => 'log']);

        $route = $this->routes()->site();

        $this->assertSame('log', $route->transport);
        $this->assertSame('log', $route->trap);
        $this->assertSame([['code' => 'trap', 'message' => 'Letters go to the “log” mailer: they are written down there and nobody receives them.']], $this->routes()->warnings($route));

        $this->mailer('array', ['transport' => 'array']);
        $this->assertTrue($this->routes()->site()->isTrap());
    }

    #[Test]
    public function a_test_sandbox_is_a_trap_and_a_real_server_is_not(): void
    {
        $this->mailer('smtp', ['transport' => 'smtp', 'host' => 'sandbox.smtp.mailtrap.io', 'port' => 2525]);

        $route = $this->routes()->site();
        $this->assertSame('sandbox.smtp.mailtrap.io:2525', $route->trap);
        $this->assertSame('smtp — sandbox.smtp.mailtrap.io:2525', $route->label());
        $this->assertStringContainsString('a test mailbox', $this->routes()->warnings($route)[0]['message']);

        // Mailpit on a developer's machine, by its port; a channel's own server is asked the same.
        $this->assertTrue($this->routes()->smtp('127.0.0.1', 1025)->isTrap());
        $this->assertTrue($this->routes()->smtp('MAILPIT', 1025)->isTrap());

        // Mailtrap's sending service delivers.
        $this->assertFalse($this->routes()->smtp('live.smtp.mailtrap.io', 587)->isTrap());

        $this->mailer('smtp', ['transport' => 'smtp', 'host' => 'mail.example.test', 'port' => 587]);
        $this->assertNull($this->routes()->site()->trap);
        $this->assertSame([], $this->routes()->warnings($this->routes()->site()));
    }

    #[Test]
    public function a_failover_is_described_by_the_mailer_it_tries_first_and_the_site_names_its_own_traps(): void
    {
        $this->app['config']->set('mail.mailers.backup', ['transport' => 'smtp', 'host' => 'smtp.example.test', 'port' => 465]);
        $this->mailer('failover', ['transport' => 'failover', 'mailers' => ['backup', 'log']]);

        $this->assertSame('smtp — smtp.example.test:465', $this->routes()->site()->label());
        $this->assertFalse($this->routes()->site()->isTrap());

        $this->app['config']->set('webx-admin.mail.traps.hosts', ['*.example.test']);
        $this->assertTrue($this->routes()->site()->isTrap());
    }

    #[Test]
    public function an_env_newer_than_the_cached_config_is_said_and_the_doctor_warns(): void
    {
        $this->mailer('smtp', ['transport' => 'smtp', 'host' => 'mail.example.test', 'port' => 587]);
        $this->assertFalse($this->routes()->staleConfig(), 'nothing is cached here');

        [$env, $cached] = $this->cachedConfig();
        touch($cached, time() - 120);
        touch($env, time() - 60);

        $this->assertTrue($this->routes()->staleConfig());
        $this->assertSame('stale_config', $this->routes()->warnings($this->routes()->site())[0]['code']);

        $found = $this->app->make(Mail::class)->run();
        $this->assertCount(1, $found);
        $this->assertSame(Diagnosis::WARN, $found[0]->level());
        $this->assertStringContainsString('webx:boot', $found[0]->detail);

        // Cached again after the edit: nothing to say but where the letters go.
        touch($cached, time());
        $this->assertFalse($this->routes()->staleConfig());
        $this->assertEquals([Diagnosis::ok('Mail', 'smtp — mail.example.test:587')], $this->app->make(Mail::class)->run());
    }

    #[Test]
    public function the_doctor_notes_a_trap_on_staging_and_warns_on_production(): void
    {
        $this->mailer('log', ['transport' => 'log']);

        $found = $this->app->make(Mail::class)->run();
        $this->assertSame('note', $found[0]->level());
        $this->assertStringContainsString('delivers to nobody', $found[0]->detail);

        $this->app['env'] = 'production';
        $this->assertSame(Diagnosis::WARN, $this->app->make(Mail::class)->run()[0]->level());
    }

    #[Test]
    public function the_queue_says_how_long_a_letter_waits(): void
    {
        $this->app['config']->set('queue.default', 'sync');
        $this->assertSame(['driver' => 'sync', 'sync' => true, 'hint' => null], $this->routes()->queue());

        $this->app['config']->set('queue.default', 'database');
        $this->app['config']->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs']);
        $this->assertStringContainsString('within a minute', (string) $this->routes()->queue()['hint']);

        $this->assertFalse($this->routes()->stalled(now()->subMinutes(2)));
        $this->assertTrue($this->routes()->stalled(now()->subMinutes(6)));
        $this->assertFalse($this->routes()->stalled(null));
    }

    private function routes(): MailRoutes
    {
        return $this->app->make(MailRoutes::class);
    }

    /** @param  array<string, mixed>  $settings */
    private function mailer(string $name, array $settings): void
    {
        $this->app['config']->set('mail.default', $name);
        $this->app['config']->set("mail.mailers.{$name}", $settings);
    }

    /**
     * A site with `.env` and a cached config of its own, in a directory of the test's.
     *
     * @return array{string, string}
     */
    private function cachedConfig(): array
    {
        $this->directory = sys_get_temp_dir().'/webx-mail-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->directory);

        $env = $this->directory.'/.env';
        $cached = $this->directory.'/config.php';
        file_put_contents($env, "MAIL_MAILER=smtp\n");
        file_put_contents($cached, '<?php return [];');

        $this->app->useEnvironmentPath($this->directory);
        putenv('APP_CONFIG_CACHE='.$cached);

        return [$env, $cached];
    }
}
