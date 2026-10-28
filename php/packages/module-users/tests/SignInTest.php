<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Admin\Visitors\Events\VisitorSignedOut;
use WebxUi\Users\Events\VisitorSignInFailed;

/**
 * The sign-in and the sign-out (spec §6.2, §6.3, §15): one sentence for every wrong pair, the
 * blocked account told so only on the right password, a session of a blocked visitor ending on
 * its next request, «Remember me», and the way back to a page of this site only.
 */
final class SignInTest extends TestCase
{
    #[Test]
    public function the_page_is_a_form_of_the_widgets_kept_out_of_every_cache(): void
    {
        $response = $this->get('/account/sign-in?back=/catalog/shoes');

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));

        $html = (string) $response->getContent();
        $this->assertStringContainsString('<title>Sign in', $html);
        $this->assertStringContainsString('class="webx-form webx-account-auth__form"', $html);
        $this->assertStringContainsString('action="http://localhost/account/sign-in"', $html);
        $this->assertMatchesRegularExpression('~<input type="email"[^>]+name="email"[^>]+autocomplete="email"~', $html);
        $this->assertMatchesRegularExpression('~<input type="password"[^>]+name="password"[^>]+autocomplete="current-password"~', $html);
        $this->assertStringContainsString('name="remember"', $html);
        $this->assertStringContainsString('name="back" value="/catalog/shoes"', $html);
        $this->assertStringContainsString('href="http://localhost/account/register?back=%2Fcatalog%2Fshoes"', $html);
        // The browser checks by the server's rules (FormRules).
        $this->assertStringContainsString('data-webx-rules', $html);
    }

    #[Test]
    public function the_right_pair_signs_in_and_goes_back_to_the_page_it_came_from(): void
    {
        Event::fake([VisitorSignedIn::class]);
        $anna = $this->visitor(['email' => 'anna@example.test']);

        $this->post('/account/sign-in', ['email' => '  Anna@Example.TEST ', 'password' => self::PASSWORD, 'back' => '/catalog/shoes?page=2'])
            ->assertRedirect('/catalog/shoes?page=2');

        $this->assertTrue(Auth::guard('site')->check());
        $this->assertSame($anna->id, Auth::guard('site')->id());
        // The site's default guard is not a visitor.
        $this->assertFalse(Auth::guard('web')->check());
        $anna->refresh();
        $this->assertNotNull($anna->last_sign_in_at);
        $this->assertSame('en', $anna->locale);
        Event::assertDispatched(VisitorSignedIn::class, static fn (VisitorSignedIn $event): bool => $event->visitorId === $anna->id && $event->method === 'password');
    }

    #[Test]
    public function an_address_of_another_site_is_no_way_back(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        foreach (['https://evil.test/x', '//evil.test/x', '/\\evil.test', 'javascript:alert(1)'] as $back) {
            Auth::guard('site')->logout();

            $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'back' => $back])
                ->assertRedirect('http://localhost');
        }

        // An address of this very site is a path of it.
        Auth::guard('site')->logout();
        $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'back' => 'http://localhost/cart'])
            ->assertRedirect('/cart');
    }

    #[Test]
    public function the_form_sent_in_place_gets_the_address_to_go_to(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        $this->postJson('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJson(['ok' => true, 'redirect' => 'http://localhost']);
    }

    #[Test]
    public function every_wrong_pair_is_one_sentence(): void
    {
        Event::fake([VisitorSignInFailed::class]);
        $this->visitor(['email' => 'anna@example.test']);
        $this->visitor(['email' => 'nopass@example.test', 'password' => null]);

        $answers = [];

        foreach ([
            ['email' => 'anna@example.test', 'password' => 'wrong-password'],
            ['email' => 'nobody@example.test', 'password' => self::PASSWORD],
            ['email' => 'nopass@example.test', 'password' => self::PASSWORD],
        ] as $pair) {
            $answers[] = $this->postJson('/account/sign-in', $pair)->assertStatus(422)->json('errors');
        }

        $this->assertSame([['email' => ['Wrong e-mail or password.']]], array_values(array_unique($answers, SORT_REGULAR)));
        $this->assertFalse(Auth::guard('site')->check());
        Event::assertDispatched(VisitorSignInFailed::class, static fn (VisitorSignInFailed $event): bool => $event->emailHash === hash('sha256', 'nobody@example.test'));
    }

    #[Test]
    public function a_blocked_account_is_told_so_only_on_the_right_password(): void
    {
        $this->visitor(['email' => 'anna@example.test'])->forceFill(['blocked_at' => now()])->save();

        $this->postJson('/account/sign-in', ['email' => 'anna@example.test', 'password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Wrong e-mail or password.');

        $this->postJson('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'This account is blocked. Please contact the shop.');

        $this->assertFalse(Auth::guard('site')->check());
    }

    #[Test]
    public function a_session_open_when_the_block_is_set_ends_on_its_next_request(): void
    {
        $anna = $this->visitor();

        $this->actingAs($anna, 'site');
        $this->get('/account/sign-in')->assertRedirect('http://localhost');

        $anna->forceFill(['blocked_at' => now()])->save();
        // A new request reads the visitor afresh from the session: the provider answers nobody.
        Auth::forgetGuards();
        $this->withSession(['login_site_'.sha1('Illuminate\Auth\SessionGuard') => $anna->id]);

        $this->get('/account/sign-in')->assertOk();
        $this->assertFalse(Auth::guard('site')->check());
    }

    #[Test]
    public function remember_me_queues_the_cookie_of_the_guard_only_when_ticked(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        $without = $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD]);
        $this->assertNull(self::rememberCookie($without->headers->getCookies()));

        Auth::guard('site')->logout();

        $with = $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'remember' => '1']);
        $this->assertNotNull(self::rememberCookie($with->headers->getCookies()));
    }

    #[Test]
    public function five_wrong_passwords_a_minute_and_the_sixth_waits(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/account/sign-in', ['email' => 'anna@example.test', 'password' => 'wrong-'.$attempt])->assertStatus(422);
        }

        // Even the right one: the limit is on the pair, whatever comes next.
        $this->postJson('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD])
            ->assertStatus(429)
            ->assertJsonPath('errors.email.0', fn (string $message): bool => preg_match('/^Too many attempts\. Please wait \d+ s\.$/', $message) === 1);

        // Another address from the same IP is its own pair.
        $this->visitor(['email' => 'boris@example.test']);
        $this->postJson('/account/sign-in', ['email' => 'boris@example.test', 'password' => self::PASSWORD])->assertOk();
    }

    #[Test]
    public function thirty_failures_from_one_ip_stop_every_address(): void
    {
        config(['webx-users.throttle.sign_in_ip' => 3]);

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/account/sign-in', ['email' => "guess-{$attempt}@example.test", 'password' => 'x'])->assertStatus(422);
        }

        $this->postJson('/account/sign-in', ['email' => 'guess-9@example.test', 'password' => 'x'])->assertStatus(429);
    }

    #[Test]
    public function the_plain_form_comes_back_with_the_error_under_its_field(): void
    {
        $this->from('/account/sign-in')
            ->post('/account/sign-in', ['email' => 'nobody@example.test', 'password' => 'x'])
            ->assertRedirect('/account/sign-in')
            ->assertSessionHasErrors(['email' => 'Wrong e-mail or password.']);
    }

    #[Test]
    public function signing_out_leaves_and_says_so(): void
    {
        Event::fake([VisitorSignedOut::class]);
        $anna = $this->visitor();
        $this->actingAs($anna, 'site');

        $this->post('/account/sign-out')->assertRedirect('http://localhost');

        $this->assertFalse(Auth::guard('site')->check());
        Event::assertDispatched(VisitorSignedOut::class, static fn (VisitorSignedOut $event): bool => $event->visitorId === $anna->id);
    }

    #[Test]
    public function the_pages_have_the_language_in_their_address(): void
    {
        $this->get('/pl/account/sign-in')
            ->assertOk()
            ->assertSee('action="http://localhost/pl/account/sign-in"', false)
            ->assertSee('<html lang="pl">', false);

        // The default language has one spelling, and a language the site lacks is no page.
        $this->get('/en/account/sign-in')->assertRedirect('http://localhost/account/sign-in');
        $this->get('/xx/account/sign-in')->assertNotFound();
    }

    /** @param  list<Cookie>  $cookies */
    private static function rememberCookie(array $cookies): ?string
    {
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_site_')) {
                return (string) $cookie->getValue();
            }
        }

        return null;
    }
}
