<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\ProbeSet;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Blocks\Audit\PruneStrayValuesFix;
use WebxUi\Blocks\Audit\StrayValuesCheck;
use WebxUi\Blocks\StrayValues;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Values for fields a block type does not have — left by an import or by a field taken out of
 * the type: how a block is told apart without them, and the two ways of taking them out.
 */
final class StrayValuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $this->publish('hero', '<section data-wx-block="hero">{{ $heading }}</section>', [], ['schema' => [
            ['id' => 'image', 'type' => 'wx-media'],
            ['id' => 'button_url', 'type' => 'wx-input'],
            ['id' => 'heading', 'type' => 'wx-input', 'localized' => true],
        ]]);
        $this->publish('reviews', '<section data-wx-block="reviews">{{ $layout }}</section>', [], ['schema' => [
            ['id' => 'layout', 'type' => 'wx-segmented'],
            ['id' => 'note', 'type' => 'wx-rich-text'],
        ]]);
    }

    #[Test]
    public function the_outline_names_a_block_by_what_its_fields_mean(): void
    {
        $page = $this->page([
            // A stray demo title first, a picture and an address before the heading.
            $this->node('hero', ['title' => 'Pages made of blocks', 'image' => 'media/8d/1e/a.png', 'button_url' => '/cms', 'heading' => ['en' => 'Deeply heard']], 'k-hero'),
            // A setting first, then a text in markup.
            $this->node('reviews', ['layout' => 'grid', 'note' => '<p>What <b>clients</b> say</p>'], 'k-reviews'),
            // Nothing that reads as a name: the type's own title.
            $this->node('reviews', ['layout' => 'grid'], 'k-empty'),
        ]);

        $this->agent('get_content', ['entity' => 'note', 'id' => $page->id, 'outline' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $labels = array_column($json->etc()->toArray()['outline'], 'label', 'key');
                self::assertSame(['k-hero' => 'Deeply heard', 'k-reviews' => 'What clients say', 'k-empty' => 'Reviews'], $labels);
            });
    }

    #[Test]
    public function an_agent_takes_values_out_of_a_block_and_a_dry_run_changes_nothing(): void
    {
        $page = $this->page([$this->node('hero', ['title' => 'Stray', 'subtitle' => 'Also stray', 'heading' => ['en' => 'Kept']], 'k-hero')]);
        $op = ['op' => 'unset', 'key' => 'k-hero', 'fields' => ['title', 'subtitle']];

        $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'ops' => [$op], 'dry_run' => true])->assertOk();
        $this->assertArrayHasKey('title', $this->values($page));

        $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'ops' => [$op]])->assertOk();
        $this->assertSame(['heading' => ['en' => 'Kept']], $this->values($page));

        $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'ops' => [['op' => 'unset', 'key' => 'k-hero']]])
            ->assertHasErrors(['`fields` is required by unset']);
    }

    #[Test]
    public function the_prune_command_takes_out_what_no_type_defines_live_and_in_the_draft(): void
    {
        $nested = $this->node('reviews', ['layout' => 'grid', 'rich-text' => 'stray'], 'k-inner');
        $page = $this->page([
            $this->node('hero', ['title' => 'Stray', 'heading' => ['en' => 'Kept'], 'inside' => [$nested]], 'k-hero'),
            $this->node('gone-type', ['anything' => 'left alone'], 'k-unknown'),
        ]);

        $page->saveDraft(['blocks' => [$this->node('hero', ['title' => 'In the draft', 'heading' => ['en' => 'Next']], 'k-hero')]]);

        $this->artisan('webx:blocks:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertArrayHasKey('title', $this->values($page));

        $this->artisan('webx:blocks:prune')->assertSuccessful();

        $this->assertSame(['heading' => ['en' => 'Next']], $this->values($page), 'the draft is cleaned too');

        $blocks = $page->refresh()->blocks;
        $this->assertSame(['heading' => ['en' => 'Kept']], $blocks[0]['values'], 'the nested list was a stray field too');
        $this->assertSame(['anything' => 'left alone'], $blocks[1]['values'], 'no schema to measure an unknown type by');

        $this->artisan('webx:blocks:prune')->expectsOutputToContain('only the fields its type defines')->assertSuccessful();
    }

    #[Test]
    public function the_audit_reports_one_finding_per_entity_and_its_fix_cleans_that_entity_only(): void
    {
        $this->publish('faq', '<section data-wx-block="faq"></section>', [], ['schema' => [
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'question', 'type' => 'wx-input'], ['id' => 'answer', 'type' => 'wx-textarea']]],
        ]]);

        $dirty = $this->page([
            $this->node('hero', ['title' => 'Stray', 'heading' => ['en' => 'Kept']], 'k-hero'),
            // A repeater item measured against the repeater's own fields.
            $this->node('faq', ['items' => [['question' => 'Why?', 'answer' => 'Because.', 'legacy' => 'x']]], 'k-faq'),
        ]);
        $clean = $this->page([$this->node('hero', ['heading' => ['en' => 'Fine']], 'k-ok')]);

        $findings = $this->audit();

        $this->assertCount(1, $findings, 'the clean page is not reported');
        $this->assertSame(StrayValuesCheck::ID, $findings[0]->check);
        $this->assertSame('notice', $findings[0]->severity);
        $this->assertSame(
            [['hero · k-hero', 'title'], ['faq · k-faq', 'items.*.legacy']],
            array_map(static fn (array $row): array => [$row['block'], $row['fields']], $findings[0]->details['table']['rows'] ?? []),
        );

        $fix = $this->app->make(PruneStrayValuesFix::class);
        $this->assertTrue($fix->available($findings[0]));
        $this->assertCount(2, $fix->preview($findings[0])->changes, 'a dry run names what would go');
        $this->assertArrayHasKey('title', $dirty->refresh()->blocks[0]['values'], 'and changes nothing');

        $fix->apply($findings[0]);

        $blocks = $dirty->refresh()->blocks;
        $this->assertSame(['heading' => ['en' => 'Kept']], $blocks[0]['values']);
        $this->assertSame([['question' => 'Why?', 'answer' => 'Because.']], $blocks[1]['values']['items']);
        $this->assertFalse($fix->available($findings[0]));
        $this->assertSame([], $this->audit());
        $this->assertSame(['heading' => ['en' => 'Fine']], $clean->refresh()->blocks[0]['values']);
    }

    #[Test]
    public function a_field_the_type_does_not_have_is_refused_at_the_door(): void
    {
        $this->publish('faq', '<section data-wx-block="faq"></section>', [], ['schema' => [
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'question', 'type' => 'wx-input']]],
        ]]);
        $page = $this->page([
            $this->node('hero', ['legacy' => 'from an import', 'heading' => ['en' => 'Kept']], 'k-hero'),
            $this->node('faq', ['items' => [['question' => 'Why?']]], 'k-faq'),
        ]);
        $edit = fn (array $op, bool $dry = false): TestResponse => $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'ops' => [$op], 'dry_run' => $dry]);

        foreach ([true, false] as $dry) {
            $edit(['op' => 'set', 'key' => 'k-hero', 'values' => ['audit_test_stray' => 'x']], $dry)
                ->assertHasErrors(['hero has no field [audit_test_stray]. Its fields: image, button_url, heading.']);
        }

        $edit(['op' => 'set', 'key' => 'k-faq', 'values' => ['items' => [['question' => 'Why?', 'extra' => 'x']]]])
            ->assertHasErrors(['faq has no field [items.*.extra]']);
        $edit(['op' => 'add', 'type' => 'hero', 'values' => ['subtitle' => 'x']])
            ->assertHasErrors(['hero has no field [subtitle]']);
        $this->assertFalse($page->refresh()->hasDraft(), 'nothing of it was written');

        // A stray value the block already holds may still be emptied.
        $edit(['op' => 'set', 'key' => 'k-hero', 'values' => ['legacy' => null, 'heading' => ['en' => 'New']]])->assertOk();
    }

    #[Test]
    public function a_draft_that_differed_only_by_stray_values_is_dropped_by_the_prune(): void
    {
        $page = $this->page([$this->node('hero', ['heading' => ['en' => 'Live']], 'k-hero')]);
        $page->publish();
        $page->saveDraft(['blocks' => [$this->node('hero', ['heading' => ['en' => 'Live'], 'audit_test_stray' => 'x'], 'k-hero')]]);
        $this->assertTrue($page->refresh()->hasDraft());

        $report = $this->app->make(StrayValues::class)->find($page);
        $this->assertTrue($report->draftDropped, 'the dry run says so');
        $this->assertTrue($page->refresh()->hasDraft(), 'and changes nothing');

        $this->artisan('webx:blocks:prune')->expectsOutputToContain('Drafts dropped')->assertSuccessful();
        $this->assertFalse($page->refresh()->hasDraft());

        // A draft with a real edit in it stays, cleaned.
        $page->saveDraft(['blocks' => [$this->node('hero', ['heading' => ['en' => 'Next'], 'audit_test_stray' => 'x'], 'k-hero')]]);
        $this->app->make(StrayValues::class)->prune($page);
        $this->assertSame(['heading' => ['en' => 'Next']], $this->values($page));
    }

    /**
     * @return list<Finding>
     */
    private function audit(): array
    {
        $context = new AuditContext(new AuditRun, new HostClassifier(['example.test']), $this->app->make(SiteClient::class), new ProbeSet, $this->app->make('config'));

        return array_values(iterator_to_array($this->app->make(StrayValuesCheck::class)->run($context), false));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(array $blocks): Page
    {
        return Page::query()->create(['title' => 'About', 'slug' => 'about', 'blocks' => $blocks]);
    }

    /**
     * The first block as an edit sees it: the draft's when there is one.
     *
     * @return array<string, mixed>
     */
    private function values(Page $page): array
    {
        $page->refresh();
        $blocks = $page->draftValues()['blocks'] ?? $page->blocks;

        return $blocks[0]['values'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }
}
