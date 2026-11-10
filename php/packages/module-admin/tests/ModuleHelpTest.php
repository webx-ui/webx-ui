<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Help\HelpBuilder;
use WebxUi\Admin\Help\HelpRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Testing\AssertsHelp;

/**
 * `assertModuleScreensHaveHelp()`: a module of the site answers for its own screens, in English and
 * the languages of the site's panel, and for nothing the packages loaded beside it draw
 * (WEBX_UI_SITE_MODULES.md §10).
 */
final class ModuleHelpTest extends TestCase
{
    use AssertsHelp;

    private string $module;

    private const string FORM = <<<'MD'
    ---
    title: Office
    ---

    # about

    One office of the directory.

    # fields

    ## title

    The name visitors see.
    MD;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = sys_get_temp_dir().'/wx-module-help-'.bin2hex(random_bytes(4));

        $this->beforeApplicationDestroyed(function (): void {
            File::deleteDirectory($this->module);
        });

        File::ensureDirectoryExists($this->module.'/resources/screens');
        File::put($this->module.'/resources/screens/form.json', (string) json_encode([
            ['id' => 'title', 'type' => 'wx-input', 'name' => 'title', 'label' => 'Title'],
        ]));

        $this->app->instance(HelpRegistry::class, new HelpRegistry);
        $this->app->forgetInstance(HelpBuilder::class);

        // As a provider says it: through `src/..`.
        File::ensureDirectoryExists($this->module.'/src');
        $this->app->make(HelpRegistry::class)->directory($this->module.'/src/../resources/help');
        Screens::register('offices.form', $this->module.'/src/../resources/screens/form.json');

        // A package's screen nobody described: not the module's business.
        Screens::register('shop.undescribed-form', [
            ['id' => 'code', 'type' => 'wx-input', 'name' => 'code', 'label' => 'Code'],
        ]);

        foreach (['en', 'ru'] as $language) {
            $this->write($language, self::FORM);
        }
    }

    private function write(string $language, string $text): void
    {
        File::ensureDirectoryExists("{$this->module}/resources/help/{$language}");
        File::put("{$this->module}/resources/help/{$language}/offices.form.md", $text);
    }

    /**
     * @param  list<string>|null  $languages
     */
    private function failure(?array $languages = ['ru']): ?string
    {
        try {
            $this->assertModuleScreensHaveHelp($this->module, $languages);
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }

        return null;
    }

    #[Test]
    public function the_screens_of_the_module_are_the_ones_registered_from_its_folder(): void
    {
        $this->assertSame(['offices.form'], $this->app->make(ScreenRegistry::class)->registeredFrom($this->module));
    }

    #[Test]
    public function a_described_module_is_healthy_beside_a_screen_of_a_package_without_help(): void
    {
        $this->assertNull($this->failure());
    }

    #[Test]
    public function a_screen_of_the_module_without_help_is_red(): void
    {
        File::deleteDirectory($this->module.'/resources/help');
        File::ensureDirectoryExists($this->module.'/resources/help/en');

        $this->assertStringContainsString('offices.form: the screen has no help', (string) $this->failure());
    }

    #[Test]
    public function a_field_of_the_module_without_a_description_is_red(): void
    {
        foreach (['en', 'ru'] as $language) {
            $this->write($language, str_replace("\n\n## title\n\nThe name visitors see.", '', self::FORM));
        }

        $this->assertStringContainsString('offices.form: the field [title] is not described', (string) $this->failure());
    }

    #[Test]
    public function a_language_the_panel_does_not_speak_is_not_required(): void
    {
        // No uk, de or anything else: the site's panel speaks en and ru.
        $this->assertNull($this->failure(['ru']));
        $this->assertNull($this->failure([]));
    }

    #[Test]
    public function a_language_the_panel_speaks_is_required(): void
    {
        $this->assertStringContainsString('no file in uk', (string) $this->failure(['ru', 'uk']));
    }

    #[Test]
    public function the_languages_are_the_panels_when_not_given(): void
    {
        config(['webx-localization.panel' => ['en', 'ru', 'uk']]);
        $this->assertStringContainsString('no file in uk', (string) $this->failure(null));

        config(['webx-localization.panel' => ['en', 'ru']]);
        $this->assertNull($this->failure(null));
    }

    #[Test]
    public function a_folder_nobody_registered_is_red(): void
    {
        $this->app->instance(HelpRegistry::class, new HelpRegistry);
        $this->app->forgetInstance(HelpBuilder::class);

        $this->assertStringContainsString('the folder is not registered', (string) $this->failure());
    }
}
