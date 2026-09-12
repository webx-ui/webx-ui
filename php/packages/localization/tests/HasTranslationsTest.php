<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Tests\Fixtures\Page;

final class HasTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->locale('en', ['is_default' => true, 'sort' => 0]);
        $this->locale('uk', ['sort' => 1]);
        $this->app->setLocale('en');
    }

    #[Test]
    public function a_value_is_written_and_read_in_the_language_of_the_request(): void
    {
        $page = new Page;
        $page->title = 'Contacts';

        $this->app->setLocale('uk');
        $page->title = 'Контакти';
        $page->save();

        $this->assertSame('Контакти', $page->refresh()->title);

        $this->app->setLocale('en');
        $this->assertSame('Contacts', $page->refresh()->title);
    }

    #[Test]
    public function writing_one_language_leaves_the_others_alone(): void
    {
        $page = Page::query()->create(['title' => ['en' => 'Contacts', 'uk' => 'Контакти']]);

        $this->app->setLocale('uk');
        $page->title = 'Контактні дані';
        $page->save();

        $this->assertSame(
            ['en' => 'Contacts', 'uk' => 'Контактні дані'],
            $page->fresh()?->getTranslations('title'),
        );
    }

    #[Test]
    public function a_missing_translation_falls_back_rather_than_showing_nothing(): void
    {
        $page = Page::query()->create(['title' => ['en' => 'Contacts']]);

        $this->app->setLocale('uk');

        $this->assertSame('Contacts', $page->title);
        // And the caller who needs to know it is missing can still find out.
        $this->assertNull($page->getTranslation('title', 'uk', fallback: false));
        $this->assertFalse($page->hasTranslation('title', 'uk'));
    }

    #[Test]
    public function an_empty_string_counts_as_missing(): void
    {
        // A form posts every language at once, so an untranslated field arrives as '' rather
        // than as absent. Treating that as a value is how a page renders a blank heading.
        $page = Page::query()->create(['title' => ['en' => 'Contacts', 'uk' => '']]);

        $this->assertSame('Contacts', $page->forLocale('uk')->title);
    }

    #[Test]
    public function reading_another_language_does_not_change_the_record_for_anybody_else(): void
    {
        $page = Page::query()->create(['title' => ['en' => 'Contacts', 'uk' => 'Контакти']]);

        $ukrainian = $page->forLocale('uk');

        $this->assertSame('Контакти', $ukrainian->title);
        $this->assertSame('Contacts', $page->title);
    }

    #[Test]
    public function the_panel_can_ask_for_every_language_at_once(): void
    {
        $page = Page::query()->create([
            'title' => ['en' => 'Contacts', 'uk' => 'Контакти'],
            'body' => ['en' => 'Write to us'],
        ]);

        $this->assertSame([
            'title' => ['en' => 'Contacts', 'uk' => 'Контакти'],
            'body' => ['en' => 'Write to us'],
        ], $page->translationsToArray());
    }

    #[Test]
    public function serialising_gives_the_site_one_language_not_all_of_them(): void
    {
        $page = Page::query()->create(['title' => ['en' => 'Contacts', 'uk' => 'Контакти']]);

        $this->app->setLocale('uk');

        $this->assertSame('Контакти', $page->toArray()['title']);
    }

    #[Test]
    public function a_column_that_used_to_hold_one_language_still_reads(): void
    {
        // Converting an existing single-language site is a migration nobody wants to have to
        // run before the code works at all.
        $id = DB::table('pages')->insertGetId(['title' => 'Contacts']);

        $page = Page::query()->findOrFail($id);

        $this->assertSame('Contacts', $page->title);

        $page->setTranslation('title', 'uk', 'Контакти');
        $page->save();

        $this->assertSame(
            ['en' => 'Contacts', 'uk' => 'Контакти'],
            $page->fresh()?->getTranslations('title'),
        );
    }

    #[Test]
    public function records_can_be_found_and_ordered_by_a_translated_value(): void
    {
        Page::query()->create(['title' => ['en' => 'Bravo', 'uk' => 'Ярмарок']]);
        Page::query()->create(['title' => ['en' => 'Alpha', 'uk' => 'Абетка']]);

        $this->assertSame(
            'Bravo',
            Page::query()->whereTranslation('title', 'Ярмарок', 'uk')->first()?->title,
        );

        $this->assertSame(
            ['Alpha', 'Bravo'],
            Page::query()->orderByTranslation('title', 'asc', 'en')->get()
                ->map(fn (Page $page): mixed => $page->getTranslation('title', 'en'))
                ->all(),
        );
    }

    #[Test]
    public function a_translation_can_be_taken_back_out(): void
    {
        $page = Page::query()->create(['title' => ['en' => 'Contacts', 'uk' => 'Контакти']]);

        $page->forgetTranslation('title', 'uk');
        $page->save();

        $this->assertSame(['en' => 'Contacts'], $page->fresh()?->getTranslations('title'));
    }

    #[Test]
    public function a_language_map_survives_a_round_trip_through_the_database(): void
    {
        $page = Page::query()->create(['title' => ['en' => 'Contacts']]);

        $stored = DB::table('pages')->where('id', $page->getKey())->value('title');

        $this->assertIsString($stored);
        $this->assertSame(['en' => 'Contacts'], json_decode($stored, true));
    }

    #[Test]
    public function an_untranslatable_column_is_left_to_eloquent(): void
    {
        $page = Page::query()->create(['title' => ['en' => 'Contacts'], 'code' => 'contacts']);

        $this->assertSame('contacts', $page->fresh()?->code);
        $this->assertSame('en', $this->app->make(Locales::class)->current());
    }
}
