<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Snippets\Checks\PlaceFinder;
use WebxUi\Snippets\Doctor\SnippetPlaces;

/**
 * Lost and undeclared places (§10): `webx:theme:check` reads the views Blade would read, and
 * `webx:doctor` says the same plus what is in the database.
 */
final class DiagnosticsTest extends TestCase
{
    #[Test]
    public function the_finder_knows_both_forms_and_only_the_file_blade_would_read(): void
    {
        $theme = dirname(__DIR__, 2).'/theme-default/views';

        $found = (new PlaceFinder)->find([self::fixture('site'), $theme]);
        $this->assertSame(['before-footer', 'body_end', 'body_start', 'head'], $this->sorted(array_keys($found['places'])));
        $this->assertStringEndsWith('theme-default/views/components/layout.blade.php', (string) $found['layout']);
        $this->assertStringEndsWith('Fixtures/site/custom.blade.php', $found['places']['before-footer']['file']);
        $this->assertSame(4, $found['places']['before-footer']['line']);

        // The site's own layout hides the theme's, places and all.
        $found = (new PlaceFinder)->find([self::fixture('own-layout'), $theme]);
        $this->assertSame([], $found['places']);
        $this->assertStringEndsWith('Fixtures/own-layout/components/layout.blade.php', (string) $found['layout']);

        $found = (new PlaceFinder)->find([self::fixture('dynamic')]);
        $this->assertSame([], $found['places']);
        $this->assertSame(2, $found['dynamic'][0]['line']);
    }

    #[Test]
    public function the_theme_check_passes_theme_defaults_layout_and_a_place_the_site_prints(): void
    {
        config()->set('webx-snippets.places', ['before-footer' => 'Before the footer']);

        $this->assertSame(0, Artisan::call('webx:theme:check'), Artisan::output());
    }

    #[Test]
    public function the_theme_check_fails_on_a_place_declared_and_printed_by_no_view(): void
    {
        config()->set('webx-snippets.places', ['sidebar' => 'Sidebar']);

        $this->assertSame(1, Artisan::call('webx:theme:check'));
        $output = Artisan::output();

        $this->assertStringContainsString('webx-snippets.php  place "sidebar" is declared but no view prints it', $output);
        $this->assertStringContainsString('1 finding', $output);
    }

    #[Test]
    public function the_theme_check_fails_on_a_layout_of_the_sites_own_that_lost_the_places(): void
    {
        config()->set('view.paths', [self::fixture('own-layout'), self::fixture('site')]);

        $this->assertSame(1, Artisan::call('webx:theme:check'));
        $output = Artisan::output();

        foreach (['head', 'body_start', 'body_end'] as $place) {
            $this->assertStringContainsString("own-layout/components/layout.blade.php  no <x-webx-snippets place=\"{$place}\">", $output);
        }
    }

    #[Test]
    public function a_place_set_by_an_expression_is_a_note_not_a_finding(): void
    {
        config()->set('view.paths', [self::fixture('site'), self::fixture('dynamic')]);

        $this->assertSame(0, Artisan::call('webx:theme:check'));
        $this->assertStringContainsString('places.blade.php:2  note: place set by an expression, not checked.', Artisan::output());
    }

    #[Test]
    public function the_doctor_warns_about_a_lost_place_and_codes_in_an_undeclared_one(): void
    {
        $this->assertContains(SnippetPlaces::class, $this->app->make(DoctorChecks::class)->all());

        config()->set('webx-snippets.places', ['sidebar' => 'Sidebar']);
        DB::table('snippets_codes')->insert([
            ['name' => 'A', 'place' => 'promo', 'code' => '<i>a</i>', 'consent' => 'necessary', 'enabled' => true, 'on_stage' => true, 'position' => 1],
            ['name' => 'B', 'place' => 'promo', 'code' => '<i>b</i>', 'consent' => 'necessary', 'enabled' => false, 'on_stage' => true, 'position' => 2],
        ]);

        $lines = $this->lines();

        $this->assertContains('warn: place "sidebar" is declared but no view prints it — add <x-webx-snippets place="sidebar" /> to the layout; php artisan webx:theme:check says which file.', $lines);
        $this->assertContains('warn: 2 snippets are in place "promo", which config/webx-snippets.php no longer declares — not printed. Move them in the panel, or declare the place again.', $lines);
    }

    #[Test]
    public function the_doctor_warns_about_codes_waiting_for_a_consent_script_the_site_will_never_have(): void
    {
        $this->snippet('body_end', '<i>pixel</i>', ['consent' => 'marketing']);

        // Nothing published yet: the consent script is not on the site.
        $this->assertContains('warn: 1 enabled snippet waits for consent, and this site has no consent script to release it — they are never printed. Configure a theme (webx-themes.theme) and run php artisan webx:theme:sync.', $this->lines());

        $this->artisan('webx:theme:sync')->assertSuccessful();
        $this->assertSame(['ok: every place is printed by a view: head, body_start, body_end.'], $this->lines());
    }

    #[Test]
    public function outside_production_the_doctor_says_why_most_codes_are_not_on_the_page(): void
    {
        $this->snippet('head', '<i>counter</i>', ['on_stage' => false]);

        $this->assertContains('ok: the environment is "testing": 1 enabled snippet without "on dev and stage too" is not printed here, only in production.', $this->lines());
    }

    /** @return list<string> */
    private function lines(): array
    {
        return array_map(
            static fn (Diagnosis $diagnosis): string => "{$diagnosis->state}: {$diagnosis->detail}",
            $this->app->make(SnippetPlaces::class)->run(),
        );
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function sorted(array $keys): array
    {
        sort($keys);

        return $keys;
    }
}
