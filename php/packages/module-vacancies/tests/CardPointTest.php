<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * The card of the list is the point `vacancies.card` (THEMES §10.4), and the index's own inline look
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
        $this->vacancy('pastry-chef');

        $page = (string) $this->get('/careers')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="wx-vacancies__list">', $page);
        $this->assertStringContainsString('<a class="wx-vacancies__link" href="http://localhost/careers/pastry-chef">Pastry chef</a>', $page);
        $this->assertStringContainsString('.wx-vacancies__list { display: grid;', $page);
    }

    #[Test]
    public function under_a_theme_the_list_prints_no_style_of_its_own(): void
    {
        $this->vacancy('pastry-chef');
        $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('site', storage_path('theme'), true)]));

        $page = (string) $this->get('/careers')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="wx-vacancies__list">', $page);
        $this->assertStringNotContainsString('.wx-vacancies__list {', $page);
    }

    /** No provider and no overridden list: the theme's `views/parts/vacancies/card.blade.php`. */
    #[Test]
    public function a_theme_answers_the_point_with_a_view_of_its_own(): void
    {
        $this->vacancy('pastry-chef');

        $theme = storage_path('framework/testing/theme-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($theme.'/parts/vacancies');
        File::put($theme.'/parts/vacancies/card.blade.php', '<li class="theme-card">{{ $card[\'title\'] }}</li>');
        $this->beforeApplicationDestroyed(static function () use ($theme): void {
            File::deleteDirectory($theme);
        });
        View::addLocation($theme);

        $page = (string) $this->get('/careers')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="wx-vacancies__list">', $page);
        $this->assertStringContainsString('<li class="theme-card">Pastry chef</li>', $page);
        $this->assertStringNotContainsString('class="wx-vacancies__link"', $page);
    }
}
