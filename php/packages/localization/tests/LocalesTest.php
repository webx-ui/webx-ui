<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\LocaleCatalogue;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;

final class LocalesTest extends TestCase
{
    private function locales(): Locales
    {
        return $this->app->make(Locales::class);
    }

    #[Test]
    public function an_empty_table_answers_from_the_configured_list(): void
    {
        // The case that decides whether an installation can boot at all: code that wants a
        // language runs long before anybody has created one.
        $this->assertSame(['en', 'uk'], $this->locales()->codes());
        $this->assertSame('en', $this->locales()->defaultCode());
    }

    #[Test]
    public function the_table_wins_once_there_is_something_in_it(): void
    {
        $this->locale('uk', ['is_default' => true, 'sort' => 0]);
        $this->locale('ru', ['sort' => 1]);

        $this->assertSame(['uk', 'ru'], $this->locales()->codes());
        $this->assertSame('uk', $this->locales()->defaultCode());
    }

    #[Test]
    public function a_language_switched_off_is_not_offered_but_is_not_lost(): void
    {
        $this->locale('uk', ['is_default' => true]);
        $this->locale('ru', ['is_active' => false]);

        $this->assertSame(['uk'], $this->locales()->codes());
        $this->assertDatabaseHas('locales', ['code' => 'ru']);
    }

    #[Test]
    public function only_one_language_can_be_the_default(): void
    {
        $first = $this->locale('uk', ['is_default' => true]);
        $this->locale('ru', ['is_default' => true]);

        $this->assertFalse($first->fresh()?->is_default);
        $this->assertSame('ru', $this->locales()->defaultCode());
    }

    #[Test]
    public function switching_to_a_language_the_site_does_not_have_is_refused(): void
    {
        $this->locale('uk', ['is_default' => true]);

        $this->assertTrue($this->locales()->use('uk'));
        $this->assertSame('uk', $this->locales()->current());

        $this->assertFalse($this->locales()->use('de'));
        $this->assertSame('uk', $this->locales()->current());
    }

    #[Test]
    public function the_fallback_chain_ends_at_a_language_that_is_complete(): void
    {
        $this->locale('uk', ['is_default' => true]);
        $this->locale('ru');

        $this->assertSame(['ru', 'uk', 'en'], $this->locales()->chain('ru'));
        // No repeats: asking for the default should not walk it twice.
        $this->assertSame(['uk', 'en'], $this->locales()->chain('uk'));
    }

    #[Test]
    public function the_panel_speaks_only_the_languages_it_has_been_translated_into(): void
    {
        config()->set('webx-localization.panel', ['en', 'ru']);

        $this->assertSame(['en', 'ru'], array_column($this->locales()->panel(), 'code'));

        // A content language the interface has no words for is still not offered here.
        $this->locale('uk', ['is_default' => true]);
        $this->assertSame(['en', 'ru'], array_column($this->locales()->panel(), 'code'));
    }

    #[Test]
    public function a_regional_request_falls_back_to_the_language(): void
    {
        config()->set('webx-localization.panel', ['en', 'de']);

        $this->assertSame('de', $this->locales()->resolvePanel('de-AT'));
        $this->assertSame('en', $this->locales()->resolvePanel('fr-CA'));
        $this->assertSame('en', $this->locales()->resolvePanel(null));
    }

    #[Test]
    public function a_code_is_read_the_same_however_it_is_written(): void
    {
        $this->assertSame('ru', LocaleCatalogue::normalise('RU'));
        $this->assertSame('pt-BR', LocaleCatalogue::normalise('pt_br'));
        $this->assertSame('zh-Hans', LocaleCatalogue::normalise('zh-hans'));

        $this->locale('PT_br');
        $this->assertDatabaseHas('locales', ['code' => 'pt-BR']);
    }

    #[Test]
    public function a_language_outside_the_catalogue_is_still_allowed(): void
    {
        // The catalogue is a convenience, not a gate: a site may publish in something we have
        // never named, and refusing it would be us deciding what languages exist.
        $locale = Locale::fromCode('xx');

        $this->assertSame('xx', $locale->code);
        $this->assertSame('xx', $locale->name);
        $this->assertSame('ltr', $locale->direction);
    }

    #[Test]
    public function seeding_adds_what_is_missing_and_touches_nothing_else(): void
    {
        $this->locale('en', ['name' => 'Renamed', 'is_default' => true]);

        $this->artisan('webx:locales:seed')->assertSuccessful();

        $this->assertSame('Renamed', Locale::query()->where('code', 'en')->value('name'));
        $this->assertDatabaseHas('locales', ['code' => 'uk']);
    }
}
