<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\AuditSettings;
use WebxUi\Audit\Runs\AuditIgnore;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\Mask;
use WebxUi\Audit\Runs\Nightly;
use WebxUi\Audit\Runs\RunAuditStage;
use WebxUi\Audit\Runs\Runner;
use WebxUi\Settings\Settings;

/**
 * What A5 adds around the runs: hiding on purpose, two runs compared, a recheck of a few
 * addresses, the section's settings, the nightly run, the history kept, «Outgoing» and the
 * export of one page.
 */
final class HistoryTest extends TestCase
{
    private const BASE = 'https://shop.example.com';

    /** @var array<string, array{0: int, 1: string, 2: array<string, string>}> */
    private array $site = [];

    #[Test]
    public function a_mask_is_a_path_unless_it_names_the_scheme(): void
    {
        $this->assertTrue(Mask::matches('', null), 'Empty is the whole check, address or not.');
        $this->assertTrue(Mask::matches('/search/**', self::BASE.'/search/a/b'));
        $this->assertFalse(Mask::matches('/search/*', self::BASE.'/search/a/b'));
        $this->assertTrue(Mask::matches('/search/*', 'https://www.shop.example.com/search/a'));
        $this->assertFalse(Mask::matches('/about', self::BASE.'/about-us'));
        $this->assertTrue(Mask::matches(self::BASE.'/about', self::BASE.'/about'));
        $this->assertFalse(Mask::matches('/about', null));
    }

    #[Test]
    public function a_finding_is_hidden_with_a_reason_and_stays_hidden_in_the_next_run(): void
    {
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        config(['app.debug' => true]);

        $runner = $this->app->make(Runner::class);
        $run = $runner->complete($runner->start(AuditRun::QUICK));
        $errors = $run->counts['severity']['error'] ?? 0;
        $this->assertTrue(AuditIssue::query()->where('check', 'config.debug')->exists());

        $this->actingAs($this->admin(['audit.view', 'audit.run']), 'cms')
            ->postJson(route('webx.audit.ignores.store'), ['check' => 'config.debug', 'reason' => 'Staging.'])
            ->assertForbidden();

        $this->actingAs($this->admin(['audit.view', 'audit.manage']), 'cms');

        $this->postJson(route('webx.audit.ignores.store'), ['check' => 'config.debug', 'reason' => 'Staging.', 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.hidden', 1);
        $this->assertSame(0, AuditIgnore::query()->count());

        $this->postJson(route('webx.audit.ignores.store'), ['check' => 'config.debug'])->assertUnprocessable();

        $rule = $this->postJson(route('webx.audit.ignores.store'), ['check' => 'config.debug', 'reason' => 'Staging, on purpose.'])
            ->assertCreated()
            ->assertJsonPath('data.created_by', 'Admin')
            ->json('data.id');

        // Out of the counts at once, not after the next run.
        $this->assertSame($errors - 1, $run->refresh()->counts['severity']['error'] ?? null);

        $this->getJson(route('webx.audit.runs.checks', $run))->assertOk()->assertJsonMissing(['id' => 'config.debug']);
        $this->getJson(route('webx.audit.runs.issues', [$run, 'state' => 'hidden']))
            ->assertOk()
            ->assertJsonPath('data.0.check', 'config.debug')
            ->assertJsonPath('data.0.ignore.reason', 'Staging, on purpose.');
        $this->getJson(route('webx.audit.ignores.index'))->assertOk()->assertJsonPath('data.0.hidden', 1);

        $next = $runner->complete($runner->start(AuditRun::QUICK));
        $this->assertNotNull(AuditIssue::query()->where('run_id', $next->id)->where('check', 'config.debug')->value('ignored_by'));
        $this->assertSame(0, $next->counts['fixed'] ?? null, 'Hidden is not fixed.');

        $this->deleteJson(route('webx.audit.ignores.destroy', $rule))->assertOk();
        $this->assertSame(0, AuditIssue::query()->whereNotNull('ignored_by')->count());
        $this->assertSame($errors, $next->refresh()->counts['severity']['error'] ?? null);
    }

    #[Test]
    public function two_runs_compare_by_fingerprint(): void
    {
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        $runner = $this->app->make(Runner::class);

        config(['app.debug' => true]);
        $first = $runner->complete($runner->start(AuditRun::QUICK));

        config(['app.debug' => false, 'app.env' => 'local']);
        $second = $runner->complete($runner->start(AuditRun::QUICK));

        $this->actingAs($this->admin(['audit.view']), 'cms');

        $rows = $this->getJson(route('webx.audit.runs.compare', ['from' => $first->id, 'to' => $second->id]))
            ->assertOk()
            ->assertJsonPath('data.from.id', $first->id)
            ->json('data.checks');
        $this->assertIsArray($rows);
        $checks = array_column($rows, null, 'check');

        $this->assertSame(1, $checks['config.debug']['fixed'] ?? null);
        $this->assertSame(1, $checks['config.env']['new'] ?? null);

        // Without `from`, the run is compared with the one it was analysed against.
        $this->getJson(route('webx.audit.runs.compare', ['to' => $second->id]))->assertOk()->assertJsonPath('data.from.id', $first->id);

        $this->getJson(route('webx.audit.runs.compare.issues', ['from' => $first->id, 'to' => $second->id, 'kind' => 'fixed', 'check' => 'config.debug']))
            ->assertOk()
            ->assertJsonPath('data.0.check', 'config.debug')
            ->assertJsonCount(1, 'data');

        $this->getJson(route('webx.audit.runs.compare.issues', ['to' => $second->id, 'kind' => 'gone']))->assertUnprocessable();
    }

    #[Test]
    public function a_recheck_asks_only_its_addresses_and_says_what_got_fixed(): void
    {
        $this->fakeSite();
        $runner = $this->app->make(Runner::class);
        $full = $runner->complete($runner->start(AuditRun::FULL));
        $this->assertTrue(AuditIssue::query()->where('run_id', $full->id)->where('check', 'title.missing')->where('url', self::BASE.'/plain')->exists());

        // The page gets its title; the recheck asks it and nothing else.
        $this->site['/plain'][1] = $this->html('A plain page with a title of a decent length at last');

        Bus::fake([RunAuditStage::class]);
        $this->actingAs($this->admin(), 'cms')
            ->postJson(route('webx.audit.runs.store'), ['scope' => 'urls', 'urls' => ['https://elsewhere.example.org/']])
            ->assertUnprocessable();
        $this->postJson(route('webx.audit.runs.store'), ['scope' => 'urls'])->assertUnprocessable();
        $this->postJson(route('webx.audit.runs.store'), ['scope' => 'urls', 'urls' => [self::BASE.'/plain']])
            ->assertCreated()
            ->assertJsonPath('data.scope', 'urls')
            ->assertJsonPath('data.urls', [self::BASE.'/plain']);

        $recheck = $runner->complete(AuditRun::query()->latest('id')->firstOrFail());

        $this->assertSame(AuditRun::DONE, $recheck->status);
        $this->assertSame([self::BASE.'/plain'], AuditPage::query()->where('run_id', $recheck->id)->pluck('url')->all());
        $this->assertSame($full->id, $recheck->counts['previous_id'] ?? null);
        $this->assertGreaterThanOrEqual(1, $recheck->counts['fixed'] ?? 0);
        $this->assertFalse(
            AuditIssue::query()->where('run_id', $recheck->id)->whereIn('check', ['structure.orphan', 'sitemap.missing_page', 'title.duplicate'])->exists(),
            'Checks that need the whole site do not run on one page.',
        );
        $this->assertNotContains('probes', $recheck->progress['done'] ?? []);

        // The overview keeps the full run; the comparison speaks for the rechecked page only.
        $this->getJson(route('webx.audit.runs.latest'))->assertOk()->assertJsonPath('data.done.id', $full->id);
        $fixed = $this->getJson(route('webx.audit.runs.compare.issues', ['to' => $recheck->id, 'kind' => 'fixed']))->assertOk()->json('data');
        $this->assertIsArray($fixed);
        $this->assertNotEmpty($fixed);
        $this->assertSame([self::BASE.'/plain'], array_values(array_unique(array_column($fixed, 'url'))));
    }

    #[Test]
    public function the_settings_screen_saves_and_the_runs_follow_it(): void
    {
        $this->fakeSite();

        $this->actingAs($this->admin(['audit.view', 'audit.run']), 'cms')
            ->putJson(route('webx.audit.settings.update'), ['values' => ['audit.pages-limit' => 10]])
            ->assertForbidden();

        $this->actingAs($this->admin(['audit.view', 'audit.manage']), 'cms');

        $this->putJson(route('webx.audit.settings.update'), ['values' => ['audit.concurrency' => 99]])->assertUnprocessable();

        $saved = $this->putJson(route('webx.audit.settings.update'), ['values' => [
            'audit.pages-limit' => 3,
            'audit.title-max' => 20,
            'audit.exclude' => "/plain\n",
            'audit.keep-runs' => 2,
        ]])->assertOk()->json('data.values');
        $this->assertSame(3, $saved['audit.pages-limit'] ?? null);

        $values = $this->getJson(route('webx.audit.settings.index'))->assertOk()->json('data.values');
        $this->assertSame(20, $values['audit.title-max'] ?? null);

        $runner = $this->app->make(Runner::class);
        $run = $runner->complete($runner->start(AuditRun::FULL));

        $this->assertSame(3, $run->pages_limit);
        $this->assertSame(['/plain'], $run->progress['exclude'] ?? null);
        $this->assertFalse(AuditPage::query()->where('url', self::BASE.'/plain')->exists(), 'An excluded path is not crawled.');
        $this->assertSame(20, config('webx-audit.thresholds.title_max'));

        // A setting emptied again falls back to the file, not to the value laid over it before.
        $this->app->make(Settings::class)->save(['audit.title-max' => null]);
        $this->app->make(AuditSettings::class)->apply();
        $this->assertSame(60, config('webx-audit.thresholds.title_max'));

        // Two runs kept; the newest full one is never pruned.
        $runner->complete($runner->start(AuditRun::QUICK));
        $runner->complete($runner->start(AuditRun::QUICK));
        $runner->complete($runner->start(AuditRun::QUICK));
        $this->assertSame(3, AuditRun::query()->count());
        $this->assertTrue(AuditRun::query()->whereKey($run->id)->exists());
    }

    #[Test]
    public function the_nightly_run_is_off_until_switched_on(): void
    {
        $settings = $this->app->make(AuditSettings::class);
        $this->assertNull($settings->schedule());

        $this->app->make(Settings::class)->save(['audit.schedule' => true, 'audit.schedule-hour' => 4, 'audit.schedule-scope' => 'quick']);
        $this->assertSame(['scope' => 'quick', 'hour' => 4], $settings->schedule());

        Bus::fake([RunAuditStage::class]);
        $run = $this->app->make(Nightly::class)->run('quick');

        $this->assertSame('schedule', $run?->started_by);
        Bus::assertDispatched(RunAuditStage::class);
        $this->assertNull($this->app->make(Nightly::class)->run('quick'), 'Not while another run is going.');
    }

    #[Test]
    public function outgoing_hosts_and_one_page_as_a_file(): void
    {
        $this->fakeSite();
        $runner = $this->app->make(Runner::class);
        $run = $runner->complete($runner->start(AuditRun::FULL));

        $this->actingAs($this->admin(['audit.view']), 'cms');

        $this->getJson(route('webx.audit.hosts.index'))
            ->assertOk()
            ->assertJsonPath('data.crawled_run', $run->id)
            ->assertJsonPath('data.hosts.0.host', 'dev.shop.example.com')
            ->assertJsonPath('data.hosts.0.class', 'dev');

        $this->getJson(route('webx.audit.hosts.index', ['host' => 'dev.shop.example.com']))
            ->assertOk()
            ->assertJsonPath('data.pages.0.page', self::BASE.'/')
            ->assertJsonPath('data.pages.0.kind', 'img');

        $home = AuditPage::query()->where('run_id', $run->id)->where('url', self::BASE.'/')->sole();

        $response = $this->get(route('webx.audit.runs.pages.export-one', [$run, $home]))->assertOk();
        $this->assertStringContainsString('attachment; filename="audit-'.$run->id.'-page-'.$home->id.'.json"', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(self::BASE.'/', $response->json('page.url'));
        $this->assertContains('hosts.dev_page', array_column((array) $response->json('issues'), 'check'));
        $this->assertNotEmpty($response->json('links'));
    }

    private function html(string $title): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
            .'<title>'.$title.'</title></head><body><h1>'.$title.'</h1><a href="/">Home</a></body></html>';
    }

    private function fakeSite(): void
    {
        $html = ['Content-Type' => 'text/html; charset=utf-8'];

        $this->site = [
            '/' => [200, '<!doctype html><html lang="en"><head><title>The garden shop, its tools and its seeds</title></head>'
                .'<body><h1>Shop</h1><a href="/plain">Plain</a> <a href="/about">About</a>'
                .'<img src="https://dev.shop.example.com/storage/hero.jpg" alt="Hero"></body></html>', $html],
            '/about' => [200, $this->html('About the garden shop and the people behind it'), $html],
            '/plain' => [200, '<html><body><p>No title here.</p><a href="/">Home</a></body></html>', $html],
            '/robots.txt' => [200, "User-agent: *\n", ['Content-Type' => 'text/plain']],
        ];

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (! str_starts_with($url, self::BASE.'/')) {
                return Http::response('', 404);
            }

            [$status, $body, $headers] = $this->site[substr($url, strlen(self::BASE))] ?? [404, 'Not found', []];

            return Http::response($body, $status, $headers);
        });
    }
}
