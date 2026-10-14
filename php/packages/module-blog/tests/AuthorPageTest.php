<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;

/**
 * The page of an author (§19): open twice or not at all, and a byline that is a link only where
 * there is a page to go to.
 */
final class AuthorPageTest extends TestCase
{
    #[Test]
    public function an_open_author_lists_their_articles_and_nobody_elses(): void
    {
        $this->authorsOn();
        $anna = $this->profile('anna', public: true);

        $this->articleBy($anna->user, 'changing-a-belt');
        $this->articleBy($anna->user, 'not-yet', published: false);
        $this->articleBy($anna->user, 'next-week', at: Carbon::now()->addWeek());
        $this->article('somebody-else');

        $response = $this->get('/blog/author/anna');

        $response->assertOk();
        $response->assertSee('Anna Writer');
        $response->assertSee('Changing a belt');
        $response->assertDontSee('Not yet');
        $response->assertDontSee('Next week');
        $response->assertDontSee('Somebody else');
    }

    #[Test]
    public function the_byline_links_to_an_open_author_page(): void
    {
        $this->authorsOn();
        $anna = $this->profile('anna', public: true);
        $this->articleBy($anna->user, 'changing-a-belt');

        $this->get('/blog/changing-a-belt')
            ->assertOk()
            ->assertSee('href="'.url('/blog/author/anna').'" rel="author">Anna Writer</a>', false);
    }

    #[Test]
    public function a_closed_profile_is_a_404_and_its_name_is_text(): void
    {
        $this->authorsOn();
        $anna = $this->profile('anna', public: false);
        $this->articleBy($anna->user, 'changing-a-belt');

        $this->get('/blog/author/anna')->assertNotFound();

        $this->get('/blog/changing-a-belt')
            ->assertOk()
            ->assertSee('<span class="webx-blog-article__author">Anna Writer</span>', false)
            ->assertDontSee('/blog/author/anna');
    }

    #[Test]
    public function a_site_without_author_pages_answers_none_and_names_the_account(): void
    {
        $anna = $this->profile('anna', public: true);
        $this->articleBy($anna->user, 'changing-a-belt');

        $this->get('/blog/author/anna')->assertNotFound();

        // As the blog always has: the account's name, no link — the profile is not read at all.
        $this->get('/blog/changing-a-belt')
            ->assertOk()
            ->assertSee('<span class="webx-blog-article__author">'.$anna->user?->name.'</span>', false)
            ->assertDontSee('Anna Writer');
    }

    #[Test]
    public function an_account_without_a_profile_is_named_as_before(): void
    {
        $this->authorsOn();
        $user = $this->account('Plain Account');
        $this->articleBy($user, 'changing-a-belt');

        $this->get('/blog/changing-a-belt')
            ->assertOk()
            ->assertSee('<span class="webx-blog-article__author">Plain Account</span>', false);
    }

    #[Test]
    public function an_open_author_with_nothing_published_is_a_page_out_of_the_index(): void
    {
        $this->authorsOn();
        $anna = $this->profile('anna', public: true);

        $this->get('/blog/author/anna')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);

        $this->assertFalse($anna->refresh()->isVisible('en'));

        $this->articleBy($anna->user, 'changing-a-belt');

        $this->get('/blog/author/anna')->assertOk()->assertDontSee('noindex', false);
        $this->assertTrue($anna->refresh()->isVisible('en'));
    }

    #[Test]
    public function the_article_names_the_person_and_the_page_names_the_profile(): void
    {
        $this->authorsOn();
        $anna = $this->profile('anna', public: true);
        $article = $this->articleBy($anna->user, 'changing-a-belt');

        $posting = $article->structuredData('en')[0];

        $this->assertSame('Person', $posting['author']['@type']);
        $this->assertSame('Anna Writer', $posting['author']['name']);
        $this->assertSame('Editor', $posting['author']['jobTitle']);
        $this->assertSame(url('/blog/author/anna'), $posting['author']['url']);

        $page = $anna->refresh()->structuredData('en')[0];

        $this->assertSame('ProfilePage', $page['@type']);
        $this->assertSame('Anna Writer', $page['mainEntity']['name']);
    }

    #[Test]
    public function a_closed_author_is_named_in_the_markup_without_an_address(): void
    {
        $this->authorsOn();
        $anna = $this->profile('anna', public: false);
        $article = $this->articleBy($anna->user, 'changing-a-belt');

        $this->assertArrayNotHasKey('url', $article->structuredData('en')[0]['author']);
    }

    #[Test]
    public function the_profile_goes_with_the_account(): void
    {
        $this->authorsOn();
        $anna = $this->profile('anna', public: true);
        $article = $this->articleBy($anna->user, 'changing-a-belt');

        $anna->user?->delete();

        $this->assertNull(Author::query()->find($anna->getKey()));
        $this->assertNull($article->refresh()->author_id);
        $this->get('/blog/author/anna')->assertNotFound();
    }

    private function authorsOn(): void
    {
        config()->set('webx-blog.authors', true);
    }

    private function account(string $name): CmsUser
    {
        static $count = 0;
        $count++;

        return CmsUser::query()->create([
            'name' => $name,
            'email' => "account-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);
    }

    private function profile(string $slug, bool $public): Author
    {
        return Author::query()->create([
            'user_id' => $this->account('Login '.ucfirst($slug))->getKey(),
            'title' => ucfirst($slug).' Writer',
            'slug' => $slug,
            'role' => 'Editor',
            'is_public' => $public,
        ]);
    }

    private function articleBy(?CmsUser $user, string $slug, bool $published = true, ?Carbon $at = null): Article
    {
        $article = new Article(['title' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'author_id' => $user?->getKey()]);
        $article->save();

        if ($published || $at !== null) {
            $article->publish(at: $at);
        }

        return $article->refresh();
    }
}
