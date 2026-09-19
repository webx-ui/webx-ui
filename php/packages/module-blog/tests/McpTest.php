<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Tag;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Seo\Models\SeoRedirect;

/**
 * The blog by its other doors (§13): what an agent can do with it, and what it is refused.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_three_sections_offer_their_tools_the_feed_and_a_prompt(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['articles_list', 'articles_get', 'articles_create', 'articles_update', 'articles_publish', 'articles_unpublish', 'articles_delete'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('articles')),
        );

        $this->assertSame(
            ['rubrics_list'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('rubrics')),
        );

        $this->assertSame(
            ['tags_list', 'tags_merge'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('tags')),
        );

        foreach (['articles:read', 'articles:write', 'rubrics:read', 'tags:read', 'tags:write'] as $scope) {
            $this->assertContains($scope, $registry->scopes());
        }

        // Rubrics are navigation: there is nothing here that makes a ninth section of the site.
        $this->assertNotContains('rubrics:write', $registry->scopes());

        $this->assertContains(
            'blog://feed',
            array_map(static fn ($resource): string => $resource->uri, $registry->resources()),
        );

        $this->assertContains(
            'write_article',
            array_map(static fn ($prompt): string => $prompt->name, $registry->prompts()),
        );
    }

    #[Test]
    public function the_list_answers_the_blog_and_narrows_by_state_rubric_and_tag(): void
    {
        $repairs = $this->rubric('repairs');
        $belts = $this->tag('belts');

        $chosen = $this->article('how-to-choose');
        $chosen->rubrics()->sync([$repairs->getKey() => ['position' => 0]]);
        $chosen->tags()->sync([$belts->getKey()]);

        $this->article('older-piece');
        $waiting = $this->article('coming-soon', published: false, at: Carbon::now()->addWeek());
        $draft = $this->article('half-written', published: false);

        $this->agent('articles_list')
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(4, $content['total']);
                $this->assertSame(['en'], $content['locales']);

                $byId = array_column($content['articles'], null, 'id');
                $row = $byId[array_key_first($byId)];

                $this->assertArrayHasKey('urls', $row);
                $this->assertArrayHasKey('status', $row);
            });

        $this->agent('articles_list', ['status' => Article::STATUS_SCHEDULED])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($waiting): void {
                $content = $json->etc()->toArray();

                $this->assertSame(1, $content['total']);
                $this->assertSame($waiting->getKey(), $content['articles'][0]['id']);
                // Dated ahead is not the same as on the site, and the row says which it is.
                $this->assertSame(Article::STATUS_SCHEDULED, $content['articles'][0]['status']);
            });

        $this->agent('articles_list', ['status' => Article::STATUS_DRAFT])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($draft): void {
                $content = $json->etc()->toArray();

                $this->assertSame(1, $content['total']);
                $this->assertSame($draft->getKey(), $content['articles'][0]['id']);
            });

        // A rubric and a tag are named by their slug, which is what an agent has in its hands.
        foreach ([['rubric' => 'repairs'], ['tag' => 'belts']] as $filter) {
            $this->agent('articles_list', $filter)
                ->assertOk()
                ->assertStructuredContent(function (AssertableJson $json) use ($chosen): void {
                    $content = $json->etc()->toArray();

                    $this->assertSame(1, $content['total']);
                    $this->assertSame($chosen->getKey(), $content['articles'][0]['id']);
                    $this->assertSame(['en' => 'Repairs'], $content['articles'][0]['rubrics'][0]['title']);
                    $this->assertSame(['en' => 'Belts'], $content['articles'][0]['tags'][0]['title']);
                });
        }

        $this->agent('articles_list', ['rubric' => 'nowhere'])
            ->assertHasErrors(['No rubric is slugged [nowhere]']);

        $this->agent('articles_list', ['search' => 'choose'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($chosen): void {
                $content = $json->etc()->toArray();

                $this->assertSame(1, $content['total']);
                $this->assertSame($chosen->getKey(), $content['articles'][0]['id']);
            });
    }

    #[Test]
    public function an_article_is_read_by_its_address_and_by_its_id(): void
    {
        $article = $this->article('how-to-choose');
        $article->saveDraft(['title' => ['en' => 'How to choose a belt']]);

        $byPath = $this->agent('articles_get', ['article' => '/blog/how-to-choose'])->assertOk();
        $byId = $this->agent('articles_get', ['article' => $article->getKey()])->assertOk();

        foreach ([$byPath, $byId] as $response) {
            $response->assertStructuredContent(function (AssertableJson $json) use ($article): void {
                $content = $json->etc()->toArray();

                $this->assertSame($article->getKey(), $content['article']['id']);
                // The title is the draft's — what somebody is working on — while the address is
                // the registry's, which is what the site answers at right now.
                $this->assertSame(['en' => 'How to choose a belt'], $content['article']['title']);
                $this->assertSame('/blog/how-to-choose', $content['article']['urls']['en']['path']);
                $this->assertSame(Article::STATUS_MODIFIED, $content['article']['status']);
                $this->assertArrayHasKey('blocks', $content['values']);
                $this->assertArrayHasKey('seo', $content['values']);
                $this->assertArrayHasKey('rubrics', $content['values']);
                $this->assertNotSame('', $content['revision']);
                $this->assertStringContainsString('preview', (string) $content['preview_url']);
            });
        }

        $this->agent('articles_get', ['article' => '/blog/how-to-choose', 'blocks' => false])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertArrayNotHasKey('blocks', $json->etc()->toArray()['values']);
            });

        $this->agent('articles_get', ['article' => '/blog/nowhere'])
            ->assertHasErrors(['No article answers at [/blog/nowhere]']);

        $this->agent('articles_get', ['article' => 9999])
            ->assertHasErrors(['No article has the id [9999]']);
    }

    #[Test]
    public function an_agent_creates_an_article_as_a_draft_and_a_dry_run_creates_nothing(): void
    {
        $repairs = $this->rubric('repairs');

        $this->agent('articles_create', ['title' => 'How to choose a belt', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(['en' => 'How to choose a belt'], $content['would_create']['title']);
                $this->assertSame(['en' => 'how-to-choose-a-belt'], $content['would_create']['slug']);
                // Where it would answer, prefix and all: the slug alone says nothing about it.
                $this->assertSame('/blog/how-to-choose-a-belt', $content['would_answer_at']['en']);
            });

        $this->assertSame(0, Article::query()->count());

        $this->agent('articles_create', [
            'title' => 'How to choose a belt',
            'values' => ['lead' => ['en' => 'Three things to look at.'], 'rubrics' => [$repairs->getKey()]],
        ], $this->editor())
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($repairs): void {
                $content = $json->etc()->toArray();

                $this->assertSame('/blog/how-to-choose-a-belt', $content['article']['urls']['en']['path']);
                // A draft, never dated: nothing is on the site until a person publishes it (§7).
                $this->assertSame(Article::STATUS_DRAFT, $content['article']['status']);
                $this->assertNull($content['article']['published_at']);
                $this->assertSame(['en' => 'Three things to look at.'], $content['values']['lead']);
                // The rubrics are a pivot and are never drafted: they are on the article at once.
                $this->assertSame([$repairs->getKey()], $content['values']['rubrics']);
                $this->assertSame('Editor', $content['article']['author']['name']);
            });

        // An address is chosen deliberately; one already taken is refused rather than suffixed.
        $this->agent('articles_create', ['title' => 'Another one', 'slug' => 'how-to-choose-a-belt'])
            ->assertHasErrors(['Not accepted']);

        // And the flat namespace is what makes that true of a rubric's address too (§4).
        $this->agent('articles_create', ['title' => 'Repairs again', 'slug' => 'repairs'])
            ->assertHasErrors(['Not accepted']);
    }

    #[Test]
    public function an_update_writes_the_draft_and_an_old_revision_is_refused(): void
    {
        $article = $this->article('how-to-choose');

        $read = $this->agent('articles_get', ['article' => '/blog/how-to-choose'])->assertOk();
        $revision = $this->content($read)['revision'];

        $this->agent('articles_update', [
            'article' => '/blog/how-to-choose',
            'values' => ['title' => ['en' => 'How to choose a belt']],
            'dry_run' => true,
        ])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame('draft', $content['would_write']);
                $this->assertSame(['title'], $content['fields']);
            });

        $this->assertFalse($article->refresh()->hasDraft());

        $this->agent('articles_update', [
            'article' => '/blog/how-to-choose',
            'values' => ['title' => ['en' => 'How to choose a belt']],
            'revision' => $revision,
        ], $this->editor())
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(['en' => 'How to choose a belt'], $content['values']['title']);
                // The draft alone: the site goes on showing what it showed.
                $this->assertSame(Article::STATUS_MODIFIED, $content['article']['status']);
            });

        $this->assertSame('How to choose', $article->refresh()->getTranslation('title', 'en'));

        // The revision from before that write is now an article somebody else has edited.
        $this->agent('articles_update', [
            'article' => '/blog/how-to-choose',
            'values' => ['title' => ['en' => 'Third']],
            'revision' => $revision,
        ])
            ->assertHasErrors(['changed since you read it', 'articles_get']);

        $this->assertSame(['en' => 'How to choose a belt'], $article->refresh()->draftValues()['title']);
    }

    #[Test]
    public function the_body_is_not_written_through_the_article_tools(): void
    {
        $this->article('how-to-choose');

        // §13: there is one way to change the blocks of an article, and it is not this one. Said
        // out loud, because an agent that got a cheerful answer would think it had saved a tree.
        $this->agent('articles_update', [
            'article' => '/blog/how-to-choose',
            'values' => ['blocks' => [['type' => 'text', 'values' => []]]],
        ])
            ->assertHasErrors(['blocks_edit_content']);

        $this->agent('articles_create', ['title' => 'News', 'values' => ['blocks' => []]])
            ->assertHasErrors(['blocks_edit_content']);

        $this->assertNull(Article::query()->whereTranslation('title', 'News', 'en')->first());
    }

    #[Test]
    public function publishing_takes_a_day_and_a_day_ahead_keeps_the_article_off_the_site(): void
    {
        Carbon::setTestNow('2026-09-19T10:00:00+00:00');

        $article = $this->article('how-to-choose', published: false);
        $article->saveDraft(['title' => ['en' => 'How to choose a belt'], 'slug' => ['en' => 'how-to-choose']]);

        $this->agent('articles_publish', ['article' => $article->getKey(), 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(Article::STATUS_DRAFT, $content['status']);
                $this->assertTrue($content['has_waiting_edits']);
            });

        $this->assertFalse($article->refresh()->isPublished());

        // A day ahead: the values become the article, and the site still answers 404 (§7).
        $this->agent('articles_publish', [
            'article' => $article->getKey(),
            'at' => '2026-09-26T09:00:00+03:00',
        ], $this->editor())
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(Article::STATUS_SCHEDULED, $content['article']['status']);
                // Said rather than left to be read off the status: to the frame underneath this
                // article is published, and it is not on the site.
                $this->assertFalse($content['on_the_site']);
            });

        $article->refresh();

        $this->assertSame('How to choose a belt', $article->getTranslation('title', 'en'));
        $this->assertSame('mcp', $article->publishedVersions()->latest('id')->first()?->source);
        // The offset is read and kept as a moment, not written down as a wall clock: 09:00+03:00
        // is 06:00 in the application's zone, and a save that lost that would move the article.
        $this->assertSame('2026-09-26T06:00:00+00:00', $article->published_at?->toAtomString());

        $this->get('/blog/how-to-choose')->assertNotFound();

        Carbon::setTestNow('2026-09-27T10:00:00+00:00');

        $this->assertTrue($article->refresh()->isPublished());
        $this->get('/blog/how-to-choose')->assertOk();

        $this->agent('articles_unpublish', ['article' => '/blog/how-to-choose'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(Article::STATUS_UNPUBLISHED, $json->etc()->toArray()['article']['status']);
            });

        // The address stays in the registry: an address released here would be taken by the next
        // article called the same thing, and putting this one back would be a move.
        $this->assertSame('blog/how-to-choose', $article->refresh()->routeCanonical('en')?->path);
    }

    #[Test]
    public function deleting_puts_an_article_in_the_bin_and_releases_its_address(): void
    {
        $article = $this->article('how-to-choose');

        $this->agent('articles_delete', ['article' => '/blog/how-to-choose', 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertStringContainsString('/blog/how-to-choose', $json->etc()->toArray()['would_trash']);
            });

        $this->assertFalse($article->refresh()->trashed());

        $this->agent('articles_delete', ['article' => '/blog/how-to-choose'])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($article): void {
                $this->assertSame($article->getKey(), $json->etc()->toArray()['id']);
            });

        $this->assertTrue($article->refresh()->trashed());

        // The address goes with it, which is exactly what frees it for somebody else — so the
        // article has no address left to be named by.
        $this->agent('articles_get', ['article' => '/blog/how-to-choose'])
            ->assertHasErrors(['No article answers at']);

        $this->agent('articles_list', ['trashed' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($article): void {
                $content = $json->etc()->toArray();

                $this->assertSame(1, $content['total']);
                $this->assertSame($article->getKey(), $content['articles'][0]['id']);
                $this->assertNotNull($content['articles'][0]['deleted_at']);
            });
    }

    #[Test]
    public function the_rubrics_are_read_and_never_written(): void
    {
        $repairs = $this->rubric('repairs');
        $this->rubric('hidden-one', visible: false);

        $article = $this->article('how-to-choose');
        $article->rubrics()->sync([$repairs->getKey() => ['position' => 0]]);

        $this->agent('rubrics_list')
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($repairs): void {
                $content = $json->etc()->toArray();

                $this->assertSame(2, $content['count']);

                $byId = array_column($content['rubrics'], null, 'id');

                $this->assertSame('/blog/repairs', $byId[$repairs->getKey()]['paths']['en']);
                // The number that makes a rubric refuse to be deleted (§6).
                $this->assertSame(1, $byId[$repairs->getKey()]['articles_count']);
                $this->assertTrue($byId[$repairs->getKey()]['is_visible']);
            });

        $this->agent('rubrics_list', ['visible' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(1, $json->etc()->toArray()['count']);
            });
    }

    #[Test]
    public function the_tags_are_listed_by_use_and_merged_into_one(): void
    {
        $belts = $this->tag('belts');
        $belt = $this->tag('belt');
        $drive = $this->tag('drive-belts');

        $first = $this->article('first');
        $second = $this->article('second');

        $first->tags()->sync([$belts->getKey(), $belt->getKey()]);
        $second->tags()->sync([$drive->getKey()]);

        $this->agent('tags_list')
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json) use ($belts): void {
                $content = $json->etc()->toArray();

                $this->assertSame(3, $content['total']);
                $this->assertSame($belts->getKey(), $content['tags'][0]['id']);
                // Out of the index by default: a hundred thin listings is how a site teaches a
                // search engine to ignore it (§2.9).
                $this->assertSame(Tag::INDEXING_NOINDEX, $content['tags'][0]['indexing']);
                $this->assertSame(3, $content['totals']['noindex']);
            });

        $this->agent('tags_merge', ['keep' => 'belts', 'merged' => ['belt', $drive->getKey()], 'dry_run' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(['en' => 'Belts'], $content['would_keep']['title']);
                $this->assertCount(2, $content['would_merge']);
                // Two articles and not three: the first carries two of the three tags, and a
                // number that counted it twice is the number the answer is compared with.
                $this->assertSame(2, $content['articles_after']);
            });

        $this->assertSame(3, Tag::query()->count());

        $this->agent('tags_merge', ['keep' => $belts->getKey(), 'merged' => ['belt', 'drive-belts'], 'redirect' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $content = $json->etc()->toArray();

                $this->assertSame(2, $content['merged']);
                $this->assertSame(2, $content['articles_count']);
                $this->assertTrue($content['redirects_written']);
            });

        $this->assertSame(1, Tag::query()->count());
        $this->assertSame(2, $belts->refresh()->articles()->count());

        // The aliases of the registry die with the tag, so an address that survives a merge
        // survives as a redirect (§6).
        $this->assertTrue(SeoRedirect::query()->where('pattern', '/blog/tag/belt')->where('status', 301)->exists());

        $this->agent('tags_merge', ['keep' => 'belts', 'merged' => ['belts']])
            ->assertHasErrors(['nothing but the tag being kept']);

        $this->agent('tags_merge', ['keep' => 'belts', 'merged' => ['nowhere']])
            ->assertHasErrors(['No tag is slugged [nowhere]']);
    }

    #[Test]
    public function the_feed_is_what_the_blog_publishes(): void
    {
        $repairs = $this->rubric('repairs');
        $belts = $this->tag('belts');

        $live = $this->article('how-to-choose');
        $live->rubrics()->sync([$repairs->getKey() => ['position' => 0]]);
        $live->tags()->sync([$belts->getKey()]);

        $this->article('half-written', published: false);
        $this->article('coming-soon', published: false, at: Carbon::now()->addWeek());

        $resource = $this->resource('blog://feed');
        $feed = ($resource->handler)();

        $this->assertSame(['en'], $feed['locales']);
        $this->assertSame('blog', $feed['prefix']);

        // What a reader sees, which is neither the draft nor the one waiting for next week.
        $this->assertSame(1, $feed['count']);
        $this->assertSame('How to choose', $feed['articles'][0]['title']);
        $this->assertStringEndsWith('/blog/how-to-choose', (string) $feed['articles'][0]['url']);
        $this->assertSame(['Repairs'], $feed['articles'][0]['rubrics']);
        $this->assertSame(['Belts'], $feed['articles'][0]['tags']);
    }

    #[Test]
    public function the_prompt_puts_the_loop_in_front_of_the_agent(): void
    {
        $prompt = $this->prompt('write_article');
        $text = ($prompt->handler)(['brief' => 'How to choose a drive belt', 'rubric' => 'repairs']);

        $this->assertStringContainsString('How to choose a drive belt', $text);
        $this->assertStringContainsString('repairs', $text);
        $this->assertStringContainsString('blog://feed', $text);
        // The two steps a first attempt gets wrong: the body is another module's tool, and the
        // date is the publication.
        $this->assertStringContainsString('blocks_edit_content', $text);
        $this->assertStringContainsString('do not set `published_at`', $text);

        $this->assertStringContainsString('ask what the article is for', ($prompt->handler)([]));
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No module offers the resource [{$uri}].");
    }

    private function prompt(string $name): Prompt
    {
        foreach ($this->app->make(ToolRegistry::class)->prompts() as $prompt) {
            if ($prompt->name === $name) {
                return $prompt;
            }
        }

        $this->fail("No module offers the prompt [{$name}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);
        $bound = new RegistryTool($registry->tool($tool));

        return $as instanceof CmsUser
            ? WebxServer::actingAs($as, 'cms')->tool($bound, $arguments)
            : WebxServer::tool($bound, $arguments);
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
