<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\Tokens;

class TokensTest extends TestCase
{
    /** @var list<string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $directory) {
            (new Filesystem)->deleteDirectory($directory);
        }

        parent::tearDown();
    }

    #[Test]
    public function the_values_merge_up_the_chain(): void
    {
        $tokens = app(Tokens::class);

        $this->assertSame('#c0392b', $tokens->get('color-accent'), 'the local theme over the package');
        $this->assertSame('4px', $tokens->get('radius-md'));
        $this->assertSame('#ffffff', $tokens->get('color-bg'), 'what the local theme does not set comes from below');
        $this->assertSame('"Inter", sans-serif', $tokens->get('font-heading'));
        $this->assertSame('#708238', $tokens->get('color-olive'), 'a name the package added to the vocabulary');
        $this->assertNull($tokens->get('color-danger'));
        $this->assertNull($tokens->preset());
    }

    #[Test]
    public function the_preset_the_manifest_names_is_merged_down_the_chain_and_laid_over_the_values(): void
    {
        $tokens = $this->localOver('night');

        $this->assertSame('night', $tokens->preset());
        $this->assertSame('#111418', $tokens->get('color-bg'), "the package's night");
        $this->assertSame('#ff8a65', $tokens->get('color-accent'), "the local theme's addition to it");
        $this->assertSame('4px', $tokens->get('radius-md'), 'a token no preset names keeps the layers\' value');
        $this->assertSame('Local night', $tokens->presets()['night']['title']);
        $this->assertSame(['color-bg' => '#111418', 'color-text' => '#e8eaed', 'color-accent' => '#ff8a65'], $tokens->presets()['night']['tokens']);
        $this->assertSame(['night', 'warm'], array_keys($tokens->presets()));
    }

    #[Test]
    public function each_value_says_which_layer_and_preset_gave_it(): void
    {
        $sources = $this->localOver('night')->sources();

        $this->assertSame('webx-ui/theme-fixture, preset night', $sources['color-bg']);
        $this->assertStringEndsWith(', preset night', $sources['color-accent']);
        $this->assertStringNotContainsString('theme-fixture', $sources['color-accent'], 'the local layer tuned the night of the package');
        $this->assertStringNotContainsString('preset', $sources['radius-md']);
        $this->assertSame('webx-ui/theme-fixture', app(Tokens::class)->sources()['color-bg'], 'no preset — the layer alone');
    }

    #[Test]
    public function a_preset_the_chain_does_not_have_is_an_error_naming_the_ones_it_has(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('it names the preset "gone", which no tokens.json of the chain declares (it has: night, warm).');

        $this->localOver('gone')->values();
    }

    #[Test]
    public function a_preset_that_is_not_a_name_is_an_invalid_manifest(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('"preset" is the name of a preset');

        $this->localOver('');
    }
    #[Test]
    public function the_css_is_one_root_rule_in_the_vocabulary_order(): void
    {
        $this->assertSame(<<<'CSS'
            :root {
              --site-color-bg: #ffffff;
              --site-color-text: #111111;
              --site-color-accent: #c0392b;
              --site-font-heading: "Inter", sans-serif;
              --site-radius-md: 4px;
              --site-color-olive: #708238;
            }
            CSS, app(Tokens::class)->css());
    }

    #[Test]
    public function theme_token_gives_the_merged_value_or_the_default(): void
    {
        $this->assertSame('#c0392b', theme_token('color-accent'));
        $this->assertSame('#e11d48', theme_token('color-danger', '#e11d48'));
        $this->assertNull(theme_token('color-danger'));
        $this->assertTrue(theme_active());
    }

    #[Test]
    public function a_value_of_the_wrong_type_in_a_tokens_file_is_an_error(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('defaults sets "radius-md" to "big", which is not a valid length');

        $this->tokensOf(['defaults' => ['radius-md' => 'big']])->values();
    }

    #[Test]
    public function a_name_outside_the_vocabulary_is_an_error(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('preset "dark" sets "color-brand", which is not in the vocabulary');

        $this->tokensOf(['presets' => ['dark' => ['title' => 'Dark', 'tokens' => ['color-brand' => '#000']]]])->values();
    }

    #[Test]
    public function a_theme_cannot_redeclare_a_name_of_the_engine(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('"gutter" is already in the engine\'s vocabulary');

        $this->tokensOf(['vocabulary' => ['gutter' => 'color']])->values();
    }

    #[Test]
    public function an_unknown_type_in_the_vocabulary_is_an_error(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('"color-brand" has an unknown type "colour"');

        $this->tokensOf(['vocabulary' => ['color-brand' => 'colour']])->values();
    }

    #[Test]
    public function an_unknown_key_in_a_tokens_file_is_an_error(): void
    {
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('unknown key "default"');

        $this->tokensOf(['default' => ['color-bg' => '#fff']])->values();
    }

    /**
     * A local theme in a temporary directory with the given tokens.json, standing on nothing.
     *
     * @param  array<string, mixed>  $tokens
     */
    private function tokensOf(array $tokens): Tokens
    {
        $directory = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-'.bin2hex(random_bytes(6));
        $this->temporary[] = $directory;

        mkdir($directory);
        file_put_contents($directory.'/theme.json', json_encode(['theme' => ['title' => 'Temporary']]));
        file_put_contents($directory.'/tokens.json', json_encode($tokens));

        return new Tokens(ThemeChain::resolve($directory, app(ThemeLocator::class)));
    }

    /** The local theme of the fixture site, copied into a temporary directory and naming a preset. */
    private function localOver(string $preset): Tokens
    {
        $directory = str_replace('\\', '/', sys_get_temp_dir()).'/webx-themes-'.bin2hex(random_bytes(6));
        $this->temporary[] = $directory;

        mkdir($directory);
        file_put_contents($directory.'/theme.json', json_encode(['theme' => ['title' => 'Local', 'uses' => ['webx-ui/theme-fixture'], 'preset' => $preset]]));
        copy(__DIR__.'/Fixtures/site/theme/tokens.json', $directory.'/tokens.json');

        return new Tokens(ThemeChain::resolve($directory, app(ThemeLocator::class)));
    }
}
