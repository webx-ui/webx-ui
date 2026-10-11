<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\ThemeChain;

/**
 * `webx:theme:check` (spec §17): a refusal with a line per finding and a non-zero exit, or
 * success on a clean chain.
 */
final class CheckCommandTest extends TestCase
{
    #[Test]
    public function a_clean_chain_passes(): void
    {
        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('knows only tokens')
            ->assertSuccessful();
    }

    #[Test]
    public function every_finding_is_a_line_and_the_exit_code_says_so(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('dirty-theme')])
            ->expectsOutputToContain('tokens.json:2  defaults sets "color-pink", which is not in the vocabulary')
            ->expectsOutputToContain('tokens.json:2  defaults sets "radius-md" to "a lot", which is not a valid length')
            ->expectsOutputToContain('tokens.json:4  preset "night" sets "color-bg" to "url(x)"')
            ->expectsOutputToContain('src/css/theme.css:2  literal colour #333')
            ->expectsOutputToContain('src/css/theme.css:3  literal font family Georgia')
            ->expectsOutputToContain('src/css/theme.css:5  token not in the vocabulary --site-color-pink')
            ->expectsOutputToContain('src/css/theme.css:5  fallback inside var() var(--site-color-pink, …)')
            ->expectsOutputToContain('src/css/theme.css:8  @media by width')
            ->expectsOutputToContain('8 findings')
            ->assertFailed();
    }

    #[Test]
    public function the_css_of_the_layers_below_the_themes_is_read_too(): void
    {
        $this->app->make(BottomLayers::class)->add('webx-ui/widgets-fixture', self::fixture('dirty-widgets'));

        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('dirty-widgets/resources/css/slider.css:2  literal colour white')
            ->expectsOutputToContain('1 finding ')
            ->assertFailed();
    }

    #[Test]
    public function a_theme_that_cannot_be_found_is_a_refusal(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('nowhere')])->assertFailed();
    }

    #[Test]
    public function without_a_theme_there_is_nothing_to_check(): void
    {
        $this->app->instance(ThemeChain::class, new ThemeChain);

        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('nothing to check')
            ->assertSuccessful();
    }
}
