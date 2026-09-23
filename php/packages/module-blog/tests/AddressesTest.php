<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Article;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Models\Route;

/**
 * The three types in the registry (§4): where the prefix goes, what happens when two of them
 * want the same address, and what a rename leaves behind.
 */
final class AddressesTest extends TestCase
{
    #[Test]
    public function each_type_writes_the_address_its_formatter_says(): void
    {
        $article = $this->article('how-to-choose');
        $rubric = $this->rubric('repairs');
        $tag = $this->tag('belts');

        $this->assertSame('blog/how-to-choose', $article->routeCanonical()?->path);
        $this->assertSame('blog/repairs', $rubric->routeCanonical()?->path);
        $this->assertSame('blog/tag/belts', $tag->routeCanonical()?->path);
    }

    #[Test]
    public function a_renamed_article_leaves_the_old_address_behind_as_a_301(): void
    {
        $article = $this->article('kak-vybrat');

        $article->slug = 'how-to-choose';
        $article->save();

        $this->get('/blog/kak-vybrat')->assertRedirect('/blog/how-to-choose')->assertStatus(301);
        $this->get('/blog/how-to-choose')->assertOk();
    }

    #[Test]
    public function an_article_and_a_rubric_cannot_share_an_address(): void
    {
        // Flat namespace (§4): the rubric "Repairs" and an article slugged `remont` are one
        // address, and the second of them to be saved is refused rather than quietly suffixed.
        $this->rubric('repairs');

        $this->expectException(PathRejected::class);

        $this->article('repairs');
    }

    #[Test]
    public function the_refusal_names_the_slug_field(): void
    {
        $this->rubric('repairs');

        try {
            $this->article('repairs');
            $this->fail('The second address should have been refused.');
        } catch (PathRejected $rejected) {
            $this->assertSame('slug', $rejected->attribute);
            $this->assertArrayHasKey('slug', $rejected->errors());
        }
    }

    #[Test]
    public function an_article_with_no_slug_takes_no_address_at_all(): void
    {
        $article = new Article(['title' => 'Untitled']);
        $article->setTranslation('slug', 'en', '');
        $article->save();

        // Not the feed's own address, which is what an empty slug would format to under the
        // prefix. Nothing is written, so nothing has to be refused (§9).
        $this->assertNull($article->routeCanonical());
        $this->get('/blog')->assertOk();
    }

    #[Test]
    public function an_article_deleted_for_good_takes_its_address_with_it(): void
    {
        $article = $this->article('how-to-choose');

        $article->forceDelete();

        $this->assertSame(0, Route::query()->where('entity_type', 'article')->count());
        $this->get('/blog/how-to-choose')->assertNotFound();
    }

    #[Test]
    public function a_rubric_and_a_tag_live_in_different_branches(): void
    {
        // A rubric and a tag with the same word are a pair that happens constantly, and they
        // must not collide: the tags sit one segment deeper (§4).
        $rubric = $this->rubric('belts');
        $tag = $this->tag('belts');

        $this->assertSame('blog/belts', $rubric->routeCanonical()?->path);
        $this->assertSame('blog/tag/belts', $tag->routeCanonical()?->path);
    }
}
