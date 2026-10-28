<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Visitors\Events\VisitorRegistered;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Users\Fields\UserFields;
use WebxUi\Users\Mail\ProviderLinked;
use WebxUi\Users\Mail\VerifyEmail;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\ProviderSettings;
use WebxUi\Users\Models\User;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialProfile;
use WebxUi\Users\Social\SocialProviders;
use WebxUi\Users\Social\SocialSession;
use WebxUi\Users\Tests\Fixtures\FakeProvider;

/**
 * Signing in through a social network (spec §9) with two made-up networks: `trusted`, whose
 * «verified» the registry takes at its word (as Google's), and `plain`, whose it does not (as
 * Facebook's). Every row of §9.7, the last way to sign in, and secrets that never come out.
 */
final class SocialTest extends TestCase
{
    private const string SECRET = 'top-secret-value-4815162342';

    protected function setUp(): void
    {
        parent::setUp();

        FakeProvider::$next = [];
        FakeProvider::$config = [];

        $providers = $this->app->make(SocialProviders::class);
        $providers->register(new FakeProvider('trusted', true));
        $providers->register(new FakeProvider('plain', false));

        $this->keys('trusted');
        $this->keys('plain');
    }

    #[Test]
    public function a_provider_without_its_keys_is_not_shown(): void
    {
        ProviderSettings::query()->delete();
        $this->forgetKeys();

        // Google and Facebook are registered and switched off: no button, no route.
        $this->get('/account/sign-in')->assertOk()->assertDontSee('webx-users-social', false);
        $this->get('/account/auth/google')->assertNotFound();
        $this->get('/account/auth/trusted')->assertNotFound();

        // On, but the secret is missing: still nothing, and the settings say what is missing.
        ProviderSettings::query()->create(['key' => 'google', 'enabled' => true, 'options' => ['client_id' => 'id-1']]);
        $this->forgetKeys();
        $google = $this->app->make(SocialProviders::class)->find('google');
        $this->assertNotNull($google);
        $this->assertSame(['client_secret'], $this->app->make(ProviderKeys::class)->missing($google));
        $this->get('/account/auth/google')->assertNotFound();

        // `.env` fills the empty field: the panel's id, the secret of the fallback.
        config()->set('webx-users.social.keys.google.client_secret', 'from-env');
        $this->forgetKeys();
        $this->get('/account/sign-in')->assertSee('webx-users-social__button--google', false)->assertSee('Continue with Google');
        $this->get('/account/auth/google')->assertRedirectContains('accounts.google.com');

        // Switched off in the panel beats switched on in `.env`.
        config()->set('webx-users.social.keys.google.enabled', true);
        ProviderSettings::query()->whereKey('google')->update(['enabled' => false]);
        $this->forgetKeys();
        $this->get('/account/auth/google')->assertNotFound();

        // No row in the panel at all: `.env` alone switches it on.
        ProviderSettings::query()->whereKey('google')->delete();
        config()->set('webx-users.social.keys.google.client_id', 'env-id');
        $this->forgetKeys();
        $this->get('/account/auth/google')->assertRedirectContains('client_id=env-id');
    }

    #[Test]
    public function google_is_sent_off_with_the_modules_state_and_without_its_secret(): void
    {
        ProviderSettings::query()->create(['key' => 'google', 'enabled' => true, 'options' => ['client_id' => 'google-id'], 'credentials' => ['client_secret' => self::SECRET]]);
        ProviderSettings::query()->create(['key' => 'facebook', 'enabled' => true, 'options' => ['app_id' => 'facebook-id'], 'credentials' => ['app_secret' => self::SECRET]]);
        $this->forgetKeys();

        $location = (string) $this->get('/account/auth/google')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertSame('google-id', $query['client_id']);
        $this->assertSame('http://localhost/account/auth/google/callback', $query['redirect_uri']);
        $this->assertSame(session(SocialSession::TRIP)['state'], $query['state']);
        $this->assertStringNotContainsString(self::SECRET, $location);

        $facebook = (string) $this->get('/account/auth/facebook')->headers->get('Location');
        $this->assertStringContainsString('client_id=facebook-id', $facebook);
        $this->assertStringNotContainsString(self::SECRET, $facebook);

        $this->assertTrue($this->app->make(SocialProviders::class)->find('google')?->trustsVerifiedEmail());
        $this->assertFalse($this->app->make(SocialProviders::class)->find('facebook')?->trustsVerifiedEmail());
    }

    #[Test]
    public function the_return_is_one_address_outside_csrf_and_needs_the_sessions_state(): void
    {
        $never = new ReflectionProperty(PreventRequestForgery::class, 'neverVerify');
        $this->assertContains('account/auth/*/callback', $never->getValue());

        $this->get('/account/auth/trusted')->assertRedirect();
        FakeProvider::$next['trusted'] = new SocialProfile('g-1', 'anna@example.test', true);

        $this->get('/account/auth/trusted/callback?code=x&state=forged')
            ->assertRedirect('/account/sign-in');
        $this->get('/account/sign-in')->assertSee('Could not sign you in. Please try again.');
        $this->assertGuest('site');
        $this->assertSame(0, User::query()->count());

        // A trip is good once: the state was taken by the failed return.
        $this->get('/account/auth/trusted/callback?code=x&state='.$this->state())->assertRedirect('/account/sign-in');
        $this->assertGuest('site');

        // The visitor cancelled at the provider.
        $this->get('/account/auth/trusted');
        $this->get('/account/auth/trusted/callback?error=access_denied&state='.$this->state())->assertRedirect('/account/sign-in');
        $this->assertGuest('site');
    }

    #[Test]
    public function a_new_person_of_a_trusted_provider_gets_a_confirmed_account(): void
    {
        Mail::fake();
        Event::fake([VisitorRegistered::class, VisitorSignedIn::class]);

        $this->trip('trusted', new SocialProfile('g-1', 'Anna@Example.test', true, 'Anna', 'Nowak'), back: '/shop/basket')
            ->assertRedirect('/shop/basket');

        $anna = User::query()->sole();
        $this->assertSame('anna@example.test', $anna->email);
        $this->assertNotNull($anna->email_verified_at);
        $this->assertNull($anna->password);
        $this->assertSame(['Anna', 'Nowak'], [$anna->first_name, $anna->last_name]);
        $this->assertSame((int) $anna->getKey(), (int) Auth::guard('site')->id());
        $this->assertSame(['trusted'], $anna->identities()->pluck('provider')->all());

        Event::assertDispatched(VisitorRegistered::class, static fn (VisitorRegistered $event): bool => $event->method === 'trusted');
        Event::assertDispatched(VisitorSignedIn::class, static fn (VisitorSignedIn $event): bool => $event->method === 'registration');
        Mail::assertNotQueued(VerifyEmail::class);
        Mail::assertNotQueued(ProviderLinked::class);
    }

    #[Test]
    public function a_new_person_of_a_provider_not_trusted_confirms_the_address_by_letter(): void
    {
        Mail::fake();

        $this->trip('plain', new SocialProfile('f-1', 'anna@example.test', true))->assertRedirect('/');

        $anna = User::query()->sole();
        $this->assertNull($anna->email_verified_at, 'Facebook is not taken at its word.');
        Mail::assertQueued(VerifyEmail::class, static fn (VerifyEmail $mail): bool => $mail->hasTo('anna@example.test'));
    }

    #[Test]
    public function a_linked_person_signs_in(): void
    {
        Event::fake([VisitorSignedIn::class]);
        $anna = $this->confirmed();
        Identity::query()->create(['user_id' => $anna->getKey(), 'provider' => 'plain', 'provider_user_id' => 'f-1', 'last_used_at' => now()->subYear()]);

        // Another address at the provider now: the id is what counts.
        $this->trip('plain', new SocialProfile('f-1', 'somebody@example.test', false))->assertRedirect('/');

        $this->assertSame((int) $anna->getKey(), (int) Auth::guard('site')->id());
        $this->assertTrue(Identity::query()->sole()->last_used_at?->isToday());
        Event::assertDispatched(VisitorSignedIn::class, static fn (VisitorSignedIn $event): bool => $event->method === 'plain');
    }

    #[Test]
    public function a_confirmed_account_is_linked_by_a_trusted_verified_address(): void
    {
        Mail::fake();
        $anna = $this->confirmed();

        $this->trip('trusted', new SocialProfile('g-1', $anna->email, true))->assertRedirect('/');

        $this->assertSame((int) $anna->getKey(), (int) Auth::guard('site')->id());
        $this->assertSame(['trusted'], $anna->identities()->pluck('provider')->all());
        $this->assertNotNull($anna->refresh()->password, 'The password stays.');
        Mail::assertQueued(ProviderLinked::class, static fn (ProviderLinked $mail): bool => $mail->hasTo($anna->email) && $mail->provider === 'Trusted');
    }

    /**
     * The three other rows of §9.7: an address the provider does not vouch for, one of a provider
     * not trusted, and an account whose own address is not confirmed (decision 23) — nothing is
     * linked by itself; the account's password links it.
     *
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function notVouchedFor(): iterable
    {
        yield 'confirmed, trusted, not verified' => ['trusted', false, true];
        yield 'confirmed, not trusted, verified' => ['plain', true, true];
        yield 'unconfirmed, not trusted, not verified' => ['plain', false, false];
        yield 'unconfirmed, trusted, verified' => ['trusted', true, false];
    }

    #[Test]
    #[DataProvider('notVouchedFor')]
    public function an_address_not_vouched_for_is_linked_by_the_password(string $provider, bool $verified, bool $confirmed): void
    {
        Mail::fake();
        $anna = $confirmed ? $this->confirmed() : $this->visitor();

        $this->trip($provider, new SocialProfile('p-1', $anna->email, $verified))->assertRedirect('/account/sign-in');

        $this->assertGuest('site');
        $this->assertSame(0, Identity::query()->count());
        $this->get('/account/sign-in')->assertSee('An account with this address exists already. Sign in with your password', false);
        Mail::assertNotQueued(ProviderLinked::class);

        $this->postJson('/account/sign-in', ['email' => $anna->email, 'password' => self::PASSWORD])->assertOk();

        $this->assertSame([$provider], $anna->identities()->pluck('provider')->all());
        Mail::assertQueued(ProviderLinked::class, static fn (ProviderLinked $mail): bool => $mail->hasTo($anna->email));
        $this->assertNull(session(SocialSession::PENDING));
    }

    #[Test]
    public function the_waiting_network_is_not_linked_to_another_account_or_later(): void
    {
        $anna = $this->confirmed();
        $boris = $this->visitor();

        $this->trip('plain', new SocialProfile('f-1', $anna->email, false));
        $this->postJson('/account/sign-in', ['email' => $boris->email, 'password' => self::PASSWORD])->assertOk();

        $this->assertSame(0, Identity::query()->count(), 'Another account signed in.');

        Auth::guard('site')->logout();
        $this->trip('plain', new SocialProfile('f-1', $anna->email, false));
        $this->travel(SocialSession::MINUTES + 1)->minutes();
        $this->postJson('/account/sign-in', ['email' => $anna->email, 'password' => self::PASSWORD])->assertOk();

        $this->assertSame(0, Identity::query()->count(), 'Fifteen minutes are over.');
    }

    #[Test]
    public function a_blocked_account_is_neither_linked_nor_let_in(): void
    {
        $anna = $this->confirmed();
        $anna->forceFill(['blocked_at' => now()])->save();

        $this->trip('trusted', new SocialProfile('g-1', $anna->email, true))->assertRedirect('/account/sign-in');
        $this->get('/account/sign-in')->assertSee('This account is blocked.');
        $this->assertGuest('site');
        $this->assertSame(0, Identity::query()->count());

        Identity::query()->create(['user_id' => $anna->getKey(), 'provider' => 'plain', 'provider_user_id' => 'f-1']);
        $this->trip('plain', new SocialProfile('f-1', null, false))->assertRedirect('/account/sign-in');
        $this->assertGuest('site');
    }

    #[Test]
    public function without_an_address_the_visitor_finishes_on_almost_done(): void
    {
        Mail::fake();

        $this->trip('trusted', new SocialProfile('g-1', null, false, 'Anna'))->assertRedirect('/account/auth/complete');
        $this->assertSame(0, User::query()->count(), 'No account before the page is sent.');

        $this->get('/account/auth/complete')->assertOk()
            ->assertSee('Almost done')
            ->assertSee('name="email"', false)
            ->assertDontSee('name="first_name"', false);

        $this->postJson('/account/auth/complete', ['email' => 'not an address'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/account/auth/complete', ['email' => 'Anna@Example.test'])->assertOk();

        $anna = User::query()->sole();
        $this->assertSame(['anna@example.test', 'Anna'], [$anna->email, $anna->first_name]);
        $this->assertNull($anna->email_verified_at, 'An address typed by hand is not confirmed.');
        $this->assertSame(['trusted'], $anna->identities()->pluck('provider')->all());
        $this->assertSame((int) $anna->getKey(), (int) Auth::guard('site')->id());
        Mail::assertQueued(VerifyEmail::class);
        $this->assertNull(session(SocialSession::PENDING));
    }

    #[Test]
    public function almost_done_asks_for_what_the_settings_require(): void
    {
        config()->set('webx-users.fields', ['club' => ['type' => 'wx-input', 'label' => 'Club', 'required' => true, 'placements' => ['registration']]]);
        $this->app->forgetInstance(UserFields::class);
        DB::table('users')->delete();

        $this->trip('trusted', new SocialProfile('g-1', 'anna@example.test', true))->assertRedirect('/account/auth/complete');
        $this->get('/account/auth/complete')->assertSee('name="fields[club]"', false)->assertDontSee('name="email"', false);

        $this->postJson('/account/auth/complete', [])->assertStatus(422)->assertJsonValidationErrors('fields.club');
        $this->postJson('/account/auth/complete', ['fields' => ['club' => 'gold']])->assertOk();

        $anna = User::query()->sole();
        $this->assertSame('gold', $anna->extra('club'));
        $this->assertNotNull($anna->email_verified_at, 'The address is the trusted provider\'s.');
    }

    #[Test]
    public function an_address_typed_on_almost_done_that_is_taken_asks_for_its_password(): void
    {
        $anna = $this->confirmed();

        $this->trip('trusted', new SocialProfile('g-1', null, false));
        $this->postJson('/account/auth/complete', ['email' => $anna->email])->assertOk()->assertJsonPath('redirect', 'http://localhost/account/sign-in');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Identity::query()->count());

        $this->postJson('/account/sign-in', ['email' => $anna->email, 'password' => self::PASSWORD])->assertOk();
        $this->assertSame(['trusted'], $anna->identities()->pluck('provider')->all());
    }

    #[Test]
    public function closed_registration_makes_no_account_through_a_network(): void
    {
        config()->set('webx-users.registration', 'closed');

        $this->trip('trusted', new SocialProfile('g-1', 'anna@example.test', true))->assertRedirect('/account/sign-in');

        $this->assertSame(0, User::query()->count());
        $this->get('/account/sign-in')->assertSee('No account uses this Trusted profile, and registration is closed.');
        $this->get('/account/auth/complete')->assertNotFound();
    }

    #[Test]
    public function the_account_links_a_network_and_refuses_one_of_somebody_else(): void
    {
        Mail::fake();
        $anna = $this->visitor();
        $boris = $this->visitor();
        Identity::query()->create(['user_id' => $boris->getKey(), 'provider' => 'plain', 'provider_user_id' => 'f-boris']);

        $this->get('/account/auth/trusted?link=1')->assertRedirect('/account/sign-in?back=%2Faccount%2Fsecurity');

        $this->actingAs($anna, 'site');
        $this->get('/account/security')->assertOk()
            ->assertSee('Social networks')
            ->assertSee('href="http://localhost/account/auth/trusted?link=1"', false);

        $this->trip('trusted', new SocialProfile('g-anna', 'anna.other@example.test', false), link: true)->assertRedirect('/account/security');
        $this->get('/account/security')->assertSee('Sign-in with Trusted is linked.')->assertSee('anna.other@example.test');
        $this->assertSame(['trusted'], $anna->identities()->pluck('provider')->all());
        Mail::assertQueued(ProviderLinked::class, static fn (ProviderLinked $mail): bool => $mail->hasTo($anna->email));

        $this->trip('plain', new SocialProfile('f-boris', null, false), link: true)->assertRedirect('/account/security');
        $this->get('/account/security')->assertSee('This Plain account is already linked to another profile.');
        $this->assertSame(['plain'], $boris->identities()->pluck('provider')->all());
        $this->assertSame(['trusted'], $anna->identities()->pluck('provider')->all());
    }

    #[Test]
    public function the_last_way_to_sign_in_is_not_unlinked(): void
    {
        // Made by a network: no password, one link.
        $this->trip('trusted', new SocialProfile('g-1', 'anna@example.test', true));
        $anna = User::query()->sole();

        $this->get('/account/security')->assertOk()
            ->assertSee('Set a password to unlink the last way to sign in.')
            ->assertSee('disabled', false);
        $this->postJson('/account/social/trusted/unlink')->assertStatus(422)
            ->assertJsonPath('errors.provider.0', 'Set a password to unlink the last way to sign in.');
        $this->assertSame(1, Identity::query()->count());

        // A second network is a second way: the first one goes.
        Identity::query()->create(['user_id' => $anna->getKey(), 'provider' => 'plain', 'provider_user_id' => 'f-1']);
        $this->postJson('/account/social/trusted/unlink')->assertOk()->assertJsonPath('redirect', 'http://localhost/account/security');
        $this->assertSame(['plain'], $anna->identities()->pluck('provider')->all());

        // A network switched off is no way in: it does not save the last one.
        Identity::query()->create(['user_id' => $anna->getKey(), 'provider' => 'trusted', 'provider_user_id' => 'g-1']);
        ProviderSettings::query()->whereKey('trusted')->update(['enabled' => false]);
        $this->forgetKeys();
        $this->postJson('/account/social/plain/unlink')->assertStatus(422);

        // A password is a way.
        $anna->forceFill(['password' => self::PASSWORD])->save();
        // A new password ends the session (its stamp): in again with it.
        Auth::guard('site')->logout();
        $this->postJson('/account/sign-in', ['email' => $anna->email, 'password' => self::PASSWORD])->assertOk();
        $this->postJson('/account/social/plain/unlink')->assertOk();
        $this->postJson('/account/social/plain/unlink')->assertStatus(422)->assertJsonPath('errors.provider.0', 'This network is not linked.');
    }

    #[Test]
    public function a_secret_never_comes_out(): void
    {
        Mail::fake();

        $pages = [];
        $pages[] = $this->get('/account/sign-in');
        $pages[] = $this->get('/account/register');
        $pages[] = $this->get('/account/auth/trusted');
        $this->assertSame(self::SECRET, FakeProvider::$config['trusted'], 'The provider itself gets it.');
        $pages[] = $this->trip('trusted', new SocialProfile('g-1', null, false));
        $pages[] = $this->get('/account/auth/complete');
        $pages[] = $this->postJson('/account/auth/complete', ['email' => 'anna@example.test']);
        $pages[] = $this->get('/account/security');
        $pages[] = $this->get('/account/state');
        $pages[] = $this->postJson('/account/social/trusted/unlink');

        foreach ($pages as $page) {
            $this->assertStringNotContainsString(self::SECRET, (string) $page->getContent());
            $this->assertStringNotContainsString(self::SECRET, (string) $page->headers->get('Location'));
        }

        $row = ProviderSettings::query()->findOrFail('trusted');
        $this->assertStringNotContainsString(self::SECRET, $row->toJson());
        $this->assertStringNotContainsString(self::SECRET, (string) DB::table('users_providers')->where('key', 'trusted')->value('credentials'), 'Encrypted at rest.');
        $this->assertStringNotContainsString('g-1', User::query()->sole()->identities()->sole()->toJson(), 'Nor the id at the provider.');
    }

    #[Test]
    public function trips_to_a_network_are_limited_per_ip(): void
    {
        config()->set('webx-users.throttle.social', 2);

        $this->get('/account/auth/trusted')->assertRedirect();
        $this->get('/account/auth/plain')->assertRedirect();
        $this->get('/account/auth/trusted')->assertStatus(429)->assertHeader('Retry-After');
    }

    #[Test]
    public function the_pages_speak_the_language_the_visitor_left_in(): void
    {
        $this->get('/pl/account/auth/trusted?back=/pl/sklep')->assertRedirect();
        FakeProvider::$next['trusted'] = new SocialProfile('g-1', null, false);

        $this->get('/account/auth/trusted/callback?code=x&state='.$this->state())
            ->assertRedirect('/pl/account/auth/complete');
    }

    private function keys(string $provider): void
    {
        ProviderSettings::query()->create([
            'key' => $provider,
            'enabled' => true,
            'options' => ['client_id' => $provider.'-id'],
            'credentials' => ['client_secret' => self::SECRET],
        ]);
    }

    private function confirmed(): User
    {
        $user = $this->visitor();
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function forgetKeys(): void
    {
        $this->app->make(ProviderKeys::class)->forget();
    }

    /**
     * There and back: the module's state from the redirect, the person from the test.
     *
     * @return TestResponse<Response>
     */
    private function trip(string $provider, SocialProfile $profile, ?string $back = null, bool $link = false): TestResponse
    {
        $query = array_filter(['back' => $back, 'link' => $link ? 1 : null]);
        $this->get('/account/auth/'.$provider.($query === [] ? '' : '?'.http_build_query($query)))->assertRedirect();
        FakeProvider::$next[$provider] = $profile;

        return $this->get('/account/auth/'.$provider.'/callback?code=x&state='.$this->state());
    }

    private function state(): string
    {
        $trip = session(SocialSession::TRIP);

        return is_array($trip) && is_string($trip['state'] ?? null) ? $trip['state'] : 'none';
    }
}
