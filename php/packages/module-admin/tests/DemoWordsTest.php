<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoWords;
use WebxUi\Localization\Locales;

/**
 * A demo document's words, one string or a map of languages, cut to the languages of the site.
 */
final class DemoWordsTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The demo's pictures go in as they are: what the pipeline makes of an upload is the media
        // module's test, and turning every demo photo into webp was a third of this one.
        $app['config']->set('webx-media.optimize.enabled', false);
    }

    private function words(string ...$codes): DemoWords
    {
        $this->app['config']->set('webx-localization.locales', array_map(
            static fn (string $code, int $index): array => ['code' => $code, 'default' => $index === 0],
            $codes,
            array_keys($codes),
        ));

        $locales = $this->app->make(Locales::class);
        $locales->forget();

        return new DemoWords($locales);
    }

    #[Test]
    public function one_string_is_the_default_language(): void
    {
        $words = $this->words('pl', 'en');

        $this->assertSame(['pl' => 'Hello'], $words->of(' Hello '));
        $this->assertNull($words->of(''));
        $this->assertNull($words->of(null));
    }

    #[Test]
    public function a_map_keeps_the_languages_of_the_site_and_fills_in_its_default(): void
    {
        $this->assertSame(
            ['en' => 'Hello', 'pl' => 'Cześć'],
            $this->words('en', 'pl')->of(['en' => 'Hello', 'ru' => 'Привет', 'pl' => 'Cześć']),
        );

        // German first and not in the document: its English stands in, as one string would.
        $this->assertSame(
            ['de' => 'Hello', 'en' => 'Hello'],
            $this->words('de', 'en')->of(['en' => 'Hello', 'ru' => 'Привет']),
        );
    }

    #[Test]
    public function inside_a_tree_only_maps_of_languages_change(): void
    {
        $tree = [[
            'key' => 'hero',
            'type' => 'hero',
            'values' => [
                'title' => ['en' => 'Hello', 'ru' => 'Привет', 'pl' => 'Cześć'],
                'button_url' => '/contacts',
                'picture' => ['path' => 'demo:photo', 'alt' => ['en' => 'A photo', 'ru' => 'Фото', 'pl' => 'Zdjęcie']],
                'link' => ['target' => 'url', 'url' => '#top', 'rel' => []],
                'rows' => [['label' => ['en' => 'years', 'ru' => 'лет', 'pl' => 'lat'], 'value' => '15']],
            ],
        ]];

        $values = $this->words('ru', 'en')->in($tree)[0]['values'];

        $this->assertSame(['en' => 'Hello', 'ru' => 'Привет'], $values['title']);
        $this->assertSame('/contacts', $values['button_url']);
        $this->assertSame(['path' => 'demo:photo', 'alt' => ['en' => 'A photo', 'ru' => 'Фото']], $values['picture']);
        $this->assertSame(['target' => 'url', 'url' => '#top', 'rel' => []], $values['link']);
        $this->assertSame([['label' => ['en' => 'years', 'ru' => 'лет'], 'value' => '15']], $values['rows']);
    }
}
