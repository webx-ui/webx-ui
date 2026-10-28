<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Users\Http\TrackVisitor;
use WebxUi\Users\Mail\VerifyEmail;
use WebxUi\Users\Models\User;

/**
 * Confirming the address (spec §8.1–§8.4): the letter after the registration, the signed link
 * that confirms without signing in, dead once the address changes or the time is up, the letter
 * once more with its limit, `webx.visitor:verified` answered by the module, and the cookie
 * `webx_visitor` that tells a cached page who is looking.
 */
final class VerificationTest extends TestCase
{
    #[Test]
    public function the_registration_sends_the_letter_and_its_link_confirms_without_signing_in(): void
    {
        Mail::fake();

        $this->post('/account/register', ['email' => 'anna@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $anna = User::query()->byEmail('anna@example.test')->firstOrFail();
        $this->assertFalse($anna->hasVerifiedEmail(), 'Signed in, not confirmed (decision 9).');

        $link = $this->letterTo('anna@example.test');
        $this->assertStringStartsWith("http://localhost/account/verify/{$anna->id}/".sha1('anna@example.test').'?expires=', $link);

        // Opened on another device: a guest there.
        Auth::guard('site')->logout();
        $page = $this->get($link)->assertOk();
        $page->assertSee('E-mail confirmed')->assertSee('href="http://localhost/account/sign-in"', false);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        $this->assertTrue($anna->refresh()->hasVerifiedEmail());
        $this->assertFalse(Auth::guard('site')->check());
    }

    #[Test]
    public function a_link_forged_out_of_date_or_of_an_older_address_is_dead(): void
    {
        Mail::fake();
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $anna->sendEmailVerificationNotification();
        $link = $this->letterTo('anna@example.test');

        $this->get(str_replace('/verify/'.$anna->id.'/', '/verify/'.($anna->id + 1).'/', $link))->assertSee('The link is out of date');
        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=0000', $link))->assertSee('The link is out of date');

        $anna->forceFill(['email' => 'anna.new@example.test'])->save();
        $this->get($link)->assertSee('The link is out of date');

        $anna->forceFill(['email' => 'anna@example.test'])->save();
        $this->travel(73)->hours();
        $this->get($link)->assertSee('The link is out of date');

        $this->assertFalse($anna->refresh()->hasVerifiedEmail());
    }

    #[Test]
    public function the_letter_once_more_is_the_signed_in_visitors_own_and_limited(): void
    {
        Mail::fake();

        $this->postJson('/account/verify/resend')->assertStatus(401);
        Mail::assertNothingQueued();

        $anna = $this->visitor(['email' => 'anna@example.test']);
        $this->actingAs($anna, 'site');

        $this->postJson('/account/verify/resend')->assertOk()->assertJsonPath('heading', 'We have sent the letter to a…@example.test once more.');
        Mail::assertQueued(VerifyEmail::class, 1);

        $this->postJson('/account/verify/resend')->assertStatus(429)->assertJsonPath('errors.resend.0', 'The letter has already been sent. Try again in 1 min.');

        foreach (range(1, 4) as $minute) {
            $this->travel(61)->seconds();
            $this->postJson('/account/verify/resend')->assertOk();
        }

        $this->travel(61)->seconds();
        $this->postJson('/account/verify/resend')->assertStatus(429);
        Mail::assertQueued(VerifyEmail::class, 5);

        $anna->forceFill(['email_verified_at' => now()])->save();
        $this->postJson('/account/verify/resend')->assertOk()->assertJsonPath('heading', 'Your e-mail is already confirmed.');
    }

    #[Test]
    public function a_page_for_the_confirmed_shows_confirm_your_email_to_the_unconfirmed(): void
    {
        Route::middleware(['web', 'webx.visitor:verified'])->get('/favorites', static fn (): string => 'the favourites');
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $this->actingAs($anna, 'site');

        $page = $this->get('/favorites')->assertForbidden();
        $page->assertSee('Confirm your e-mail')->assertSee('a…@example.test')->assertSee('action="http://localhost/account/verify/resend"', false);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->getJson('/favorites')->assertForbidden()->assertJsonPath('code', 'visitor.unverified');

        $anna->forceFill(['email_verified_at' => now()])->save();
        $this->get('/favorites')->assertOk()->assertSee('the favourites');
    }

    #[Test]
    public function the_cookie_of_the_cached_pages_follows_the_visitor(): void
    {
        Mail::fake();
        $this->visitor(['email' => 'anna@example.test']);

        $guest = $this->get('/account/sign-in');
        $this->assertNull(self::visitorCookie($guest), 'A guest gets no cookie.');

        $signedIn = $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD]);
        $cookie = self::visitorCookie($signedIn);
        $this->assertNotNull($cookie);
        $this->assertSame('u', $cookie->getValue(), 'Signed in, the address not confirmed.');
        $this->assertFalse($cookie->isHttpOnly(), 'users.js reads it.');

        // Confirmed on this very device: the page of the link says so in the cookie too.
        $this->get($this->resendAndTakeLink())->assertSee('E-mail confirmed')->assertSee('Continue');
        $this->assertSame('1', self::visitorCookie($this->withUnencryptedCookie(TrackVisitor::COOKIE, 'u')->get('/account/sign-in'))?->getValue());

        $out = $this->withUnencryptedCookie(TrackVisitor::COOKIE, '1')->post('/account/sign-out');
        $this->assertTrue(self::visitorCookie($out)?->isCleared() ?? false, 'Signed out — the cookie goes.');
    }

    #[Test]
    public function the_cookie_is_read_as_it_was_written(): void
    {
        $this->assertTrue(app(EncryptCookies::class)->isDisabled(TrackVisitor::COOKIE));
    }

    private function resendAndTakeLink(): string
    {
        $this->post('/account/verify/resend');

        return $this->letterTo('anna@example.test');
    }

    private function letterTo(string $email): string
    {
        $letters = Mail::queued(VerifyEmail::class, static fn (VerifyEmail $mail): bool => $mail->hasTo($email));
        $this->assertNotEmpty($letters, "No letter of confirmation to {$email}.");

        /** @var VerifyEmail $letter */
        $letter = $letters->last();

        return $letter->link;
    }

    /** @param  TestResponse<Response>  $response */
    private static function visitorCookie(TestResponse $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === TrackVisitor::COOKIE) {
                return $cookie;
            }
        }

        return null;
    }
}
