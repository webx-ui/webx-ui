<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

/**
 * `webx:theme:show` (spec §16, §17): what the chain gives and from where — read by an agent
 * before it touches the look, so every answer has to name the layer that owns it.
 */
final class ShowCommandTest extends TestCase
{
    #[Test]
    public function the_layers_come_top_first_with_the_preset_none_of_them_names(): void
    {
        $report = $this->report();

        $this->assertSame([self::fixture('site/theme'), 'webx-ui/theme-fixture'], array_column($report['layers'], 'name'));
        $this->assertTrue($report['layers'][0]['local']);
        $this->assertNull($report['preset']);
        $this->assertSame(['night', 'warm'], array_keys($report['presets']));
    }

    #[Test]
    public function each_token_says_its_type_and_the_layer_that_set_it(): void
    {
        $tokens = $this->report()['tokens'];

        $this->assertSame(['type' => 'color', 'value' => '#c0392b', 'resolved' => null, 'from' => self::fixture('site/theme')], $tokens['color-accent']);
        $this->assertSame('webx-ui/theme-fixture', $tokens['color-bg']['from'], 'what the local theme does not set comes from below');
        $this->assertSame('color', $tokens['color-olive']['type'], 'a name a layer added to the vocabulary');
    }

    #[Test]
    public function every_view_of_the_layers_says_who_serves_it_and_whom_it_hides(): void
    {
        $views = array_column($this->report()['views'], null, 'view');
        $local = self::fixture('site/theme');

        $this->assertSame(['view' => 'local-wins.blade.php', 'from' => $local, 'hides' => ['webx-ui/theme-fixture']], $views['local-wins.blade.php']);
        $this->assertSame(['site', $local, 'webx-ui/theme-fixture'], [$views['site-wins.blade.php']['from'], ...$views['site-wins.blade.php']['hides']], "the site's own resources/views is above every layer");
        $this->assertSame([], $views['default-wins.blade.php']['hides']);
        $this->assertSame(['webx-ui/theme-fixture', 'module webx-fake'], $views['vendor/webx-fake/local-wins.blade.php']['hides'], 'an override of a module view hides the module');
        $this->assertSame('site', $views['vendor/webx-fake/site-wins.blade.php']['from']);
        $this->assertArrayNotHasKey('module-only.blade.php', $views, 'a view no layer carries is not the theme\'s');
    }

    #[Test]
    public function blocks_icons_fonts_and_module_stylesheets_name_their_layer(): void
    {
        $report = $this->report();
        $blocks = array_column($report['blocks'], null, 'slug');

        $this->assertSame(self::fixture('site/theme'), $blocks['hero']['from']);
        $this->assertSame(['webx-ui/theme-fixture'], $blocks['hero']['hides']);
        $this->assertSame('webx-ui/theme-fixture', $blocks['note']['from']);
        $this->assertSame(self::fixture('site/theme'), $report['icons']['phone'], 'the layer above wins the name');
        $this->assertSame('webx-ui/theme-fixture', $report['icons']['arrow-right']);
        $this->assertSame([['family' => 'Fixture Sans', 'weight' => '100 900', 'style' => 'normal', 'scripts' => ['cyrillic', 'latin'], 'from' => 'webx-ui/theme-fixture']], $report['fonts']);
        $this->assertSame(['module/fixture.css'], $report['modules']);
    }

    #[Test]
    public function the_table_says_where_a_value_comes_from_and_that_the_panel_does_not_choose(): void
    {
        $this->artisan('webx:theme:show')
            ->expectsOutputToContain('Layers, top first')
            ->expectsOutputToContain('--site-color-accent')
            ->expectsOutputToContain('the panel does not change the look')
            ->expectsOutputToContain('module webx-fake')
            ->assertSuccessful();
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        $this->assertSame(0, Artisan::call('webx:theme:show', ['--json' => true]));
        $report = json_decode(Artisan::output(), true);
        $this->assertIsArray($report);

        return $report;
    }
}
