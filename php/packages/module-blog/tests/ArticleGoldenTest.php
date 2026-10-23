<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Author;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * The page of an article, byte for byte (COMPOSITIONS §10, stage 4): what the article printed
 * before it became a composition is kept in `Fixtures/golden/`, and the article has to print
 * exactly that without a patch — with everything an article can carry, and with nothing but a title.
 *
 * Only the body is compared: the head is module-seo's, and a change there is not a change of the
 * article. `WEBX_GOLDEN=update` rewrites the files; a change of the page is then read in the diff.
 */
final class ArticleGoldenTest extends TestCase
{
    /** @return array<string, array{bool, ?string}> */
    public static function pages(): array
    {
        return [
            'full' => [true, 'site::layout'],
            'full-standalone' => [true, null],
            'bare' => [false, 'site::layout'],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function the_article_prints_the_page_it_always_printed(bool $full, ?string $layout): void
    {
        Carbon::setTestNow('2026-05-14 09:30:00');
        config()->set('webx-blog.layout', $layout);

        $url = $full ? $this->fullArticle() : $this->bareArticle();
        $html = (string) $this->get($url)->assertOk()->getContent();
        $body = substr($html, (int) strpos($html, '<body>'));

        $golden = __DIR__.'/Fixtures/golden/'.$this->dataName().'.html';

        if (getenv('WEBX_GOLDEN') === 'update') {
            @mkdir(dirname($golden), 0777, true);
            file_put_contents($golden, $body);
        }

        $this->assertFileExists($golden);
        $this->assertSame((string) file_get_contents($golden), $body);
    }

    /** Breadcrumbs, a date and an author with a page, a cover, blocks, tags and two to read next. */
    private function fullArticle(): string
    {
        config()->set('webx-blog.authors', true);

        $this->blockType('text', '<p class="b-text">{{ $text }}</p>');

        $user = CmsUser::query()->create([
            'name' => 'Login Anna',
            'email' => 'anna@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);
        Author::query()->create(['user_id' => $user->getKey(), 'title' => 'Anna Writer', 'slug' => 'anna', 'is_public' => true]);

        $article = new Article([
            'title' => 'Changing a belt',
            'slug' => 'changing-a-belt',
            'author_id' => $user->getKey(),
            'cover_id' => $this->picture()->getKey(),
            'blocks' => [['key' => 'a', 'type' => 'text', 'values' => ['text' => 'Loosen the tensioner first.']]],
        ]);
        $article->save();
        $article->publish();
        $article->rubrics()->attach($this->rubric('repairs'));
        $article->tags()->attach([$this->tag('belts')->getKey(), $this->tag('tools')->getKey()]);

        foreach (['changing-a-filter', 'checking-the-oil'] as $position => $slug) {
            $article->related()->attach($this->article($slug)->getKey(), ['position' => $position]);
        }

        return '/blog/changing-a-belt';
    }

    /** A title and nothing else an article may leave out. */
    private function bareArticle(): string
    {
        config()->set('webx-blog.breadcrumbs', false);
        $this->article('changing-a-belt');

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
