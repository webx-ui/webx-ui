<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Fixes\ReplaceHost;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditRun;

/**
 * A fix is a button with a preview (decision 4): the preview says what changes, the press
 * changes it through the module's own model, and the finding waits for the next run.
 */
final class FixesTest extends TestCase
{
    private FakeContentSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->source = new FakeContentSource;
        $this->source->records = [
            '1' => [
                'label' => 'Delivery',
                'published' => true,
                'fields' => [
                    'body' => '<a href="https://dev.shop.example.com/sale">sale</a> <img src="//dev.shop.example.com:8080/a.jpg">',
                ],
            ],
        ];

        $this->app->make(AuditContentSources::class)->register($this->source);
    }

    #[Test]
    public function replace_host_previews_then_writes_through_the_source(): void
    {
        $this->artisan('webx:audit:run', ['--quick' => true]);

        $run = AuditRun::query()->latest('id')->firstOrFail();
        $issue = AuditIssue::query()->where('check', 'hosts.dev_content')->firstOrFail();
        $this->assertSame('posts|1|body|en', $issue->key);

        $this->actingAs($this->admin(['audit.view', 'audit.manage']), 'cms');

        $checks = array_column((array) $this->getJson(route('webx.audit.runs.checks', $run))->assertOk()->json('data'), null, 'id');
        $this->assertSame([ReplaceHost::ID], $checks['hosts.dev_content']['fixes'] ?? null);
        $this->assertSame([], $checks['host.index_files']['fixes'] ?? null);

        $this->getJson(route('webx.audit.runs.issues.fixes', [$run, $issue->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', ReplaceHost::ID)
            ->assertJsonPath('data.0.total', 2)
            ->assertJsonPath('data.0.changes.0.label', 'Delivery')
            ->assertJsonPath('data.0.changes.0.edit_url', '/posts/1');

        $this->postJson(route('webx.audit.runs.issues.fixes.store', [$run, $issue->id, ReplaceHost::ID]), ['dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.applied', false);
        $this->assertCount(0, $this->source->replaced, 'A dry run changes nothing.');

        $this->postJson(route('webx.audit.runs.issues.fixes.store', [$run, $issue->id, ReplaceHost::ID]))
            ->assertOk()
            ->assertJsonPath('data.applied', true);

        $this->assertSame(
            '<a href="https://shop.example.com/sale">sale</a> <img src="https://shop.example.com/a.jpg">',
            $this->source->replaced[0]['value'] ?? null,
            'With a scheme or without, with a port or without, the stand becomes the site.',
        );
        $this->assertSame(ReplaceHost::ID, $issue->refresh()->fixed_with);

        // A host that only starts like the stand is somebody else's.
        $this->assertSame(
            ['https://dev.shop.example.com.evil.net/', 0],
            ReplaceHost::swap('https://dev.shop.example.com.evil.net/', ['dev.shop.example.com'], 'https://shop.example.com'),
        );
    }

    #[Test]
    public function pressing_a_fix_needs_manage_and_a_fix_of_that_check(): void
    {
        $this->artisan('webx:audit:run', ['--quick' => true]);

        $run = AuditRun::query()->latest('id')->firstOrFail();
        $issue = AuditIssue::query()->where('check', 'hosts.dev_content')->firstOrFail();

        $this->actingAs($this->admin(['audit.view', 'audit.run']), 'cms')
            ->postJson(route('webx.audit.runs.issues.fixes.store', [$run, $issue->id, ReplaceHost::ID]))
            ->assertForbidden();

        $other = AuditIssue::query()->where('check', '!=', 'hosts.dev_content')->firstOrFail();

        $this->actingAs($this->admin(['audit.manage']), 'cms')
            ->postJson(route('webx.audit.runs.issues.fixes.store', [$run, $other->id, ReplaceHost::ID]))
            ->assertStatus(422);
    }
}
