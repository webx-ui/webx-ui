<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * The card of the list is the point `events.card` (THEMES §10.4), and the list's own inline look
 * steps aside under a theme, whose stylesheet has to be the last word on its classes (§10.2).
 */
final class CardPointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
    }

    #[Test]
    public function the_module_prints_its_own_card_and_without_a_theme_its_inline_look(): void
    {
        $this->event('spring-cooking-class', '2026-10-20 10:00:00');

        $page = (string) $this->get('/events')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="wx-events__grid">', $page);
        $this->assertStringContainsString('<span class="wx-events__name">Spring cooking class</span>', $page);
        $this->assertStringContainsString('.wx-events__grid { display: grid;', $page);
    }

    #[Test]
    public function under_a_theme_the_list_prints_no_style_of_its_own(): void
    {
        $this->event('spring-cooking-class', '2026-10-20 10:00:00');
        $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('site', storage_path('theme'), true)]));

        $page = (string) $this->get('/events')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="wx-events__grid">', $page);
        $this->assertStringNotContainsString('.wx-events__grid {', $page);
    }

    /** No provider and no overridden list: the theme's `views/parts/events/card.blade.php`. */
    #[Test]
    public function a_theme_answers_the_point_with_a_view_of_its_own(): void
    {
        $this->event('spring-cooking-class', '2026-10-20 10:00:00');

        $theme = storage_path('framework/testing/theme-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($theme.'/parts/events');
        File::put($theme.'/parts/events/card.blade.php', '<li class="theme-card">{{ $card[\'title\'] }}</li>');
        $this->beforeApplicationDestroyed(static function () use ($theme): void {
            File::deleteDirectory($theme);
        });
        View::addLocation($theme);

        $page = (string) $this->get('/events')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="wx-events__grid">', $page);
        $this->assertStringContainsString('<li class="theme-card">Spring cooking class</li>', $page);
        $this->assertStringNotContainsString('class="wx-events__name"', $page);
    }
}
