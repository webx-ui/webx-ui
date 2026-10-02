<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\AuditServiceProvider;
use WebxUi\Audit\Fixes\FixRunner;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Seo\Audit\CollapseChainFix;
use WebxUi\Seo\Audit\SeoChecks;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Normalisation;
use WebxUi\Settings\Settings;

/**
 * What SEO brings to the audit when the audit is installed: checks of its tables, and fixes that
 * change settings and rows of this module — never anything the audit owns.
 */
final class AuditTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuditServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
    }

    #[Test]
    public function a_chain_in_the_table_is_found_once_and_collapsed_by_the_fix(): void
    {
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/a', 'target' => '/b']);
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/b', 'target' => '/c']);
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/c', 'target' => '/d']);

        $this->artisan('webx:audit:run', ['--quick' => true]);

        $issues = AuditIssue::query()->where('check', SeoChecks::REDIRECT_CHAIN)->get();
        $this->assertCount(1, $issues, 'One finding at the head, not one per hop.');

        $runner = $this->app->make(FixRunner::class);
        $offers = $runner->offers($issues[0]);

        $this->assertSame(CollapseChainFix::ID, $offers[0]['id'] ?? null);
        $this->assertSame(2, $offers[0]['total'] ?? null, '/a and /b are pointed at /d; /c already is.');

        $runner->run($issues[0], CollapseChainFix::ID, false);

        $this->assertSame(['/d', '/d', '/d'], SeoRedirect::query()->orderBy('id')->pluck('target')->all());
    }

    #[Test]
    public function a_normalisation_fix_turns_its_setting_on_and_is_not_offered_twice(): void
    {
        $run = AuditRun::query()->create(['status' => AuditRun::DONE, 'scope' => AuditRun::QUICK, 'base_url' => 'https://shop.example.com']);
        $issue = AuditIssue::query()->create([
            'run_id' => $run->id,
            'check' => 'host.trailing_slash',
            'severity' => 'warning',
            'url' => 'https://shop.example.com/about/',
            'details' => ['summary' => ['key' => 'x', 'params' => ['url' => 'https://shop.example.com/about', 'other' => 'https://shop.example.com/about/']]],
            'fingerprint' => sha1('x'),
        ]);

        $runner = $this->app->make(FixRunner::class);

        $this->assertSame('seo.normalise-trailing', $runner->offers($issue)[0]['id'] ?? null);
        $runner->run($issue, 'seo.normalise-trailing', false);

        $this->assertSame(Normalisation::STRIP, $this->app->make(Settings::class)->get(Normalisation::TRAILING));
        $this->assertSame([], $runner->offers($issue), 'Already on: the web server is what answers now.');
    }

    #[Test]
    public function the_sitemap_line_is_written_into_robots_txt(): void
    {
        $run = AuditRun::query()->create(['status' => AuditRun::DONE, 'scope' => AuditRun::QUICK, 'base_url' => 'https://shop.example.com']);
        $issue = AuditIssue::query()->create([
            'run_id' => $run->id,
            'check' => 'robots.no_sitemap',
            'severity' => 'warning',
            'url' => 'https://shop.example.com/robots.txt',
            'fingerprint' => sha1('y'),
        ]);

        $this->app->make(FixRunner::class)->run($issue, 'seo.robots-sitemap', false);

        $this->assertStringContainsString("User-agent: *\nDisallow:\n\nSitemap: ", (string) $this->app->make(Settings::class)->get('seo.robots-txt'));
    }
}
