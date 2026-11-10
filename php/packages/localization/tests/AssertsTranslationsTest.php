<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Localization\Testing\AssertsTranslations;

/**
 * `assertTranslationsMatch()` for a module outside the monorepo: English is there, and every
 * language it has says what English says (WEBX_UI_SITE_MODULES.md §10).
 */
final class AssertsTranslationsTest extends PlainTestCase
{
    use AssertsTranslations;

    private string $lang;

    protected function setUp(): void
    {
        $this->lang = sys_get_temp_dir().'/wx-module-lang-'.bin2hex(random_bytes(4));

        $lines = ['title' => 'Offices', 'actions' => ['create' => 'New office']];
        $this->write('en', 'module', $lines);
        $this->write('ru', 'module', $lines);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->lang.'/*/*.php') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($this->lang.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            rmdir($directory);
        }

        if (is_dir($this->lang)) {
            rmdir($this->lang);
        }
    }

    /**
     * @param  array<string, mixed>  $lines
     */
    private function write(string $language, string $group, array $lines): void
    {
        if (! is_dir($this->lang.'/'.$language)) {
            mkdir($this->lang.'/'.$language, 0777, true);
        }

        file_put_contents("{$this->lang}/{$language}/{$group}.php", '<?php return '.var_export($lines, true).';');
    }

    /**
     * @param  list<string>  $languages
     */
    private function failure(array $languages = ['ru']): ?string
    {
        try {
            $this->assertTranslationsMatch($this->lang, $languages);
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }

        return null;
    }

    #[Test]
    public function the_same_keys_in_every_language_are_healthy(): void
    {
        $this->assertNull($this->failure());
    }

    #[Test]
    public function a_language_outside_the_list_is_not_required(): void
    {
        // No uk, de or the other ten: the panel of this site speaks en and ru.
        $this->assertNull($this->failure(['ru']));
        $this->assertNull($this->failure([]));
    }

    #[Test]
    public function a_language_of_the_list_is_required(): void
    {
        $this->assertStringContainsString('/uk: there is no such language', (string) $this->failure(['ru', 'uk']));
    }

    #[Test]
    public function without_english_it_is_red(): void
    {
        unlink($this->lang.'/en/module.php');
        rmdir($this->lang.'/en');

        $this->assertStringContainsString('/en: there is no such language', (string) $this->failure([]));
    }

    #[Test]
    public function an_empty_english_is_red(): void
    {
        unlink($this->lang.'/en/module.php');

        $this->assertStringContainsString('/en: no lines', (string) $this->failure([]));
    }

    #[Test]
    public function a_key_missing_in_a_language_is_red(): void
    {
        $this->write('ru', 'module', ['title' => 'Offices', 'actions' => []]);

        $this->assertStringContainsString('ru: [module.actions.create] is in en and not here', (string) $this->failure());
    }

    #[Test]
    public function a_key_only_a_language_has_is_red(): void
    {
        $this->write('ru', 'module', ['title' => 'Offices', 'actions' => ['create' => 'New office', 'delete' => 'Delete']]);

        $this->assertStringContainsString('ru: [module.actions.delete] is here and not in en', (string) $this->failure());
    }

    #[Test]
    public function a_language_present_but_not_listed_is_compared_too(): void
    {
        $this->write('de', 'module', ['title' => 'Offices']);

        $this->assertStringContainsString('de: [module.actions.create] is in en and not here', (string) $this->failure());
    }

    #[Test]
    public function a_whole_group_missing_in_a_language_is_red(): void
    {
        $this->write('en', 'errors', ['taken' => 'Taken']);

        $this->assertStringContainsString('ru: [errors.taken] is in en and not here', (string) $this->failure());
    }
}
