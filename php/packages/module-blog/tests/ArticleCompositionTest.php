<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\Inspector;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * The page of an article as the composition blog.article (COMPOSITIONS §10, stage 4): a theme
 * moves, removes, replaces and adds its nodes by id in compositions/blog.article/default.json — the
 * four things a site used to copy article.blade.php for.
 */
final class ArticleCompositionTest extends TestCase
{
    private ?string $theme = null;

    #[Test]
    public function the_module_gives_the_tree_and_nothing_in_it_is_a_finding(): void
    {
        $tree = $this->app->make(Inspector::class)->tree('blog.article');

        $this->assertSame(
            ['root', 'article', 'article.header', 'article.breadcrumbs', 'article.title', 'article.meta', 'article.cover', 'article.content', 'article.tags', 'article.related'],
            array_map(static fn ($node): string => $node->id, iterator_to_array($tree->root->walk(), false)),
        );

        $this->get($this->fullArticle())->assertOk();
        $this->assertSame([], $this->app->make(Compositions::class)->findings());
    }

    /** §2.2: what the leaves get from article.blade.php, and that this is the view a site would copy. */
    #[Test]
    public function the_base_says_what_its_leaves_get_and_which_view_includes_it(): void
    {
        $tree = $this->app->make(Inspector::class)->tree('blog.article');

        $this->assertSame(['article', 'related', 'content'], $tree->vars);
        $this->assertSame('webx-blog::article', $tree->dispatcher);
        $this->assertStringContainsString("composition_view('blog.article')", (string) file_get_contents(View::getFinder()->find((string) $tree->dispatcher)));

        foreach (['breadcrumbs', 'title', 'meta', 'cover', 'content', 'tags', 'related'] as $leaf) {
            $source = (string) file_get_contents(View::getFinder()->find("webx-blog::article.{$leaf}"));
            preg_match_all('/\$([a-z]+)\b(?!\s*=[^=>])/', (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source), $read);
            preg_match_all('/(?:\$([a-z]+)\s*=[^=>]|\bas \$([a-z]+))/', $source, $set);

            $this->assertSame([], array_values(array_diff($read[1], $tree->vars, $set[1], $set[2], ['loop'])), "article.{$leaf} reads only what the base says its leaves get");
        }
    }

    #[Test]
    public function a_theme_puts_the_cover_above_the_title(): void
    {
        $this->theme(['move' => [['id' => 'article.cover', 'before' => 'article.title']]]);

        $html = $this->html();

        $this->assertLessThan(strpos($html, '<h1 class="webx-blog-article__title">'), strpos($html, '<img class="webx-blog-article__cover"'));
        $this->assertGreaterThan(strpos($html, '<nav class="webx-breadcrumbs"'), strpos($html, '<img class="webx-blog-article__cover"'));
    }

    #[Test]
    public function a_theme_drops_read_next(): void
    {
        $this->theme(['remove' => ['article.related']]);

        $html = $this->html();

        $this->assertStringNotContainsString('webx-blog-related', $html);
        $this->assertStringContainsString('<p class="b-text">Loosen the tensioner first.</p>', $html);
    }

    #[Test]
    public function a_theme_prints_the_meta_with_a_view_of_its_own(): void
    {
        View::addNamespace('site-blog', __DIR__.'/Fixtures/site-blog');
        $this->theme(['replace' => ['article.meta' => ['view' => 'site-blog::meta']]]);

        $html = $this->html();

        $this->assertStringContainsString('<p class="site-byline">By Editor</p>', $html);
        $this->assertStringNotContainsString('webx-blog-article__meta', $html);
        // In the same place: inside the header, after the title.
        $this->assertLessThan(strpos($html, '</header>', (int) strpos($html, 'webx-blog-article__header')), strpos($html, 'site-byline'));
        $this->assertGreaterThan(strpos($html, '<h1'), strpos($html, 'site-byline'));
    }

    #[Test]
    public function a_theme_adds_a_node_of_its_own_before_the_text(): void
    {
        View::addNamespace('site-blog', __DIR__.'/Fixtures/site-blog');
        $this->theme(['add' => [['id' => 'lead', 'to' => 'article', 'before' => 'article.content', 'view' => 'site-blog::lead']]]);

        $html = $this->html();

        $this->assertMatchesRegularExpression('~</header>\s*<p class="site-lead">Belts wear out\.</p>\s*<p class="b-text">~', $html);
    }

    #[Test]
    public function a_theme_sets_attributes_of_the_article(): void
    {
        $this->theme(['attrs' => ['article' => ['class' => 'is-wide', 'data-reading' => true]]]);

        $this->assertStringContainsString('<article class="webx-blog-article is-wide" data-reading>', $this->html());
    }

    /** §4: the page is included with `default`; a file for the handle of its own name is not read. */
    #[Test]
    public function a_file_for_the_handle_of_its_own_name_is_not_read(): void
    {
        $this->theme(['attrs' => ['article' => ['class' => 'is-wide']]], 'blog.article');

        $this->assertStringContainsString('<article class="webx-blog-article">', $this->html());
    }

    #[Test]
    public function the_body_of_the_article_is_not_removed_nor_replaced(): void
    {
        $this->theme(['remove' => ['article.content'], 'replace' => ['article.content' => ['html' => '<p>Nothing</p>']]]);

        $html = $this->html();

        $this->assertStringContainsString('<p class="b-text">Loosen the tensioner first.</p>', $html);
        $this->assertCount(2, $this->app->make(Compositions::class)->findings());
    }

    #[Test]
    public function a_site_that_copied_the_whole_article_view_keeps_its_copy(): void
    {
        $this->theme(['remove' => ['article.related']]);
        View::prependNamespace('webx-blog', __DIR__.'/Fixtures/site-views/vendor/webx-blog');

        $html = (string) $this->get($this->fullArticle())->assertOk()->getContent();

        $this->assertStringContainsString('<article class="site-copy"><h1>Changing a belt</h1></article>', $html);
        $this->assertStringNotContainsString('webx-blog-article__header', $html);
    }

    #[After]
    protected function forgetTheme(): void
    {
        if ($this->theme !== null) {
            (new Filesystem)->deleteDirectory($this->theme);
            $this->theme = null;
        }
    }

    /**
     * A local theme with one file: compositions/blog.article/<handle>.json.
     *
     * @param  array<string, mixed>  $patch
     */
    private function theme(array $patch, string $handle = 'default'): void
    {
        $this->theme = sys_get_temp_dir().'/webx-blog-theme-'.str_replace('.', '', uniqid('', true));
        $dir = $this->theme.'/compositions/blog.article';
        mkdir($dir, 0777, true);
        file_put_contents("{$dir}/{$handle}.json", json_encode($patch, JSON_THROW_ON_ERROR));

        $compositions = $this->theme.'/compositions';
        Compositions::themes(static fn (): array => [['name' => 'site', 'dir' => $compositions, 'local' => true]]);
    }

    private function html(): string
    {
        return (string) $this->get($this->fullArticle())->assertOk()->getContent();
    }

    /** A lead, a byline, a cover, blocks and two articles to read next. */
    private function fullArticle(): string
    {
        config()->set('webx-blog.layout', 'site::layout');
        $this->blockType('text', '<p class="b-text">{{ $text }}</p>');

        $article = $this->article('changing-a-belt');
        $article->lead = 'Belts wear out.';
        $article->blocks = [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Loosen the tensioner first.']]];
        $article->save();
        $article->publish();
        $article->tags()->attach($this->tag('belts'));
        $article->related()->attach($this->article('changing-a-filter')->getKey(), ['position' => 0]);

        // The byline without the author pages: the account's name is enough here.
        $article->author_id = $this->editor()->getKey();
        $article->cover_id = $this->picture()->getKey();
        $article->save();

        return '/blog/changing-a-belt';
    }

    private function picture(): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/cover.jpg',
            'hash' => str_repeat('a', 32),
            'name' => 'cover',
            'file_name' => 'cover.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
