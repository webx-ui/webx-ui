<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
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
    public function the_block_types_of_the_layers_are_counted_when_clean(): void
    {
        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('3 block types')
            ->assertSuccessful();
    }

    #[Test]
    public function a_block_type_of_a_layer_is_checked_for_tokens_shape_and_schema(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('blocks-theme')])
            ->expectsOutputToContain('blocks/list.json  not a block document')
            ->expectsOutputToContain('blocks/loud.json:6  styles, line 3: literal colour #ff0000')
            ->expectsOutputToContain('blocks/hero.json  hero overrides the type of webx-ui/theme-fixture: field "title" was wx-input, is wx-textarea')
            ->expectsOutputToContain('field "buttons.url" (wx-input) is gone')
            ->expectsOutputToContain('4 findings')
            ->assertFailed();
    }

    /**
     * Rule 26: every language a layer declares in `locales` has the keys of `en`, no more and no
     * fewer; a language it only overrides a word in (theme-fixture's partial `pl`) is not compared.
     */
    #[Test]
    public function the_words_of_a_layer_have_the_keys_of_en_in_every_declared_locale(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('lines-theme')])
            ->expectsOutputToContain('lang/ru/theme.php  missing: contact.email — "ru" in locales has every key of en, and only those')
            ->expectsOutputToContain('lang/ru/theme.php  not in en: old')
            ->expectsOutputToContain('lang/pl/theme.php  the theme declares "pl" in locales, but has no lang/pl/theme.php')
            ->expectsOutputToContain('lang/uk/theme.php  not a words file')
            ->expectsOutputToContain('4 findings')
            ->assertFailed();
    }

    #[Test]
    public function a_chain_whose_layers_declare_no_locales_says_so(): void
    {
        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('no layer declares locales for words of its own')
            ->assertSuccessful();
    }

    #[Test]
    public function the_declared_locales_are_counted_when_clean(): void
    {
        $directory = sys_get_temp_dir().'/webx-lines-'.uniqid();
        mkdir($directory.'/lang/en', 0777, true);
        mkdir($directory.'/lang/pl', 0777, true);
        file_put_contents($directory.'/theme.json', json_encode(['theme' => ['uses' => ['webx-ui/theme-fixture'], 'locales' => ['en', 'pl']]]));
        file_put_contents($directory.'/lang/en/theme.php', "<?php return ['more' => 'More'];");
        file_put_contents($directory.'/lang/pl/theme.php', "<?php return ['more' => 'Więcej'];");

        try {
            $this->artisan('webx:theme:check', ['path' => $directory])
                ->expectsOutputToContain('words of the layout in 2 locales, every one with the keys of en')
                ->assertSuccessful();
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }

    #[Test]
    public function a_letter_takes_its_colours_from_theme_token_never_from_var_or_a_literal(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('mail-theme')])
            ->expectsOutputToContain('views/mail/layout.blade.php:2  literal colour #f4f5f7 in a letter')
            ->expectsOutputToContain('views/mail/layout.blade.php:3  literal colour #ffffff in a letter')
            ->expectsOutputToContain('views/mail/layout.blade.php:3  var(--site-color-text) in a letter — mail clients do not read CSS variables')
            ->expectsOutputToContain('views/mail/layout.blade.php:4  literal colour #2563eb in a letter')
            ->expectsOutputToContain('views/mail/layout.blade.php:5  literal colour red in a letter')
            ->expectsOutputToContain('views/mail/layout.blade.php:8  literal colour rgb(…) in a letter')
            ->expectsOutputToContain('6 findings')
            ->assertFailed();
    }

    #[Test]
    public function the_letters_of_the_layers_and_of_the_modules_are_counted_when_clean(): void
    {
        $this->artisan('webx:theme:check')
            ->expectsOutputToContain('1 letter, its colours from theme_token()')
            ->assertSuccessful();
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

    #[Test]
    public function a_preset_the_chain_does_not_declare_is_a_finding(): void
    {
        $directory = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-'.bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory.'/theme.json', json_encode(['theme' => ['uses' => ['webx-ui/theme-fixture'], 'preset' => 'dusk']]));

        try {
            $this->artisan('webx:theme:check', ['path' => $directory])
                ->expectsOutputToContain('it names the preset "dusk", which no tokens.json of the chain declares (it has: night, warm).')
                ->assertFailed();
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }

    #[Test]
    public function without_a_theme_there_is_nothing_to_show(): void
    {
        $this->app->instance(ThemeChain::class, new ThemeChain);

        $this->artisan('webx:theme:show')
            ->expectsOutputToContain('No theme is configured')
            ->assertSuccessful();
    }
}
