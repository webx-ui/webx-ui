<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Routing\Models\Route;

/**
 * The press by its other doors (§4.11): the same list, the same form, the same order code — and
 * the articles one at a time, each a save of its outlet.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function it_offers_ten_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            [
                'press_list', 'press_get', 'press_create', 'press_update', 'press_delete', 'press_reorder',
                'press_articles_add', 'press_articles_update', 'press_articles_delete', 'press_articles_move',
            ],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('press')),
        );

        $this->assertSame(['press.view', 'press.manage'], $registry->tool('press_list')->permissions());
        $this->assertSame(['press.manage'], $registry->tool('press_articles_add')->permissions());
        $this->assertContains('press://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));

        // The kinds the site has, named where an agent picks one.
        $kind = $registry->tool('press_articles_add')->tool->inputSchema['properties']['kind']['description'] ?? '';
        $this->assertStringContainsString('mention, interview, expert_comment, authored', $kind);
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['press.view']);

        $this->agent('press_list', [], $reader)->assertOk();
        $this->agent('press_create', ['title' => 'Nobody'], $reader)->assertHasErrors(['[press.manage]']);
    }

    #[Test]
    public function create_writes_the_outlet_and_its_articles_through_the_form(): void
    {
        $this->file('media/ab/cd/logo.svg', 'image/svg+xml');
        $this->file();

        $created = $this->content($this->agent('press_create', [
            'title' => 'Tatler Asia',
            'summary' => ['en' => 'A society magazine.', 'ru' => 'Светский журнал.'],
            'website_url' => 'https://tatler.example',
            'logo' => 'media/ab/cd/logo.svg',
            'published' => true,
            'articles' => [
                ['title' => 'Interview', 'kind' => 'interview', 'url' => 'https://tatler.example/interview', 'published_on' => '2025-03-14'],
                ['title' => ['en' => 'Scan', 'ru' => 'Скан'], 'file' => 'media/ab/cd/scan.pdf', 'date_precision' => 'month', 'published_on' => '2024-08-01'],
            ],
        ]));

        // A plain string is the default language, not the language of the request.
        $this->assertSame(['en' => 'Tatler Asia'], $created['values']['title']);
        $this->assertSame('media/ab/cd/logo.svg', $created['outlet']['logo']);
        $this->assertSame(['en', 'ru'], $created['outlet']['visible_in']);
        $this->assertSame(['Interview', 'Scan'], array_map(static fn (array $row): string => $row['title']['en'], $created['articles']));
        $this->assertSame('August 2024', $created['articles'][1]['when']);
        $this->assertSame('media/ab/cd/scan.pdf', $created['articles'][1]['file']);
        $this->assertSame(['en'], $created['articles'][0]['visible_in']);

        // The address made from the name, as the form makes it.
        $this->assertTrue(Route::query()->where('path', 'press/tatler-asia')->exists());
    }

    #[Test]
    public function a_refused_create_leaves_nothing_behind(): void
    {
        Screens::extend('press.outlet-form', [['op' => 'add', 'target' => 'project-fields', 'node' => [
            'id' => 'weight', 'type' => 'wx-input-number', 'name' => 'weight', 'label' => 'Weight', 'props' => ['min' => 10],
        ]]]);
        $this->file('media/ab/cd/photo.jpg', 'image/jpeg');

        $this->agent('press_create', ['title' => 'Half', 'values' => ['weight' => 5], 'articles' => [$this->row('Fine')]])->assertHasErrors(['weight']);
        $this->agent('press_create', ['title' => 'Nowhere', 'articles' => [['title' => 'No link']]])->assertHasErrors(['articles.0.url']);
        $this->agent('press_create', ['title' => 'Script', 'articles' => [$this->row('X', ['url' => 'javascript:alert(1)'])]])->assertHasErrors(['articles.0.url']);
        $this->agent('press_create', ['title' => 'Kind', 'articles' => [$this->row('X', ['kind' => 'gossip'])]])->assertHasErrors(['articles.0.kind']);
        $this->agent('press_create', ['title' => 'Picture', 'articles' => [$this->row('X', ['file' => 'media/ab/cd/photo.jpg'])]])->assertHasErrors(['articles.0.file']);
        $this->agent('press_create', ['title' => 'Typo', 'logo' => 'media/no/such.svg'])->assertHasErrors(['no file']);

        $this->assertSame(0, Outlet::withTrashed()->count());
        $this->assertSame(0, Article::query()->count());
    }

    #[Test]
    public function an_outlet_is_named_by_id_or_by_its_name_in_any_language(): void
    {
        $outlet = $this->outlet('Business Herald', [$this->row('One')], values: ['title' => ['en' => 'Business Herald', 'ru' => 'Деловой вестник']]);

        $this->assertSame($outlet->id, $this->content($this->agent('press_get', ['outlet' => 'деловой вестник']))['outlet']['id']);
        $this->assertSame($outlet->id, $this->content($this->agent('press_get', ['outlet' => "#{$outlet->id}"]))['outlet']['id']);
        $this->agent('press_get', ['outlet' => 'Herald'])->assertHasErrors(['No outlet is called']);

        $this->outlet('Business Herald');
        $this->agent('press_get', ['outlet' => 'Business Herald'])->assertHasErrors(['More than one']);
    }

    #[Test]
    public function update_keeps_the_languages_it_was_not_given_and_leaves_the_articles_alone(): void
    {
        $outlet = $this->outlet('Herald', [$this->row('One')], values: ['summary' => ['en' => 'Paper.', 'ru' => 'Газета.']]);

        $this->agent('press_update', ['outlet' => $outlet->id, 'values' => ['summary' => 'The paper.', 'featured' => true]])->assertOk();

        $outlet->refresh();
        $this->assertSame(['en' => 'The paper.', 'ru' => 'Газета.'], $outlet->getTranslations('summary'));
        $this->assertTrue($outlet->featured);
        $this->assertSame(['One'], $this->titles($outlet));

        $this->agent('press_update', ['outlet' => $outlet->id, 'values' => ['articles' => []]])->assertHasErrors(['one at a time']);
        $this->assertSame(1, $outlet->articles()->count());
    }

    #[Test]
    public function an_article_is_added_where_it_is_asked_and_the_others_keep_their_ids(): void
    {
        $outlet = $this->outlet('Herald', [$this->row('One'), $this->row('Two')]);
        $ids = $outlet->articles()->pluck('id')->all();

        $added = $this->content($this->agent('press_articles_add', [
            'outlet' => 'Herald',
            'title' => ['ru' => 'Между'],
            'url' => 'https://news.example/between',
            'kind' => 'mention',
            'position' => 2,
        ]));

        $this->assertSame(['ru' => 'Между'], $added['article']['title']);
        $this->assertSame(['ru'], $added['article']['visible_in']);
        $this->assertSame([$ids[0], $added['article']['id'], $ids[1]], $outlet->articles()->pluck('id')->all());
        // A Russian article gives the outlet a Russian address (the slug from another language).
        $this->assertSame(['en', 'ru'], $added['outlet']['visible_in']);

        $this->agent('press_articles_add', ['outlet' => $outlet->id, 'title' => 'Bad', 'url' => 'ftp://x'])
            ->assertHasErrors(['the new article url']);
        $this->assertSame(3, $outlet->articles()->count());
    }

    #[Test]
    public function an_article_is_updated_by_language_and_a_refusal_names_it(): void
    {
        $outlet = $this->outlet('Herald', [$this->row('One', ['title' => ['en' => 'One', 'ru' => 'Один']]), $this->row('Two')]);
        [$one, $two] = $outlet->articles()->get()->all();

        $this->agent('press_articles_update', ['article' => $one->id, 'values' => ['title' => 'First', 'is_hidden' => true]])->assertOk();

        $one->refresh();
        $this->assertSame(['en' => 'First', 'ru' => 'Один'], $one->getTranslations('title'));
        $this->assertTrue($one->is_hidden);

        $this->agent('press_articles_update', ['article' => $one->id, 'values' => ['title' => ['ru' => '']]])->assertOk();
        $this->assertSame(['en' => 'First'], $one->refresh()->getTranslations('title'));

        $this->agent('press_articles_update', ['article' => $two->id, 'values' => ['kind' => 'gossip']])
            ->assertHasErrors(["article #{$two->id} kind"]);
        $this->assertNull($two->refresh()->kind);
    }

    #[Test]
    public function an_article_is_deleted_and_moved_inside_its_outlet_and_into_another(): void
    {
        $herald = $this->outlet('Herald', [$this->row('One'), $this->row('Two'), $this->row('Three')]);
        $portal = $this->outlet('Portal', [$this->row('Four')]);
        [$one, $two, $three] = $herald->articles()->get()->all();

        $this->agent('press_articles_move', ['article' => $three->id, 'position' => 1])->assertOk();
        $this->assertSame(['Three', 'One', 'Two'], $this->titles($herald));

        $this->agent('press_articles_move', ['article' => $one->id, 'outlet' => 'Portal', 'position' => 1])->assertOk();
        $this->assertSame(['Three', 'Two'], $this->titles($herald));
        $this->assertSame(['One', 'Four'], $this->titles($portal));
        // The same article, not a copy of it.
        $this->assertSame($portal->id, $one->refresh()->outlet_id);

        $this->agent('press_articles_delete', ['article' => $two->id, 'dry_run' => true])->assertOk();
        $this->assertNotNull(Article::query()->find($two->id));

        $this->agent('press_articles_delete', ['article' => $two->id])->assertOk();
        $this->assertSame(['Three'], $this->titles($herald));
    }

    #[Test]
    public function delete_and_reorder_are_the_panels(): void
    {
        $first = $this->outlet('First', [$this->row('One')]);
        $second = $this->outlet('Second', [$this->row('Two')]);

        $listed = $this->content($this->agent('press_reorder', ['outlets' => ['Second', $first->id]]));
        $this->assertSame([$second->id, $first->id], array_column($listed['outlets'], 'id'));

        $this->agent('press_delete', ['outlet' => $first->id])->assertOk();
        $this->assertTrue($first->refresh()->trashed());
        $this->assertSame([$first->id], array_column($this->content($this->agent('press_list', ['trashed' => true]))['outlets'], 'id'));
        $this->agent('press_articles_add', ['outlet' => $first->id, 'title' => 'Late', 'url' => 'https://x.example'])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_catalogue_says_where_each_article_is_seen_and_where_it_leads(): void
    {
        $this->file();
        $herald = $this->outlet('Herald', [
            $this->row('Seen', ['title' => ['en' => 'Seen', 'ru' => 'Виден'], 'published_on' => '2023-05-01', 'date_precision' => 'year']),
            $this->row('Scan', ['url' => null, 'file' => ['path' => 'media/ab/cd/scan.pdf']]),
            $this->row('Hidden', ['is_hidden' => true]),
        ]);
        $draft = $this->outlet('Draft', [$this->row('Waiting')], published: false);

        $catalog = ($this->resource('press://catalog')->handler)();

        $this->assertSame([$herald->id, $draft->id], array_column($catalog['outlets'], 'id'));
        $this->assertSame(['en', 'ru'], $catalog['outlets'][0]['visible_in']);
        $this->assertSame([], $catalog['outlets'][1]['visible_in']);

        [$seen, $scan, $hidden] = $catalog['outlets'][0]['articles'];
        $this->assertSame('2023', $seen['when']);
        $this->assertSame(['en', 'ru'], $seen['written_in']);
        $this->assertStringContainsString('scan.pdf', (string) $scan['target']);
        $this->assertSame([], $hidden['visible_in']);
        $this->assertSame(['en'], $hidden['written_in']);
        $this->assertSame([], $catalog['outlets'][1]['articles'][0]['visible_in']);
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
