<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class ErrorPagesTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('/gone', fn () => abort(410));
        Route::get('/teapot', fn () => abort(418));
        Route::get('/down', fn () => abort(503));
        Route::get('/pl/missing', function () {
            app()->setLocale('pl');
            abort(404);
        });
    }

    #[Test]
    public function an_address_nothing_answers_gets_the_404_of_the_chain(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('default 404 in en', false);
    }

    #[Test]
    public function the_page_is_rendered_where_it_was_thrown_in_the_language_set_by_then(): void
    {
        $this->get('/pl/missing')->assertNotFound()->assertSee('default 404 in pl', false);
    }

    #[Test]
    public function the_status_is_looked_up_through_the_chain(): void
    {
        $this->get('/gone')->assertStatus(410)->assertSee('local 410', false);
    }

    #[Test]
    public function a_status_no_layer_has_is_left_to_laravel(): void
    {
        $this->get('/teapot')->assertStatus(418)->assertDontSee('default 404');
    }

    #[Test]
    public function json_is_left_to_laravel(): void
    {
        $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
    }

    #[Test]
    public function a_page_that_fails_to_render_falls_back_to_laravel_with_its_status(): void
    {
        $this->get('/down')->assertStatus(503)->assertDontSee('the header needs the database');
    }
}
