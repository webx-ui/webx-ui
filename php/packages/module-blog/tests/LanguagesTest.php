<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Article;

/**
 * A blog published in two languages (§9).
 *
 * Its own class for the same reason {@see NoPrefixTest} is: the feed's localised route is
 * registered while the providers boot, and only when the site puts the language in the path.
 */
final class LanguagesTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'uk'],
        ]);
        $app['config']->set('webx-localization.strategy', 'prefix');
    }

    #[Test]
    public function an_article_has_an_address_only_where_it_names_a_slug(): void
    {
        $article = new Article(['title' => 'How to choose']);
        $article->setTranslation('slug', 'en', 'how-to-choose');
        $article->save();
        $article->publish();

        $this->assertSame('blog/how-to-choose', $article->routeCanonical('en')?->path);

        // Not the English slug standing in front of Ukrainian content that is not there: an
        // article without a Ukrainian slug simply has no Ukrainian address.
        $this->assertNull($article->routeCanonical('uk'));

        $article->setTranslation('slug', 'uk', 'yak-obraty');
        $article->save();

        $this->assertSame('blog/yak-obraty', $article->fresh()?->routeCanonical('uk')?->path);
        $this->get('/uk/blog/yak-obraty')->assertOk();
    }

    #[Test]
    public function clearing_a_slug_takes_that_language_off_the_site(): void
    {
        $article = new Article(['title' => 'How to choose']);
        $article->setTranslations('slug', ['en' => 'how-to-choose', 'uk' => 'yak-obraty']);
        $article->save();
        $article->publish();

        $this->get('/uk/blog/yak-obraty')->assertOk();

        $article->setTranslation('slug', 'uk', '');
        $article->save();

        $this->get('/uk/blog/yak-obraty')->assertNotFound();
        $this->get('/blog/how-to-choose')->assertOk();
    }

    #[Test]
    public function the_feed_answers_under_a_language_prefix(): void
    {
        $article = new Article(['title' => 'How to choose']);
        $article->setTranslations('slug', ['en' => 'how-to-choose', 'uk' => 'yak-obraty']);
        $article->setTranslation('title', 'uk', 'Як обрати');
        $article->save();
        $article->publish();

        $this->get('/uk/blog')->assertOk()->assertSee('Як обрати');
    }

    #[Test]
    public function a_first_segment_that_is_not_a_language_is_a_404(): void
    {
        // The prefixed feed route matches `anything/blog`; only a language may stand there, or
        // the feed answers at an address nobody chose (§8.2 of the routing spec).
        $this->get('/nonsense/blog')->assertNotFound();
    }

    #[Test]
    public function the_default_language_has_one_spelling_and_redirects_to_it(): void
    {
        $this->article('how-to-choose');

        // `prefix_default` is off, so `/en/blog` is the same page said a second way.
        $this->get('/en/blog')->assertRedirect('/blog')->assertStatus(301);
        $this->get('/en/blog?page=2')->assertRedirect('/blog?page=2');
    }
}
