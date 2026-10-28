<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Compositions;
use WebxUi\Users\Http\ScriptController;

/**
 * «Confirm your e-mail» (spec §8.3, §11.6). A page cached for everybody prints the banner hidden
 * and the same for a guest and for an unconfirmed visitor — nothing personal, no query for the
 * guest; `users.js` shows it by the cookie. A page of the module is the visitor's own and the
 * server prints it there.
 */
final class BannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A page of the site the page cache could keep: the banner where the layout's patch puts it.
        Route::middleware('web')->get('/catalog', static fn (): string => Blade::render(
            '<main>@include(\'webx-users::components.verify-banner\')<p>Shoes</p></main>@stack(\'scripts\')',
        ));
    }

    #[Test]
    public function a_cached_page_prints_it_hidden_and_the_guest_asks_nothing(): void
    {
        DB::enableQueryLog();

        $html = (string) $this->get('/catalog')->assertOk()->getContent();

        // The site's languages (the address of the form) are the site's; nothing asks for a visitor.
        $mine = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains((string) $query['query'], 'users'));
        $this->assertSame([], $mine, 'A guest on a cached page costs no query of the module.');
        $this->assertMatchesRegularExpression('~<div class="webx-users-verify"[^>]*data-webx-users-verify[^>]*hidden~', $html);
        $this->assertStringContainsString('data-webx-users-state="http://localhost/account/state"', $html);
        $this->assertStringContainsString('action="http://localhost/account/verify/resend"', $html);
        $this->assertMatchesRegularExpression('~<script type="module" src="http://localhost/webx/users/users\.js\?v=[0-9a-f]{16}"></script>~', $html);
        $this->assertStringNotContainsString('@example', $html);
    }

    #[Test]
    public function the_cached_page_is_the_same_for_the_unconfirmed_visitor(): void
    {
        $guest = self::withoutCsrf((string) $this->get('/catalog')->getContent());

        $anna = $this->visitor(['email' => 'anna@example.test']);
        $this->actingAs($anna, 'site');
        $signedIn = self::withoutCsrf((string) $this->get('/catalog')->getContent());

        $this->assertSame($guest, $signedIn, 'Nothing of the visitor in a page the cache serves to everybody.');
    }

    #[Test]
    public function a_page_of_the_module_prints_it_for_the_unconfirmed_only(): void
    {
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $this->actingAs($anna, 'site');

        // The sign-in sends a visitor who is in away; the reset form is a page of the module anyone sees.
        $html = (string) $this->get('/account/forgot')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<div class="webx-users-verify"[^>]*data-webx-users-verify[^>]*>~', $html);
        $this->assertDoesNotMatchRegularExpression('~data-webx-users-verify[^>]*hidden~', $html);
        $this->assertStringContainsString('a…@example.test', $html);

        $anna->forceFill(['email_verified_at' => now()])->save();
        $this->assertStringNotContainsString('data-webx-users-verify', (string) $this->get('/account/forgot')->getContent());
    }

    #[Test]
    public function the_state_says_who_is_looking_with_the_address_masked(): void
    {
        $this->getJson('/account/state')->assertOk()->assertExactJson(['signedIn' => false]);

        $anna = $this->visitor(['email' => 'anna@example.test', 'first_name' => 'Anna']);
        $this->actingAs($anna, 'site');

        $response = $this->getJson('/account/state')->assertOk()->assertExactJson([
            'signedIn' => true,
            'name' => 'Anna',
            'verified' => false,
            'email' => 'a…@example.test',
        ]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function the_script_is_served_once_for_good_and_small(): void
    {
        $response = $this->get('/webx/users/users.js?v='.ScriptController::version())->assertOk();

        $this->assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('webx_visitor', (string) $response->getContent());
        $this->assertLessThan(4096, strlen(ScriptController::contents()), 'users.js is a small script of every cached page.');
    }

    #[Test]
    public function the_layout_gets_the_banner_first_in_main(): void
    {
        $patch = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/compositions/layout/default.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(
            [['id' => 'users.verify-banner', 'to' => 'main', 'first' => true, 'component' => 'webx-users::verify-banner']],
            $patch['add'],
        );
        $this->assertTrue(class_exists(Compositions::class));
    }

    private static function withoutCsrf(string $html): string
    {
        return (string) preg_replace('~name="_token" value="[^"]*"~', '', $html);
    }
}
