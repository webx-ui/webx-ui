<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * The section «Authors» (§19.5): profiles written in the panel, on a site that allows them.
 */
final class AuthorPanelTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // At boot, where the panel's modules are registered — set later, the section is not there.
        $app['config']->set('webx-blog.authors', true);
    }

    #[Test]
    public function the_section_is_in_the_manifest_of_a_site_with_author_pages(): void
    {
        $modules = $this->actingAs($this->manager(), 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');

        $this->assertContains('authors', array_column((array) $modules, 'id'));
    }

    #[Test]
    public function a_profile_is_made_for_an_account_that_has_none(): void
    {
        $writer = $this->account('Login Name');
        $article = new Article(['title' => 'Belts', 'slug' => 'belts', 'author_id' => $writer->getKey()]);
        $article->save();

        $accounts = $this->actingAs($this->manager(), 'cms')->getJson('/api/cms/blog/authors/accounts')->assertOk();

        // The one who wrote something first: they are why the section was opened.
        $this->assertSame('Login Name', $accounts->json('data.0.name'));
        $this->assertSame(1, $accounts->json('data.0.articles_count'));

        $response = $this->actingAs($this->manager(), 'cms')->postJson('/api/cms/blog/authors', [
            'user_id' => $writer->getKey(),
            'values' => ['title' => ['en' => 'Anna Writer'], 'slug' => ['en' => 'anna'], 'role' => ['en' => 'Editor'], 'is_public' => true],
        ]);

        $response->assertCreated();
        $this->assertSame('Anna Writer', $response->json('data.author.name'));
        $this->assertTrue($response->json('data.author.open'));
        $this->assertSame(url('/blog/author/anna'), $response->json('data.author.url'));
        $this->assertSame(1, $response->json('data.author.articles_count'));

        // Once: the same account again is refused under the field that chose it.
        $this->actingAs($this->manager(), 'cms')->postJson('/api/cms/blog/authors', [
            'user_id' => $writer->getKey(),
            'values' => ['title' => ['en' => 'Again']],
        ])->assertStatus(422)->assertJsonValidationErrors(['user_id']);

        $this->assertNotContains($writer->getKey(), array_column((array) $this->actingAs($this->manager(), 'cms')->getJson('/api/cms/blog/authors/accounts')->json('data'), 'id'));
    }

    #[Test]
    public function a_profile_needs_an_account_and_a_name(): void
    {
        $this->actingAs($this->manager(), 'cms')->postJson('/api/cms/blog/authors', [
            'values' => ['title' => ['en' => 'Nobody']],
        ])->assertStatus(422)->assertJsonValidationErrors(['user_id']);

        $this->actingAs($this->manager(), 'cms')->postJson('/api/cms/blog/authors', [
            'user_id' => $this->account('Someone')->getKey(),
            'values' => ['role' => ['en' => 'Editor']],
        ])->assertStatus(422)->assertJsonValidationErrors(['title.en']);
    }

    #[Test]
    public function the_photo_is_a_file_of_the_library_and_the_seo_card_is_kept(): void
    {
        $author = $this->profile();
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
        $file = MediaFile::query()->forceCreate([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'people/anna.jpg',
            'hash' => str_repeat('b', 32),
            'name' => 'anna.jpg',
            'name_lower' => 'anna.jpg',
            'file_name' => 'anna.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
        ]);

        $response = $this->actingAs($this->manager(), 'cms')->putJson('/api/cms/blog/authors/'.$author->getKey(), [
            'values' => [
                'photo' => ['path' => 'people/anna.jpg'],
                'seo' => ['title' => ['en' => 'Anna, the editor']],
            ],
        ]);

        $response->assertOk();
        $this->assertSame((int) $file->getKey(), $author->refresh()->photo_id);
        $this->assertSame(['path' => 'people/anna.jpg'], $response->json('data.values.photo'));
        $this->assertSame('Anna, the editor', $response->json('data.values.seo.title.en'));
        // A save of one field leaves the others as they were.
        $this->assertSame('Anna Writer', $author->name('en'));
    }

    #[Test]
    public function the_networks_drop_empty_rows_and_refuse_an_address_that_is_not_a_page(): void
    {
        $author = $this->profile();

        $this->actingAs($this->manager(), 'cms')->putJson('/api/cms/blog/authors/'.$author->getKey(), [
            'values' => ['socials' => [['network' => 'linkedin', 'url' => 'linkedin.com/in/anna']]],
        ])->assertStatus(422)->assertJsonValidationErrors(['socials.0.url']);

        $response = $this->actingAs($this->manager(), 'cms')->putJson('/api/cms/blog/authors/'.$author->getKey(), [
            'values' => ['socials' => [['network' => '', 'url' => ''], ['network' => 'linkedin', 'url' => 'https://www.linkedin.com/in/anna']]],
        ]);

        $response->assertOk();
        $this->assertSame([['network' => 'linkedin', 'url' => 'https://www.linkedin.com/in/anna']], $response->json('data.values.socials'));
    }

    #[Test]
    public function deleting_a_profile_leaves_the_articles_with_the_account(): void
    {
        $author = $this->profile();
        $article = new Article(['title' => 'Belts', 'slug' => 'belts', 'author_id' => $author->user_id]);
        $article->save();

        $this->actingAs($this->manager(), 'cms')->deleteJson('/api/cms/blog/authors/'.$author->getKey())->assertNoContent();

        $this->assertNull(Author::query()->find($author->getKey()));
        $this->assertSame($author->user_id, $article->refresh()->author_id);
    }

    #[Test]
    public function it_takes_the_permission_of_its_own(): void
    {
        $this->actingAs($this->editor(['blog.articles.manage', 'blog.taxonomy.manage']), 'cms')
            ->getJson('/api/cms/blog/authors')
            ->assertForbidden();
    }

    #[Test]
    public function a_site_that_turned_author_pages_off_answers_404(): void
    {
        config()->set('webx-blog.authors', false);

        $this->actingAs($this->manager(), 'cms')->getJson('/api/cms/blog/authors')->assertNotFound();
    }

    #[Test]
    public function the_article_editor_knows_whose_account_has_a_profile(): void
    {
        $author = $this->profile();
        $article = new Article(['title' => 'Belts', 'slug' => 'belts', 'author_id' => $author->user_id]);
        $article->save();

        $authors = $this->actingAs($this->editor(), 'cms')->getJson($this->api($article->getKey()))->assertOk()->json('data.options.authors');
        $row = array_values(array_filter((array) $authors, static fn (mixed $one): bool => is_array($one) && $one['id'] === $author->user_id))[0] ?? null;

        $this->assertSame($author->getKey(), $row['profile'] ?? null);
    }

    #[Test]
    public function an_agent_reads_the_profiles_and_changes_their_words_but_does_not_make_one(): void
    {
        $author = $this->profile();
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['authors_list', 'authors_update'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('authors')),
        );

        $list = $this->content($this->agent('authors_list'));
        $this->assertSame('Anna Writer', $list['authors'][0]['name']);

        // A dry run is the save, rolled back.
        $dry = $this->content($this->agent('authors_update', ['author' => 'anna', 'role' => 'Editor', 'dry_run' => true]));
        $this->assertTrue($dry['dry_run']);
        $this->assertNull($author->refresh()->getTranslation('role', 'en', false));

        $this->agent('authors_update', ['author' => $author->getKey(), 'role' => 'Editor'])->assertOk();
        $this->assertSame('Editor', $author->refresh()->getTranslation('role', 'en'));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->manager(), 'cms')->tool($bound, $arguments);
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

        return (array) $decoded;
    }

    private function manager(): CmsUser
    {
        return $this->editor(['blog.articles.view', 'blog.articles.manage', 'blog.authors.manage']);
    }

    private function account(string $name): CmsUser
    {
        static $count = 0;
        $count++;

        return CmsUser::query()->create([
            'name' => $name,
            'email' => "author-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);
    }

    private function profile(): Author
    {
        return Author::query()->create([
            'user_id' => $this->account('Login Anna')->getKey(),
            'title' => 'Anna Writer',
            'slug' => 'anna',
            'is_public' => true,
        ]);
    }
}
