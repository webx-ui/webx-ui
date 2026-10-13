<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Tests\Fixtures\RegionPage;

/**
 * The regions in the panel on the site (SB4 of WEBX_UI_SITE_BAR.md): the header and the footer
 * marked on an administrator's page only, a block of a region edited into the region's draft and
 * drawn as on the page it stands on, its publication and discarding, and the right that opens it —
 * the one of the «Site regions» screen.
 */
final class SiteBarRegionsTest extends RegionTestCase
{
    private const REGIONS = ['blocks.regions'];

    #[Test]
    public function a_visitor_gets_the_page_with_regions_byte_for_byte_as_without_the_bar(): void
    {
        $this->siteWithRegions();

        $with = $this->get('/about')->assertOk();

        $this->app['config']->set('webx-admin.site_bar.enabled', false);
        $without = $this->get('/about')->assertOk();

        $this->assertSame($without->getContent(), $with->getContent());
        $this->assertSame($this->headers($without->headers->all()), $this->headers($with->headers->all()));
        $this->assertStringContainsString('<div class="b-bar">Live bar</div>', (string) $with->getContent());
        $this->assertStringNotContainsString('<!--wx', (string) $with->getContent());
        $this->assertStringNotContainsString('data-wx-bar', (string) $with->getContent());
    }

    #[Test]
    public function the_regions_are_marked_for_whoever_edits_them_and_for_nobody_else(): void
    {
        $this->siteWithRegions();

        $this->signIn($this->editor(self::REGIONS));
        $html = (string) $this->get('/about')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();

        // The region's pair around its blocks, each block with its own — published values.
        $this->assertStringContainsString('<!--wx-region:header--><!--wx:h1--><div class="b-bar">Live bar</div><!--/wx:h1--><!--/wx-region:header-->', $html);
        $this->assertStringContainsString('<!--wx-region:footer--><!--wx:f1-->', $html);
        $this->assertStringNotContainsString('Draft bar', $html);
        $this->assertSame(1, substr_count($html, 'data-wx-bar'));

        // Reading blocks without the regions' right gets nothing on a page without a record.
        $this->signIn($this->editor(['blocks.view', 'blocks.manage']));
        $html = (string) $this->get('/about')->assertOk()->getContent();

        $this->assertStringNotContainsString('<!--wx', $html);
        $this->assertStringNotContainsString('data-wx-bar', $html);
    }

    #[Test]
    public function the_context_offers_the_regions_on_any_address(): void
    {
        $this->siteWithRegions();

        $sections = $this->actingAs($this->editor(self::REGIONS), 'cms')
            ->getJson('/api/cms/site-bar/context?url=/about')
            ->assertOk()
            ->json('data.sections');

        $this->assertSame(['regions'], $sections);

        $this->actingAs($this->editor(['blocks.view']), 'cms')->getJson('/api/cms/site-bar/context?url=/about')->assertForbidden();

        // A site that declares no region has none to offer.
        $this->app['config']->set('webx-blocks.regions', []);
        $this->actingAs($this->editor(self::REGIONS), 'cms')->getJson('/api/cms/site-bar/context?url=/about')->assertForbidden();
    }

    #[Test]
    public function the_tab_reads_a_region_with_its_state(): void
    {
        $this->siteWithRegions();

        $data = $this->actingAs($this->editor(self::REGIONS), 'cms')
            ->getJson($this->content('header'))
            ->assertOk()
            ->json('data');

        $this->assertSame('live', $data['editing']);
        $this->assertSame('h1', $data['outline'][0]['key']);
        $this->assertSame('header', $data['region']['name']);
        $this->assertSame('Header', $data['region']['title']);
        $this->assertTrue($data['region']['published']);
        $this->assertFalse($data['region']['draft']['differs']);

        // Declared and never saved: read empty, printed as the fallback.
        Region::query()->where('name', 'footer')->delete();
        $footer = $this->actingAs($this->editor(self::REGIONS), 'cms')->getJson($this->content('footer'))->assertOk()->json('data');

        $this->assertSame([], $footer['tree']);
        $this->assertFalse($footer['region']['published']);
    }

    #[Test]
    public function an_edit_goes_into_the_regions_draft_and_comes_back_drawn_as_on_the_page(): void
    {
        $this->siteWithRegions();
        $this->publish('here', '<p class="here">{{ $text }} on {{ $entity?->title ?? \'nothing\' }} at [{{ $region?->path() }}]</p>');
        $region = $this->region('header', [$this->node('here', ['text' => 'Live'], 'h1')]);
        $admin = $this->editor(self::REGIONS);
        $revision = $this->revision('header', $admin);

        $response = $this->actingAs($admin, 'cms')
            ->postJson($this->content('header').'/edit', [
                'revision' => $revision,
                'url' => '/about',
                'ops' => [['op' => 'set', 'key' => 'h1', 'values' => ['text' => 'Edited']]],
            ])
            ->assertOk();

        $region->refresh();

        // The draft, not the published tree: visitors keep the header they had.
        $this->assertSame('Edited', $region->draftValues()['blocks'][0]['values']['text']);
        $this->assertSame('Live', $region->blocks[0]['values']['text'] ?? null);
        $this->assertStringContainsString('Live on About', (string) $this->get('/about')->getContent());

        // Drawn from the draft as the page at `url` draws it — its record, its path — with markers.
        $this->assertSame('<!--wx:h1--><p class="here">Edited on About at [about]</p><!--/wx:h1-->', $response->json('data.html'));
        $this->assertSame($this->revision('header', $admin), $response->json('data.revision'));

        $version = $region->versions()->first();
        $this->assertInstanceOf(EntityVersion::class, $version);
        $this->assertSame(EntityVersion::SOURCE_PANEL, $version->source);
        $this->assertSame($admin->getKey(), $version->author_id);

        // The state counts blocks, and says whose draft it is.
        $this->actingAs($admin, 'cms')
            ->getJson($this->content('header'))
            ->assertJsonPath('data.editing', 'draft')
            ->assertJsonPath('data.region.draft.differs', true)
            ->assertJsonPath('data.region.draft.changes', 1)
            ->assertJsonPath('data.region.draft.mine', true);

        $this->actingAs($this->editor(self::REGIONS), 'cms')
            ->getJson($this->content('header'))
            ->assertJsonPath('data.region.draft.mine', false);
    }

    #[Test]
    public function an_edit_on_a_revision_that_moved_or_from_another_site_is_refused(): void
    {
        $this->siteWithRegions();
        $admin = $this->editor(self::REGIONS);
        $revision = $this->revision('header', $admin);

        Region::query()->where('name', 'header')->firstOrFail()->saveDraft(['blocks' => [$this->node('bar', ['text' => 'Theirs'], 'h1')]], authorId: $admin->getKey());

        $this->actingAs($admin, 'cms')
            ->postJson($this->content('header').'/edit', [
                'revision' => $revision,
                'ops' => [['op' => 'set', 'key' => 'h1', 'values' => ['text' => 'Mine']]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('revision', $this->revision('header', $admin));

        $this->actingAs($admin, 'cms')
            ->postJson($this->content('header').'/edit', [
                'url' => 'https://elsewhere.test/about',
                'ops' => [['op' => 'set', 'key' => 'h1', 'values' => ['text' => 'Mine']]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);

        $this->assertSame('Theirs', Region::query()->where('name', 'header')->firstOrFail()->draftValues()['blocks'][0]['values']['text']);
    }

    #[Test]
    public function a_region_keeps_to_its_declaration(): void
    {
        $this->siteWithRegions();
        // The footer takes two blocks; a third put there behind the panel's back is the editor's
        // to sort out in the panel, and no edit from the site goes into such a draft.
        $footer = $this->region('footer', [
            $this->node('bar', ['text' => 'One'], 'f1'),
            $this->node('bar', ['text' => 'Two'], 'f2'),
            $this->node('bar', ['text' => 'Three'], 'f3'),
        ]);

        $this->actingAs($this->editor(self::REGIONS), 'cms')
            ->postJson($this->content('footer').'/edit', ['ops' => [['op' => 'set', 'key' => 'f1', 'values' => ['text' => 'Uno']]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['blocks']);

        $this->assertFalse($footer->refresh()->hasDraft());
    }

    #[Test]
    public function the_regions_tab_is_behind_the_regions_right(): void
    {
        $this->siteWithRegions();
        $edit = ['ops' => [['op' => 'set', 'key' => 'h1', 'values' => ['text' => 'x']]]];

        $this->actingAs($this->editor(['blocks.view', 'blocks.manage']), 'cms')->getJson($this->content('header'))->assertForbidden();
        $this->actingAs($this->editor(['blocks.view', 'blocks.manage']), 'cms')->postJson($this->content('header').'/edit', $edit)->assertForbidden();
        $this->actingAs($this->editor(self::REGIONS), 'cms')->getJson($this->content('sidebar'))->assertNotFound();

        $this->assertFalse(Region::query()->where('name', 'header')->firstOrFail()->hasDraft());
    }

    #[Test]
    public function publish_puts_the_regions_draft_on_the_site_and_discard_takes_it_back(): void
    {
        $this->siteWithRegions();
        $admin = $this->editor(self::REGIONS);

        $this->actingAs($admin, 'cms')
            ->postJson($this->content('header').'/edit', ['url' => '/about', 'ops' => [['op' => 'set', 'key' => 'h1', 'values' => ['text' => 'New bar']]]])
            ->assertOk();

        // The revision the tab holds is the one the region's own editor holds: publishing with it
        // is not a conflict.
        $this->actingAs($admin, 'cms')
            ->postJson('/api/cms/regions/header/publish', ['revision' => $this->revision('header', $admin)])
            ->assertOk();

        $this->assertStringContainsString('<div class="b-bar">New bar</div>', (string) $this->get('/about')->getContent());

        $this->actingAs($admin, 'cms')
            ->postJson($this->content('header').'/edit', ['ops' => [['op' => 'set', 'key' => 'h1', 'values' => ['text' => 'Dropped']]]])
            ->assertOk();
        $this->actingAs($admin, 'cms')->deleteJson('/api/cms/regions/header/draft')->assertOk();

        $header = Region::query()->where('name', 'header')->firstOrFail();
        $this->assertFalse($header->hasDraft());
        $this->assertSame('New bar', $header->blocks[0]['values']['text'] ?? null);
    }

    /** A published page at `/about` under a published header and footer. */
    private function siteWithRegions(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->region('header', [$this->node('bar', ['text' => 'Live bar'], 'h1')]);
        $this->region('footer', [$this->node('bar', ['text' => 'Footer words'], 'f1')]);

        $header = Region::query()->where('name', 'header')->firstOrFail();
        $header->saveDraft(['blocks' => [$this->node('bar', ['text' => 'Draft bar'], 'h1')]]);
        $header->discardDraft();

        RegionPage::query()->create(['slug' => 'about', 'title' => 'About'])->publish();
    }

    private function content(string $region): string
    {
        return '/api/cms/blocks/content/region/'.$region;
    }

    private function revision(string $region, CmsUser $admin): string
    {
        return (string) $this->actingAs($admin, 'cms')->getJson($this->content($region))->json('data.revision');
    }

    private function signIn(CmsUser $admin): void
    {
        // By name, as a real session would: a page of the site has no `cms` default guard.
        $this->app['auth']->guard('cms')->setUser($admin);
    }

    /**
     * The headers to compare, less the two that differ between any two responses.
     *
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, list<string|null>>
     */
    private function headers(array $headers): array
    {
        unset($headers['date'], $headers['set-cookie']);

        return $headers;
    }
}
