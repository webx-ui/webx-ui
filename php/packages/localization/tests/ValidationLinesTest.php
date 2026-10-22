<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

final class ValidationLinesTest extends TestCase
{
    /** Every language the panel is drawn in has to be able to refuse a form in that language. */
    private const LOCALES = ['en', 'ru', 'uk', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    #[Test]
    public function a_refused_form_is_worded_in_the_language_it_was_read_in(): void
    {
        $english = $this->messageFor('en');

        foreach (self::LOCALES as $locale) {
            $message = $this->messageFor($locale);

            $this->assertNotSame('', $message, "[{$locale}] said nothing.");
            $this->assertStringNotContainsString('validation.', $message, "[{$locale}] said a key.");

            if ($locale !== 'en') {
                $this->assertNotSame($english, $message, "[{$locale}] answered in English.");
            }
        }
    }

    #[Test]
    public function the_same_keys_exist_in_every_language(): void
    {
        $english = $this->lines('en');

        $this->assertNotSame([], $english);

        foreach (self::LOCALES as $locale) {
            $this->assertSame(
                array_keys($english),
                array_keys($this->lines($locale)),
                "[{$locale}] carries a different set of rules.",
            );
        }
    }

    #[Test]
    public function the_application_still_has_the_last_word(): void
    {
        app('translator')->addLines(['validation.required' => 'Ours.'], 'ru');

        $this->assertSame('Ours.', $this->messageFor('ru'));
    }

    #[Test]
    public function a_line_in_the_sites_own_lang_wins_and_the_rest_are_still_there(): void
    {
        // A path added to the loader is read after the site's `lang/`, and so used to beat it.
        $lang = sys_get_temp_dir().'/webx-lang-'.uniqid();
        (new Filesystem)->ensureDirectoryExists($lang.'/uk');
        file_put_contents($lang.'/uk/validation.php', "<?php return ['required' => 'Ours.'];");

        try {
            $this->app->useLangPath($lang);
            $this->fresh();

            $this->assertSame('Ours.', $this->messageFor('uk'));
            $this->assertSame('Поле «value» має бути списком.', $this->messageFor('uk', 'array', 'value', 1));
        } finally {
            (new Filesystem)->deleteDirectory($lang);
        }
    }

    #[Test]
    public function a_loader_another_package_wrapped_still_answers_in_every_language(): void
    {
        // `webx-ui/themes` wraps the loader. The lines used to be added to a `FileLoader` only,
        // and with a theme installed a site in Ukrainian answered with `validation.array`.
        $this->app->extend('translation.loader', static fn (Loader $loader): Loader => new class($loader) implements Loader
        {
            public function __construct(private readonly Loader $loader) {}

            /** @return array<array-key, mixed> */
            public function load($locale, $group, $namespace = null)
            {
                return $this->loader->load($locale, $group, $namespace);
            }

            public function addNamespace($namespace, $hint)
            {
                $this->loader->addNamespace($namespace, $hint);
            }

            public function addJsonPath($path)
            {
                $this->loader->addJsonPath($path);
            }

            public function namespaces()
            {
                return $this->loader->namespaces();
            }
        });
        $this->fresh();

        // A site whose fallback is its own language has not even Laravel's English to fall to.
        $this->app['config']->set('app.fallback_locale', 'uk');
        app('translator')->setFallback('uk');

        $message = $this->messageFor('uk', 'array', 'cover', 62);

        $this->assertStringNotContainsString('validation.', $message);
        $this->assertSame('Поле «cover» має бути списком.', $message);
    }

    private function messageFor(string $locale, string $rule = 'required', string $field = 'title', mixed $value = null): string
    {
        app()->setLocale($locale);

        $data = $value === null ? [] : [$field => $value];

        return (string) Validator::make($data, [$field => $rule])->errors()->first($field);
    }

    /** The translator and the validator built again, around the loader as it is now. */
    private function fresh(): void
    {
        foreach (['translation.loader', 'translator', 'validator'] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        Validator::clearResolvedInstances();
    }

    /**
     * @return array<string, mixed>
     */
    private function lines(string $locale): array
    {
        /** @var array<string, mixed> $lines */
        $lines = require __DIR__."/../lang/{$locale}/validation.php";

        return $lines;
    }
}
