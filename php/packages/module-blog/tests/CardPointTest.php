<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;

/** The card of a listing is the point `blog.card` (THEMES §10.4). */
final class CardPointTest extends TestCase
{
    #[Test]
    public function the_module_prints_its_own_card_with_its_classes(): void
    {
        $this->article('changing-a-belt');

        $feed = (string) $this->get('/blog')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="webx-blog-cards">', $feed);
        $this->assertStringContainsString('<h2 class="webx-blog-card__title">Changing a belt</h2>', $feed);
    }

    /** No provider and no overridden listing: the theme's `views/parts/blog/card.blade.php`. */
    #[Test]
    public function a_theme_answers_the_point_with_a_view_of_its_own(): void
    {
        $this->article('changing-a-belt');

        $theme = storage_path('framework/testing/theme-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($theme.'/parts/blog');
        File::put($theme.'/parts/blog/card.blade.php', '<li class="theme-card">{{ $article->title }}</li>');
        $this->beforeApplicationDestroyed(static function () use ($theme): void {
            File::deleteDirectory($theme);
        });
        View::addLocation($theme);

        $feed = (string) $this->get('/blog')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="webx-blog-cards">', $feed);
        $this->assertStringContainsString('<li class="theme-card">Changing a belt</li>', $feed);
        $this->assertStringNotContainsString('webx-blog-card__title', $feed);
    }
}
