<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\RunAuditStage;
use WebxUi\Audit\Runs\Runner;

/** The section's API: starting a run, the overview, the findings by check and by address. */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_section_is_in_the_system_group(): void
    {
        $module = $this->app->make(ModuleRegistry::class)->get('audit');

        $this->assertSame('system', $module->group());
        $this->assertSame(['audit.view', 'audit.run', 'audit.manage'], $module->permissions());
    }

    #[Test]
    public function a_run_goes_to_the_queue(): void
    {
        Bus::fake();

        $this->actingAs($this->admin(), 'cms')
            ->postJson(route('webx.audit.runs.store'), ['scope' => 'quick'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.base_url', 'https://shop.example.com');

        Bus::assertDispatched(RunAuditStage::class);

        // One at a time: a second press while the first is going is refused.
        $this->postJson(route('webx.audit.runs.store'), ['scope' => 'full'])->assertStatus(409);
    }

    #[Test]
    public function a_sync_queue_is_refused_with_the_command_that_works(): void
    {
        config(['queue.default' => 'sync']);

        $this->actingAs($this->admin(), 'cms')
            ->postJson(route('webx.audit.runs.store'), ['scope' => 'quick'])
            ->assertStatus(409)
            ->assertJsonFragment(['message' => __('webx-audit::page.sync-queue')]);

        $this->getJson(route('webx.audit.runs.latest'))->assertOk()->assertJsonPath('data.queue.sync', true);
        $this->assertSame(0, AuditRun::query()->count());
    }

    #[Test]
    public function looking_is_not_running(): void
    {
        $this->actingAs($this->admin(['audit.view']), 'cms')
            ->postJson(route('webx.audit.runs.store'), ['scope' => 'quick'])
            ->assertForbidden();

        $this->getJson(route('webx.audit.runs.latest'))->assertOk()->assertJsonPath('data.done', null);
    }

    #[Test]
    public function the_findings_are_listed_by_check_with_their_texts_then_by_address(): void
    {
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        config(['app.debug' => true]);

        $runner = $this->app->make(Runner::class);
        $run = $runner->complete($runner->start(AuditRun::QUICK));

        $this->actingAs($this->admin(['audit.view']), 'cms');

        $this->getJson(route('webx.audit.runs.latest'))
            ->assertOk()
            ->assertJsonPath('data.done.id', $run->id)
            ->assertJsonPath('data.done.counts.severity.error', 8);

        /** @var list<array<string, mixed>> $checks */
        $checks = $this->getJson(route('webx.audit.runs.checks', ['run' => $run->id, 'severity' => 'error']))
            ->assertOk()
            ->json('data');

        // The sitemap answers the same HTML as everything else, so it does not parse.
        $this->assertSame(['config.debug', 'host.https', 'host.index_files', 'host.mirror', 'host.soft_404', 'sitemap.missing'], collect($checks)->pluck('id')->sort()->values()->all());

        $debug = collect($checks)->firstWhere('id', 'config.debug');
        $this->assertIsArray($debug);
        $this->assertSame('Debug mode on a working domain', $debug['title']);
        $this->assertNotSame('', $debug['why']);
        $this->assertSame(1, $debug['new']);

        $this->getJson(route('webx.audit.runs.checks', ['run' => $run->id, 'group' => 'config']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson(route('webx.audit.runs.issues', ['run' => $run->id, 'check' => 'host.index_files']))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.details.summary', '/index.php answers 200.');

        $this->getJson(route('webx.audit.runs.issues', ['run' => $run->id, 'check' => 'host.security_headers']))
            ->assertOk()
            ->assertJsonPath('data.0.details.table.columns.0.label', 'Header')
            ->assertJsonPath('data.0.details.table.rows.0.header', 'X-Content-Type-Options');
    }

    #[Test]
    public function a_run_can_be_cancelled(): void
    {
        Bus::fake();
        $run = $this->app->make(Runner::class)->start(AuditRun::FULL);

        $this->actingAs($this->admin(), 'cms')
            ->postJson(route('webx.audit.runs.cancel', ['run' => $run->id]))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertFalse($this->app->make(Runner::class)->step($run->refresh(), 10));
    }
}
