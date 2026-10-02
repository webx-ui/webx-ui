<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Runs\AuditIssue;

/**
 * The production config (§5.1): said on a working domain, kept quiet on a stand, where
 * `APP_DEBUG` and a log mailer are the point.
 */
final class ConfigTest extends TestCase
{
    #[Test]
    public function a_working_domain_with_a_development_config_is_told(): void
    {
        $this->site();
        config([
            'app.debug' => true,
            'app.env' => 'local',
            'mail.default' => 'log',
            'queue.default' => 'sync',
            'filesystems.links' => [public_path('storage') => storage_path('app/public')],
        ]);

        $this->artisan('webx:audit:run', ['--quick' => true, '--fail-on' => 'error'])->assertFailed();

        $found = AuditIssue::query()->where('check', 'like', 'config.%')->pluck('severity', 'check')->all();
        ksort($found);

        $this->assertSame([
            'config.debug' => 'error',
            'config.env' => 'warning',
            'config.mail' => 'error',
            'config.queue' => 'warning',
            'config.storage_link' => 'error',
        ], $found);
    }

    #[Test]
    public function a_stand_keeps_its_development_config_in_peace(): void
    {
        $this->site();
        config(['app.url' => 'http://shop.local', 'app.debug' => true, 'app.env' => 'local', 'mail.default' => 'log']);

        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $this->assertSame([], AuditIssue::query()->where('check', 'like', 'config.%')->pluck('check')->all());
    }

    #[Test]
    public function an_app_url_the_site_redirects_away_from_is_an_error(): void
    {
        Http::fake(['*' => Http::response('', 301, ['Location' => 'https://www.shop.example.com/'])]);

        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $issue = AuditIssue::query()->where('check', 'config.app_url')->sole();

        $this->assertSame('error', $issue->severity);
        $this->assertSame('webx-audit::details.app-url-redirects', $issue->details['summary']['key'] ?? null);
    }

    #[Test]
    public function a_bad_level_for_fail_on_is_refused(): void
    {
        $this->site();
        $this->artisan('webx:audit:run', ['--quick' => true, '--fail-on' => 'fatal'])->assertExitCode(2);
    }

    private function site(): void
    {
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
    }
}
