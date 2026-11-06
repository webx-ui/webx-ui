<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Help\HelpAudit;
use WebxUi\Admin\Help\HelpBuilder;
use WebxUi\Admin\Help\HelpRegistry;

/**
 * What goes red, one row of WEBX_UI_PANEL_HELP.md §6.2 at a time: a healthy screen is built, one
 * thing is taken out of it, and only that is reported.
 */
final class HelpAuditTest extends TestCase
{
    private string $root;

    private const string ENGLISH = <<<'MD'
    ---
    title: Item
    guide: guide-of-items#anchor
    ---

    # about

    What an item is.

    # fields

    ## title

    What the buyer sees.

    ## code

    Old links stop working if it changes.

    ## line-city

    A row of the repeater, described like any field.
    MD;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/wx-help-audit-'.bin2hex(random_bytes(4));

        $this->beforeApplicationDestroyed(function (): void {
            File::deleteDirectory($this->root);
        });

        Screens::register('shop.item-form', [
            ['id' => 'title', 'type' => 'wx-input', 'name' => 'title', 'label' => 'Title'],
            ['id' => 'code', 'type' => 'wx-input', 'name' => 'code', 'label' => 'Code', 'help' => 'Latin letters and digits.'],
            ['id' => 'lines', 'type' => 'wx-repeater', 'label' => 'Lines', 'children' => [
                ['id' => 'line-city', 'type' => 'wx-input', 'name' => 'city', 'label' => 'City'],
            ]],
        ]);

        foreach (HelpAudit::LANGUAGES as $language) {
            $this->write('owner', $language, 'shop.item-form', self::ENGLISH);
        }
    }

    private function write(string $package, string $language, string $name, string $text): void
    {
        File::ensureDirectoryExists("{$this->root}/{$package}/{$language}");
        File::put("{$this->root}/{$package}/{$language}/{$name}.md", $text);
        $this->app->make(HelpRegistry::class)->directory("{$this->root}/{$package}");
    }

    /**
     * @param  array<string, string>  $pending
     * @return list<string>
     */
    private function problems(array $pending = [], bool $strict = true): array
    {
        // A new audit reads the files anew; the registry remembers what it has parsed.
        $registry = new HelpRegistry;

        foreach (glob($this->root.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $registry->directory($directory);
        }

        $this->app->instance(HelpRegistry::class, $registry);
        $this->app->forgetInstance(HelpBuilder::class);

        $guides = $this->root.'/guides';
        File::ensureDirectoryExists($guides);
        File::put($guides.'/guide-of-items.md', '# Items');

        $problems = $this->app->make(HelpAudit::class)->problems($pending, $guides, $strict);

        return array_map(fn (string $line): string => str_replace($this->root.'/', '', $line), $problems);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function everyLanguage(array $replacements): void
    {
        foreach (HelpAudit::LANGUAGES as $language) {
            $this->write('owner', $language, 'shop.item-form', strtr(self::ENGLISH, $replacements));
        }
    }

    #[Test]
    public function a_screen_with_everything_explained_is_healthy(): void
    {
        $this->assertSame([], $this->problems());
    }

    #[Test]
    public function a_screen_without_a_file_is_red(): void
    {
        File::deleteDirectory($this->root.'/owner');

        $this->assertSame(['shop.item-form: the screen has no help, it needs a title and # about in en'], $this->problems());
    }

    #[Test]
    public function a_field_whose_description_was_taken_out_is_red(): void
    {
        $this->everyLanguage(["## code\n\nOld links stop working if it changes.\n\n" => '']);

        $this->assertSame(['shop.item-form: the field [code] is not described'], $this->problems());
    }

    #[Test]
    public function a_row_of_a_repeater_is_a_field_like_any_other(): void
    {
        $this->everyLanguage(["\n\n## line-city\n\nA row of the repeater, described like any field." => '']);

        $this->assertSame(['shop.item-form: the field [line-city] is not described'], $this->problems());
    }

    #[Test]
    public function a_description_of_a_field_that_is_gone_is_red(): void
    {
        $this->everyLanguage(['## code' => "## old-code\n\nGone.\n\n## code"]);

        $this->assertSame(['shop.item-form: [old-code] is described, and the screen has no such field'], $this->problems());
    }

    #[Test]
    public function a_description_of_a_field_a_setting_took_away_is_healthy(): void
    {
        Screens::extend('shop.item-form', [['op' => 'remove', 'target' => 'code']]);

        $this->assertSame([], $this->problems());
    }

    #[Test]
    public function a_field_a_patch_added_and_a_later_one_took_away_may_stay_described(): void
    {
        Screens::extend('shop.item-form', [
            ['op' => 'add', 'target' => 'lines', 'node' => ['id' => 'line-zip', 'type' => 'wx-input', 'name' => 'zip', 'label' => 'Zip']],
        ]);
        Screens::extend('shop.item-form', [['op' => 'remove', 'target' => 'line-zip']]);
        $this->everyLanguage(['## code' => "## line-zip\n\nAdded and taken away.\n\n## code"]);

        $this->assertSame([], $this->problems());
    }

    #[Test]
    public function a_removed_node_that_was_no_field_is_red_when_described(): void
    {
        Screens::extend('shop.item-form', [['op' => 'remove', 'target' => 'lines']]);
        $this->everyLanguage(['## code' => "## lines\n\nNot a field.\n\n## code"]);

        $this->assertSame(['shop.item-form: [lines] is described, and the screen has no such field'], $this->problems());
    }

    #[Test]
    public function a_field_on_the_screen_must_be_described_whatever_was_taken_away(): void
    {
        Screens::extend('shop.item-form', [['op' => 'remove', 'target' => 'code']]);
        $this->everyLanguage(["## title\n\nWhat the buyer sees.\n\n" => '']);

        $this->assertSame(['shop.item-form: the field [title] is not described'], $this->problems());
    }

    #[Test]
    public function one_id_described_by_two_packages_is_red(): void
    {
        foreach (HelpAudit::LANGUAGES as $language) {
            $this->write('satellite', $language, 'shop.item-form', "# fields\n\n## code\n\nMine.\n");
        }

        $this->assertSame(
            ['shop.item-form: [code] is described in owner/en/shop.item-form.md and in satellite/en/shop.item-form.md'],
            $this->problems(),
        );
    }

    #[Test]
    public function a_project_may_describe_a_field_over_a_package(): void
    {
        $this->write('satellite', 'en', 'shop.item-form', "# fields\n\n## code\n\nOurs.\n");

        $this->assertSame([], $this->problems(strict: false));
    }

    #[Test]
    public function a_language_without_the_file_is_red(): void
    {
        File::delete($this->root.'/owner/uk/shop.item-form.md');

        $this->assertSame(['owner/en/shop.item-form.md: no file in uk'], $this->problems());
    }

    #[Test]
    public function a_language_with_other_fields_guide_or_include_is_red(): void
    {
        $this->write('owner', 'ru', 'shop.item-form', strtr(self::ENGLISH, [
            "guide: guide-of-items#anchor\n" => "include: shop.other\n",
            "## code\n\nOld links stop working if it changes.\n\n" => '',
        ]));

        $this->assertSame([
            'owner/ru/shop.item-form.md: guide differs from en',
            'owner/ru/shop.item-form.md: include differs from en',
            'owner/ru/shop.item-form.md: [code] is described in en and not here',
        ], $this->problems());
    }

    #[Test]
    public function a_file_with_no_english_reference_is_red(): void
    {
        $this->write('owner', 'ru', 'shop.only-russian', "---\ntitle: Т\n---\n\n# about\n\nТ.\n");

        $this->assertContains(
            'owner/ru/shop.only-russian.md: there is no such file in en, and en decides what exists',
            $this->problems(),
        );
    }

    #[Test]
    public function a_description_that_repeats_the_hint_is_red(): void
    {
        $this->everyLanguage(['Old links stop working if it changes.' => 'As it says: latin  letters and digits. And that is all.']);

        $this->assertSame([
            'shop.item-form: the description of [code] in en repeats its hint, it has to go on from it',
            'shop.item-form: the description of [code] in ru repeats its hint, it has to go on from it',
            'shop.item-form: the description of [code] in uk repeats its hint, it has to go on from it',
        ], $this->problems());
    }

    #[Test]
    public function what_the_panel_cannot_draw_is_red(): void
    {
        foreach (['| a | b |' => 'a table', '![a](b.png)' => 'a picture', 'Some <b>bold</b> word.' => 'HTML', '### Deeper' => 'a heading'] as $text => $what) {
            $this->everyLanguage(['What an item is.' => $text]);

            $this->assertContains("owner/en/shop.item-form.md: {$what} in the text, the panel will not draw it", $this->problems());
        }
    }

    #[Test]
    public function code_may_show_what_a_text_may_not(): void
    {
        $this->everyLanguage(['What an item is.' => 'Write `<b>` or `| a |` here.']);

        $this->assertSame([], $this->problems());
    }

    #[Test]
    public function a_guide_that_is_not_there_is_red(): void
    {
        $this->everyLanguage(['guide-of-items#anchor' => 'no-such-guide']);

        $this->assertSame(['owner/en/shop.item-form.md: there is no guide [no-such-guide]'], $this->problems());
    }

    #[Test]
    public function a_broken_file_is_reported_and_the_rest_still_is(): void
    {
        $this->write('owner', 'en', 'shop.broken', "# nonsense\n");

        $this->assertStringContainsString('shop.broken.md', implode("\n", $this->problems()));
    }

    #[Test]
    public function a_part_nobody_owns_is_red(): void
    {
        foreach (HelpAudit::LANGUAGES as $language) {
            $this->write('satellite', $language, 'shop.other', "# fields\n\n## code\n\nMine.\n");
        }

        $this->assertSame(['satellite/en/shop.other.md: nobody owns this key, no file of it has a title and # about'], $this->problems());
    }

    #[Test]
    public function a_pending_screen_may_go_without_a_file(): void
    {
        File::deleteDirectory($this->root.'/owner');

        $this->assertSame([], $this->problems(['shop.item-form' => 'filling, wave A']));
    }

    #[Test]
    public function a_pending_screen_that_got_its_file_is_red(): void
    {
        $this->assertSame(
            ['shop.item-form: still in the pending list, and the text is there'],
            $this->problems(['shop.item-form' => 'filling, wave A']),
        );
    }

    #[Test]
    public function one_field_may_wait_for_its_satellite_and_not_longer_than_its_text(): void
    {
        $this->everyLanguage(["## code\n\nOld links stop working if it changes.\n\n" => '']);
        $this->assertSame([], $this->problems(['shop.item-form#code' => 'filling, wave B']));

        $this->everyLanguage([]);
        $this->assertSame(
            ['shop.item-form#code: still in the pending list, and the text is there'],
            $this->problems(['shop.item-form#code' => 'filling, wave B']),
        );
    }

    #[Test]
    public function the_command_says_what_is_not_explained_on_this_site(): void
    {
        $this->everyLanguage(["## code\n\nOld links stop working if it changes.\n\n" => '']);
        $this->problems();

        $this->artisan('webx:help', ['--check' => true])
            ->expectsOutputToContain('shop.item-form: the field [code] is not described')
            ->assertExitCode(1);
    }

    #[Test]
    public function the_command_is_quiet_about_a_healthy_site(): void
    {
        $this->problems();

        $this->artisan('webx:help', ['--check' => true])->assertExitCode(0);
        $this->artisan('webx:help')->expectsOutput('shop.item-form')->assertExitCode(0);
    }

    #[Test]
    public function the_command_points_at_a_shared_description_nothing_uses(): void
    {
        $this->write('owner', 'en', '_fields', "# fields\n\n## nothing-like-this\n\nShared.\n");
        $this->problems();

        $this->artisan('webx:help', ['--check' => true])
            ->expectsOutputToContain('_fields: [nothing-like-this] is described, and no screen has such a field')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_shared_description_of_a_field_a_setting_took_away_is_healthy(): void
    {
        Screens::extend('shop.item-form', [['op' => 'remove', 'target' => 'code']]);
        $this->everyLanguage(["## code\n\nOld links stop working if it changes.\n\n" => '']);
        $this->write('owner', 'en', '_fields', "# fields\n\n## code\n\nShared.\n");

        $this->assertSame([], $this->problems(strict: false));
    }

    #[Test]
    public function the_command_points_at_an_include_that_leads_nowhere(): void
    {
        $this->everyLanguage(["guide: guide-of-items#anchor\n" => "include: shop.nowhere\n"]);
        $this->problems();

        $this->artisan('webx:help', ['--check' => true])
            ->expectsOutputToContain('include [shop.nowhere] leads nowhere')
            ->assertExitCode(1);
    }
}
