<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Users\Http\TrackVisitor;
use WebxUi\Users\Mail\PasswordChanged;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;

/**
 * «Forgot your password?» (spec §7, §15): one answer for every address, a link that works once
 * and for an hour, the other sessions ending with the new password, and the reset that proves
 * the mailbox — confirming the address and unlinking the networks of an unconfirmed account.
 */
final class PasswordResetTest extends TestCase
{
    private const string NEW = 'a-brand-new-password';

    #[Test]
    public function the_request_answers_the_same_whoever_asks_and_writes_only_to_an_account(): void
    {
        Mail::fake();
        $this->visitor(['email' => 'anna@example.test']);
        $this->visitor(['email' => 'blocked@example.test'])->forceFill(['blocked_at' => now()])->save();

        $answers = [];

        foreach (['anna@example.test', 'nobody@example.test', 'blocked@example.test'] as $email) {
            $answers[] = $this->postJson('/account/forgot', ['email' => $email])->assertOk()->json();
        }

        $this->assertSame($answers[0], $answers[1]);
        $this->assertSame($answers[0], $answers[2]);
        $this->assertSame('If there is an account with this address, we have sent a link to it.', $answers[0]['heading']);

        Mail::assertQueued(ResetPassword::class, 1);
        Mail::assertQueued(ResetPassword::class, static fn (ResetPassword $mail): bool => $mail->hasTo('anna@example.test')
            && str_starts_with($mail->link, 'http://localhost/account/reset/'));
    }

    #[Test]
    public function over_the_limit_no_letter_leaves_and_the_answer_stays_the_same(): void
    {
        Mail::fake();
        $this->visitor(['email' => 'anna@example.test']);

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/account/forgot', ['email' => 'Anna@example.test'])
                ->assertOk()
                ->assertJsonPath('heading', 'If there is an account with this address, we have sent a link to it.');
        }

        Mail::assertQueued(ResetPassword::class, 3);
    }

    #[Test]
    public function the_link_sets_a_new_password_once_and_signs_in(): void
    {
        Mail::fake();
        Event::fake([VisitorSignedIn::class]);
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $anna->forceFill(['email_verified_at' => now()])->save();
        $anna->forceFill(['remember_token' => 'old-remember-token'])->save();
        $path = $this->linkFor('anna@example.test');

        $page = $this->get($path)->assertOk();
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('~<input type="password"[^>]+name="password"[^>]+autocomplete="new-password"~', (string) $page->getContent());
        $this->assertStringContainsString('name="password_confirmation"', (string) $page->getContent());

        $this->postJson($path, ['password' => self::NEW, 'password_confirmation' => 'something-else'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->postJson($path, ['password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertOk()
            ->assertJsonPath('redirect', 'http://localhost');

        $anna->refresh();
        $this->assertTrue(Hash::check(self::NEW, (string) $anna->password));
        $this->assertNotSame('old-remember-token', $anna->getRememberToken());
        $this->assertSame($anna->id, Auth::guard('site')->id());
        $this->assertSame(0, DB::table(ResetLinks::TABLE)->count());
        Event::assertDispatched(VisitorSignedIn::class, static fn (VisitorSignedIn $event): bool => $event->method === 'reset');
        Mail::assertQueued(PasswordChanged::class, static fn (PasswordChanged $mail): bool => $mail->hasTo('anna@example.test'));

        // Once: the same link is dead now, on the page and on the form.
        Auth::guard('site')->logout();
        $this->get($path)->assertOk()->assertSee('The link is out of date.');
        $this->postJson($path, ['password' => 'yet-another-one', 'password_confirmation' => 'yet-another-one'])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'The link is out of date. Ask for a new one below.');
        $this->assertTrue(Hash::check(self::NEW, (string) $anna->refresh()->password));
    }

    #[Test]
    public function the_link_lives_an_hour_and_a_new_one_replaces_it(): void
    {
        Mail::fake();
        $this->visitor(['email' => 'anna@example.test']);

        $first = $this->linkFor('anna@example.test');
        $second = $this->linkFor('anna@example.test', 2);

        $this->get($first)->assertSee('The link is out of date.');
        $this->get($second)->assertDontSee('The link is out of date.')->assertSee('name="password_confirmation"', false);

        $this->travel(61)->minutes();
        $this->get($second)->assertSee('The link is out of date.');
        $this->assertSame(1, DB::table(ResetLinks::TABLE)->count(), 'The row stays until webx:users:prune.');
    }

    #[Test]
    public function the_token_is_kept_only_as_its_hash(): void
    {
        Mail::fake();
        $this->visitor(['email' => 'anna@example.test']);
        $token = basename($this->linkFor('anna@example.test'));

        $row = DB::table(ResetLinks::TABLE)->first();
        $this->assertNotNull($row);
        $this->assertSame(hash('sha256', $token), $row->token_hash);
        $this->assertSame(43, strlen($token), '32 random bytes in base64url.');
    }

    #[Test]
    public function the_reset_of_an_unconfirmed_account_confirms_it_and_unlinks_its_networks(): void
    {
        Mail::fake();
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $boris = $this->visitor(['email' => 'boris@example.test']);
        $boris->forceFill(['email_verified_at' => now()])->save();
        self::link($anna, 'google', 'stranger-1');
        self::link($boris, 'google', 'boris-1');

        foreach (['anna@example.test', 'boris@example.test'] as $email) {
            $path = $this->linkFor($email);
            $this->post($path, ['password' => self::NEW, 'password_confirmation' => self::NEW])->assertRedirect();
            Auth::guard('site')->logout();
        }

        $this->assertNotNull($anna->refresh()->email_verified_at);
        $this->assertSame(0, DB::table('users_identities')->where('user_id', $anna->id)->count(), 'Whoever linked their network to an address not theirs loses it.');
        $this->assertSame(1, DB::table('users_identities')->where('user_id', $boris->id)->count(), 'A confirmed account keeps its networks.');
    }

    #[Test]
    public function a_blocked_account_is_not_let_in_by_a_link(): void
    {
        Mail::fake();
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $path = $this->linkFor('anna@example.test');
        $anna->forceFill(['blocked_at' => now()])->save();

        $this->get($path)->assertSee('The link is out of date.');
        $this->post($path, ['password' => self::NEW, 'password_confirmation' => self::NEW])->assertSessionHasErrors('password');
        $this->assertFalse(Auth::guard('site')->check());
    }

    #[Test]
    public function a_session_opened_with_the_old_password_ends_on_its_next_request(): void
    {
        $anna = $this->visitor();
        $guard = 'login_site_'.sha1('Illuminate\Auth\SessionGuard');
        $old = TrackVisitor::stamp($anna);

        // Another device: signed in, with the stamp of the password it signed in with.
        $this->withSession([$guard => $anna->id, TrackVisitor::STAMP => $old]);
        $this->get('/account/sign-in')->assertRedirect('http://localhost');

        // The password changes elsewhere — the reset of §7 on the visitor's phone.
        $anna->forceFill(['password' => self::NEW])->save();
        Auth::forgetGuards();
        $this->withSession([$guard => $anna->id, TrackVisitor::STAMP => $old]);

        $this->get('/account/sign-in')->assertOk();
        $this->assertFalse(Auth::guard('site')->check());
    }

    #[Test]
    public function new_passwords_from_one_address_are_limited(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->postJson('/account/reset/not-a-token', ['password' => self::NEW, 'password_confirmation' => self::NEW])->assertStatus(422);
        }

        $this->postJson('/account/reset/not-a-token', ['password' => self::NEW, 'password_confirmation' => self::NEW])->assertStatus(429);
    }

    #[Test]
    public function the_sign_in_leads_to_the_request(): void
    {
        $this->get('/account/sign-in')->assertSee('href="http://localhost/account/forgot"', false)->assertSee('Forgot your password?');
        $this->get('/pl/account/forgot')->assertOk()->assertSee('action="http://localhost/pl/account/forgot"', false);
    }

    /** Ask for a link and give back its path: the letter is the only place it is written. */
    private function linkFor(string $email, int $nth = 1): string
    {
        $this->postJson('/account/forgot', ['email' => $email])->assertOk();

        $letters = Mail::queued(ResetPassword::class, static fn (ResetPassword $mail): bool => $mail->hasTo($email))->values();
        $this->assertGreaterThanOrEqual($nth, $letters->count());

        /** @var ResetPassword $letter */
        $letter = $letters[$nth - 1];

        return (string) parse_url($letter->link, PHP_URL_PATH);
    }

    private static function link(User $user, string $provider, string $id): void
    {
        DB::table('users_identities')->insert([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $id,
            'created_at' => now(),
        ]);
    }
}
