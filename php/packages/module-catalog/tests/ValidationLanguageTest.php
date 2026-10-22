<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Themes\ThemeLines;

/**
 * A site with a theme, the way a real one is put together: `webx-ui/themes` wraps the translation
 * loader, and the validation lines of `webx-ui/localization` used to be added to a bare loader
 * only — so a site in Ukrainian refused a cover with `validation.array`.
 */
final class ValidationLanguageTest extends TestCase
{
    #[Test]
    public function with_a_theme_installed_a_refusal_in_ukrainian_is_worded_rather_than_keyed(): void
    {
        $this->assertTrue($this->wrappedByTheme(app('translation.loader')), 'The theme wraps the loader here, as on a site.');

        // The site's own language is its fallback too: there is no English to fall back to.
        app('translator')->setFallback('uk');
        app()->setLocale('uk');

        $media = $this->app->make(FieldTypes::class)->get('wx-media')?->rules(['type' => 'wx-media']) ?? [];
        $validator = Validator::make(
            ['tags' => 'one', 'cover' => 999999],
            ['tags' => ['array'], 'cover' => $media],
            [],
            ['tags' => 'Мітки', 'cover' => 'Обкладинка'],
        );

        $this->assertTrue($validator->fails());

        $messages = $validator->errors()->all();

        $this->assertSame([
            'Поле «Мітки» має бути списком.',
            'У бібліотеці немає файлу з id 999999: виберіть файл із неї.',
        ], $messages);
    }

    private function wrappedByTheme(mixed $loader): bool
    {
        // Whichever package wrapped the other, the theme's layer is in the chain.
        while (is_object($loader)) {
            if ($loader instanceof ThemeLines) {
                return true;
            }

            $loader = (fn (): mixed => $this->loader ?? null)->call($loader);
        }

        return false;
    }
}
