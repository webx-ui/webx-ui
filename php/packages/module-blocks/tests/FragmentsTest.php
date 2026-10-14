<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Log;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Doctor\FragmentsCheck;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Tests\Fixtures\RegionPage;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Shared blocks (F1 of the shared blocks spec): a region declared with `fragment`, placed in a
 * page's content by the offered block «Shared block» — printed there with its styles, never inside
 * itself, copied into a page as that page's own, previewed on a page that holds it, and found
 * wherever the content places it.
 */
final class FragmentsTest extends RegionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.regions', [
            ...$this->app['config']->get('webx-blocks.regions'),
            'request' => ['title' => 'Request', 'description' => 'The request form above the footer.', 'fragment' => true],
            'promo' => ['title' => 'Promo', 'fragment' => true],
        ]);
        $this->app['config']->set('webx-blocks.entities', [RegionPage::class]);

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['blocks']])->assertSuccessful();

        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>', [], [
            'schema' => [['id' => 'text', 'type' => 'wx-input']],
            'styles' => '.b-bar { color: teal; }',
        ]);
    }

    #[Test]
    public function the_declaration_says_which_regions_are_shared_blocks(): void
    {
        $regions = $this->app->make(Regions::class);

        $this->assertSame(['request', 'promo'], $regions->fragments());
        $this->assertTrue($regions->isFragment('request'));
        $this->assertFalse($regions->isFragment('header'));
        $this->assertFalse($regions->declared()['footer']['fragment']);

        $rows = $this->actingAs($this->editor(['blocks.regions']), 'cms')->getJson('/api/cms/regions')->assertOk()->json('data');
        $byName = array_column($rows, null, 'name');

        $this->assertFalse($byName['header']['fragment']);
        $this->assertNull($byName['header']['used_on']);
        $this->assertTrue($byName['request']['fragment']);
        $this->assertSame([], $byName['request']['used_on']);
    }

    #[Test]
    public function the_block_that_places_one_is_offered_and_installed_once(): void
    {
        $block = Block::query()->where('slug', 'fragment')->firstOrFail();

        $this->assertSame('Shared block', $block->title);
        $this->assertNotNull($block->publishedVersion);
        $this->assertSame('wx-fragment', $block->publishedVersion?->schema[0]['type'] ?? null);

        // A second install leaves the site's type alone.
        $block->update(['title' => 'Our shared block']);
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['blocks']])->assertSuccessful();

        $this->assertSame('Our shared block', $block->refresh()->title);
    }

    #[Test]
    public function the_field_takes_a_shared_block_and_nothing_else(): void
    {
        $values = $this->app->make(ContentValues::class);

        foreach (['header' => 'a region of the layout', 'sidebar' => 'a name nobody declared'] as $name => $why) {
            try {
                $values->store([$this->node('fragment', ['fragment' => $name], 'f1')]);
                $this->fail("Stored {$why}.");
            } catch (ValidationException $refused) {
                $this->assertStringContainsString("“{$name}” is not a shared block", implode(' ', $refused->validator->errors()->all()));
            }
        }

        $stored = $values->store([$this->node('fragment', ['fragment' => 'request'], 'f1'), $this->node('fragment', ['fragment' => ''], 'f2')]);

        $this->assertSame('request', $stored[0]['values']['fragment']);
        $this->assertNull($stored[1]['values']['fragment']);

        // Empty prints nothing; so does a name that stopped being a shared block after it was written.
        $this->assertSame('', trim($this->render([$this->node('fragment', ['fragment' => null])])));
        $this->region('header', [$this->node('bar', ['text' => 'Header bar'])]);
        $this->assertSame('', trim($this->render([$this->node('fragment', ['fragment' => 'header'])])));
    }

    #[Test]
    public function a_page_prints_the_shared_block_with_its_styles_and_marks_it_for_the_bar(): void
    {
        $this->region('request', [$this->node('bar', ['text' => 'Ask us'], 'r1')]);
        $this->page('about', [$this->node('fragment', ['fragment' => 'request'], 'p1')]);

        $html = (string) $this->get('/about')->assertOk()->getContent();
        $article = substr($html, (int) strpos($html, '<article'));

        $this->assertStringContainsString('<div class="b-bar">Ask us</div>', $article);
        // The region's own bundle, printed by its tag in the page's content, before the block.
        $this->assertMatchesRegularExpression('~(<link rel="stylesheet"|<style>)[^\n]*?<div class="b-bar">Ask us</div>~s', $article);
        $this->assertStringNotContainsString('<!--wx', $html);

        // For whoever edits the regions, the shared block's pair and its blocks' pairs.
        $this->app['auth']->guard('cms')->setUser($this->editor(['blocks.regions']));
        $html = (string) $this->get('/about')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<!--wx-region:request-->.*<!--wx:r1--><div class="b-bar">Ask us</div><!--/wx:r1-->.*<!--/wx-region:request-->~s', $html);
    }

    #[Test]
    public function a_shared_block_inside_itself_is_refused_with_the_path(): void
    {
        $editor = $this->editor(['blocks.regions']);
        $this->region('promo', [$this->node('fragment', ['fragment' => 'request'], 'm1')]);

        // Straight in: the draft is not even kept.
        $refused = $this->actingAs($editor, 'cms')
            ->putJson('/api/cms/regions/request', ['blocks' => [$this->node('fragment', ['fragment' => 'promo'], 'q1')]])
            ->assertUnprocessable();

        $this->assertStringContainsString('request → promo → request', (string) $refused->json('errors.blocks.0'));

        // A draft written while the other did not place it yet is refused at publication.
        $this->region('promo', [$this->node('bar', ['text' => 'Sale'], 'm1')]);
        $this->actingAs($editor, 'cms')
            ->putJson('/api/cms/regions/request', ['blocks' => [$this->node('fragment', ['fragment' => 'promo'], 'q1')]])
            ->assertOk();
        $this->region('promo', [$this->node('fragment', ['fragment' => 'request'], 'm1')]);

        $refused = $this->actingAs($editor, 'cms')->postJson('/api/cms/regions/request/publish')->assertUnprocessable();

        $this->assertStringContainsString('request → promo → request', (string) $refused->json('errors.blocks.0'));
        $this->assertFalse(Region::query()->where('name', 'request')->firstOrFail()->isPublished());

        // Nested in a container counts the same.
        $this->publish('box', '<div class="b-box">@blocks(\'inner\')</div>', [], ['schema' => [['id' => 'inner', 'type' => 'wx-blocks']]]);
        $this->actingAs($editor, 'cms')
            ->putJson('/api/cms/regions/request', ['blocks' => [$this->node('box', ['inner' => [$this->node('fragment', ['fragment' => 'request'], 'q2')]], 'q1')]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.blocks.0', fn (string $line): bool => str_contains($line, 'request → request'));
    }

    #[Test]
    public function a_cycle_published_before_the_check_is_left_empty_where_it_closes(): void
    {
        $log = Log::spy();

        // Written past the check, as a tree published before it existed would be.
        $this->region('request', [$this->node('bar', ['text' => 'Ask us'], 'r1'), $this->node('fragment', ['fragment' => 'promo'], 'r2')]);
        $this->region('promo', [$this->node('bar', ['text' => 'Sale'], 'm1'), $this->node('fragment', ['fragment' => 'request'], 'm2')]);

        $html = $this->tag('name="request"');

        $this->assertSame(1, substr_count($html, 'Ask us'));
        $this->assertSame(1, substr_count($html, 'Sale'));

        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, 'request → promo → request'))->once();
    }

    #[Test]
    public function a_copy_is_the_published_tree_with_new_keys_at_every_level(): void
    {
        $this->publish('box', '<div class="b-box">@blocks(\'inner\')</div>', [], ['schema' => [['id' => 'inner', 'type' => 'wx-blocks']]]);
        $editor = $this->editor(['blocks.view']);

        $this->actingAs($editor, 'cms')->postJson('/api/cms/regions/request/copy')->assertStatus(409)
            ->assertJsonPath('message', 'The shared block is not published yet: there is nothing to copy.');

        $region = $this->region('request', [$this->node('box', ['inner' => [$this->node('bar', ['text' => 'Inside'], 'r2')]], 'r1')]);
        // A draft somebody has not finished: not what the page takes over.
        $region->saveDraft(['blocks' => [$this->node('bar', ['text' => 'Unfinished'], 'd1')]]);

        $blocks = $this->actingAs($editor, 'cms')->postJson('/api/cms/regions/request/copy')->assertOk()->json('data.blocks');

        $this->assertCount(1, $blocks);
        $this->assertSame('box', $blocks[0]['type']);
        $this->assertNotSame('r1', $blocks[0]['key']);
        $this->assertSame('bar', $blocks[0]['values']['inner'][0]['type']);
        $this->assertSame('Inside', $blocks[0]['values']['inner'][0]['values']['text']);
        $this->assertNotSame('r2', $blocks[0]['values']['inner'][0]['key']);
        $this->assertNotSame($blocks[0]['key'], $blocks[0]['values']['inner'][0]['key']);

        // Nothing written.
        $this->assertSame('Unfinished', $region->refresh()->draftValues()['blocks'][0]['values']['text']);

        // Only a shared block is copied; the header is the layout's.
        $this->region('header', [$this->node('bar', ['text' => 'Header'])]);
        $this->actingAs($editor, 'cms')->postJson('/api/cms/regions/header/copy')->assertNotFound();
        $this->actingAs($this->editor(['webx.nothing']), 'cms')->postJson('/api/cms/regions/request/copy')->assertForbidden();
    }

    #[Test]
    public function the_preview_is_a_page_that_holds_it_or_the_stage_with_a_notice(): void
    {
        $this->app['config']->set('webx-blocks.layout', 'region-site::layout');

        $this->region('request', [$this->node('bar', ['text' => 'Ask us'], 'r1')], published: false);
        $this->page('about', [$this->node('fragment', ['fragment' => 'request'], 'p1')]);
        $this->page('contacts', [$this->node('bar', ['text' => 'Write to us'], 'c1')]);

        $html = (string) $this->get(Preview::regionUrl('request', at: '/about'))->assertOk()->getContent();

        $this->assertStringContainsString('<article data-page="About">', $html);
        $this->assertStringContainsString('<!--wx-region:request--><!--wx:r1--><div class="b-bar">Ask us</div>', $html);
        $this->assertStringNotContainsString('does not hold the shared block', $html);

        $html = (string) $this->get(Preview::regionUrl('request', at: '/contacts'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Write to us', $html);
        $this->assertStringContainsString('This page does not hold the shared block', $html);
        $this->assertSame(1, substr_count($html, '<!--wx-region:request-->'));
        $this->assertStringContainsString('Ask us', $html);
    }

    #[Test]
    public function where_it_stands_is_every_record_that_places_it_live_or_in_a_draft(): void
    {
        $this->page('about', [$this->node('fragment', ['fragment' => 'request'], 'p1')]);
        $this->page('contacts', [])->saveDraft(['blocks' => [$this->node('fragment', ['fragment' => 'request'], 'c1')]]);
        $this->region('promo', [$this->node('fragment', ['fragment' => 'request'], 'm1')]);

        $row = $this->actingAs($this->editor(['blocks.regions']), 'cms')->getJson('/api/cms/regions/request')->assertOk()->json('data');
        $usedOn = array_column($row['used_on'], null, 'title');

        $this->assertCount(3, $row['used_on']);
        $this->assertTrue($usedOn['About']['live']);
        $this->assertFalse($usedOn['About']['draft']);
        $this->assertFalse($usedOn['Contacts']['live']);
        $this->assertTrue($usedOn['Contacts']['draft']);
        $this->assertSame('promo', $usedOn['Region “Promo”']['region']);

        $this->agent('regions', [], $this->editor(['blocks.regions']))
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                $regions = array_column($json->etc()->toArray()['regions'], null, 'name');

                self::assertTrue($regions['request']['fragment']);
                self::assertCount(3, $regions['request']['used_on']);
                self::assertContains('region', array_column($regions['request']['used_on'], 'entity'));
                self::assertNull($regions['header']['used_on']);
            });
    }

    #[Test]
    public function the_doctor_asks_whether_anything_places_a_shared_block(): void
    {
        $this->page('about', [$this->node('fragment', ['fragment' => 'request'], 'p1')]);

        $found = array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->state.' '.$diagnosis->detail, $this->app->make(FragmentsCheck::class)->run());

        $this->assertCount(2, $found);
        $this->assertStringStartsWith(Diagnosis::OK.' The shared block [request] stands in the content of 1 record(s)', $found[0]);
        $this->assertStringContainsString('[promo] is placed on no page', $found[1]);
        $this->assertStringNotContainsString(Diagnosis::OK, $found[1]);
    }

    /**
     * A published page at an address, under the layout with the regions.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(string $slug, array $blocks): RegionPage
    {
        $page = RegionPage::query()->create(['slug' => $slug, 'title' => ucfirst($slug), 'blocks' => $blocks]);
        $page->publish();

        return $page->refresh();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        return WebxServer::actingAs($as, 'cms')->tool(new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool)), $arguments);
    }
}
