<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Debug\Reports;

/**
 * A report is its maker's, for minutes (§9 of the debug bar spec): anybody else's id and an
 * expired one are the same 404, and nothing of it is cached on the way.
 */
final class ReportsTest extends TestCase
{
    #[Test]
    public function the_maker_reads_the_report_and_nobody_else_does(): void
    {
        $maker = $this->admin();
        $id = (string) $this->tagData($this->asAdmin($maker, '/plain'))['id'];

        $this->actingAs($maker, 'cms')->getJson("/api/cms/debug/reports/{$id}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.url', '/plain');

        // Another super administrator: not a 403, which would say the id is real.
        $this->actingAs($this->admin(), 'cms')->getJson("/api/cms/debug/reports/{$id}")->assertNotFound();

        // An administrator without the permission does not reach the route at all.
        $this->actingAs($this->admin(super: false, permissions: ['pages.manage']), 'cms')
            ->getJson("/api/cms/debug/reports/{$id}")
            ->assertForbidden();
    }

    #[Test]
    public function an_expired_report_is_gone(): void
    {
        $maker = $this->admin();
        $id = (string) $this->tagData($this->asAdmin($maker, '/plain'))['id'];

        $this->travel((int) config('webx-debug.ttl') + 1)->seconds();

        $this->actingAs($maker, 'cms')->getJson("/api/cms/debug/reports/{$id}")->assertNotFound();
        $this->actingAs($maker, 'cms')->getJson('/api/cms/debug/reports')->assertOk()->assertJsonPath('data', []);
    }

    #[Test]
    public function the_latest_are_listed_newest_first_and_only_so_many_are_kept(): void
    {
        $this->app['config']->set('webx-debug.keep', 2);
        $maker = $this->admin();

        $ids = [];

        foreach (['/plain', '/view', '/cached'] as $path) {
            $ids[] = (string) $this->tagData($this->asAdmin($maker, $path))['id'];
        }

        $listed = $this->actingAs($maker, 'cms')->getJson('/api/cms/debug/reports')->assertOk()->json('data');

        $this->assertSame([$ids[2], $ids[1]], array_column($listed, 'id'));
        $this->assertSame('/cached', $listed[0]['url']);
        $this->assertArrayHasKey('queries', $listed[0]['summary']);
        // The oldest is forgotten when a newer one comes over the limit.
        $this->assertNull($this->app->make(Reports::class)->find($ids[0], $maker->getKey()));
    }

    #[Test]
    public function a_malformed_id_is_not_a_route(): void
    {
        $this->actingAs($this->admin(), 'cms')->getJson('/api/cms/debug/reports/not-an-id')->assertNotFound();
    }
}
