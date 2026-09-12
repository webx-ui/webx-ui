<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class SiteLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->locale('uk', ['is_default' => true, 'sort' => 0]);
        $this->locale('ru', ['sort' => 1]);

        Route::middleware(['webx.locale'])->get('{path?}', fn (): string => app()->getLocale())
            ->where('path', '.*');
    }

    #[Test]
    public function the_first_segment_of_the_path_chooses_the_language(): void
    {
        $this->get('/ru/about')->assertOk()->assertSee('ru');
    }

    #[Test]
    public function an_address_without_a_language_is_the_default_one(): void
    {
        // Which is what most sites want: /about and /uk/about being the same page, with only
        // one of them advertised.
        $this->get('/about')->assertOk()->assertSee('uk');
    }

    #[Test]
    public function a_language_the_site_does_not_publish_in_is_not_taken_from_the_url(): void
    {
        $this->get('/de/about')->assertOk()->assertSee('uk');
    }

    #[Test]
    public function a_site_can_read_the_language_from_the_browser_instead(): void
    {
        config()->set('webx-localization.strategy', 'header');

        $this->get('/about', ['Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8'])
            ->assertOk()
            ->assertSee('ru');

        // A header naming nothing this site has still lands somewhere sensible.
        $this->get('/about', ['Accept-Language' => 'fr-FR'])->assertOk()->assertSee('uk');
    }

    #[Test]
    public function a_migration_can_say_which_columns_hold_every_language(): void
    {
        $this->assertTrue(Blueprint::hasMacro('translatable'));

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            // Through __call on purpose: a macro does not exist as far as static analysis is
            // concerned, and this is the one place that proves the macro reaches the helper.
            $table->__call('translatable', ['title', 'body']);
        });

        $this->assertTrue(Schema::hasColumns('articles', ['title', 'body']));

        Schema::dropIfExists('articles');
    }
}
