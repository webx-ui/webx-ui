<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Users\Http\TrackVisitor;
use WebxUi\Users\Mail\AccountDeleted;
use WebxUi\Users\Mail\PasswordChanged;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;
use WebxUi\Users\Tests\Fixtures\OrdersModule;

/**
 * «Sign-in and security» (spec §11.1, §9.6, §14.1): the password changed on the spot or set by a
 * letter, every other session ended, and the account deleted through the listeners of the modules.
 */
final class SecurityTest extends TestCase
{
    private const string NEW = 'a-new-long-password';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), OrdersModule::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        OrdersModule::$deleting = null;
        OrdersModule::$refuse = false;

        Schema::create('test_orders', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
        });
    }

    #[Test]
    public function the_password_changes_with_the_current_one_and_ends_the_other_sessions(): void
    {
        Mail::fake();
        $anna = $this->visitor();
        $other = $this->elsewhere($anna);

        $this->actingAs($anna, 'site');
        $this->get('/account/security')->assertOk()->assertSee('name="current_password"', false);

        $this->postJson('/account/password', ['current_password' => 'wrong-one', 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertStatus(422)->assertJsonPath('errors.current_password.0', 'The password is wrong.');
        $this->postJson('/account/password', ['current_password' => self::PASSWORD, 'password' => self::NEW, 'password_confirmation' => 'something-else'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/account/password', ['current_password' => self::PASSWORD, 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertOk()->assertJsonPath('heading', 'The password is changed. Other devices are signed out.');

        $this->assertTrue(Hash::check(self::NEW, (string) $anna->refresh()->password));
        Mail::assertQueued(PasswordChanged::class, static fn (PasswordChanged $mail): bool => $mail->hasTo($anna->email));
        $this->assertTrue(Auth::guard('site')->check(), 'This device stays.');
        $this->assertSignedOut($other);
    }

    #[Test]
    public function wrong_current_passwords_are_limited(): void
    {
        $this->actingAs($this->visitor(), 'site');

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/account/password', ['current_password' => 'wrong-one', 'password' => self::NEW, 'password_confirmation' => self::NEW])->assertStatus(422);
        }

        $this->postJson('/account/password', ['current_password' => self::PASSWORD, 'password' => self::NEW, 'password_confirmation' => self::NEW])->assertStatus(429);
        $this->postJson('/account/delete', ['password' => self::PASSWORD])->assertStatus(429);
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function an_account_without_a_password_sets_one_by_a_letter(): void
    {
        Mail::fake();
        $anna = $this->visitor(['password' => null]);
        $this->actingAs($anna, 'site');

        $this->get('/account/security')->assertOk()->assertSee('Set a password')->assertDontSee('name="current_password"', false);
        $this->postJson('/account/password', ['current_password' => 'x', 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->postJson('/account/password/link')->assertOk()->assertJsonPath('heading', 'We have sent a link to a…@example.test.');

        Mail::assertQueued(ResetPassword::class, static fn (ResetPassword $mail): bool => $mail->hasTo($anna->email));
        $this->assertSame(1, DB::table(ResetLinks::TABLE)->where('user_id', $anna->id)->count());
        $this->assertNull($anna->refresh()->password, 'No password on the spot.');
    }

    #[Test]
    public function sign_out_everywhere_ends_every_other_session_and_keeps_this_one(): void
    {
        $anna = $this->visitor();
        $other = $this->elsewhere($anna);
        $this->actingAs($anna, 'site');

        $this->postJson('/account/sign-out-everywhere')->assertOk()->assertJsonPath('heading', 'You are signed out on every other device.');

        $this->assertTrue(Auth::guard('site')->check());
        $this->assertSignedOut($other);
    }

    #[Test]
    public function signing_out_of_one_device_leaves_the_others(): void
    {
        $anna = $this->visitor();
        $other = $this->elsewhere($anna);
        $this->actingAs($anna, 'site');

        $this->post('/account/sign-out')->assertRedirect();
        Auth::forgetGuards();

        $this->withSession($other)->get('/account/sign-in')->assertRedirect('http://localhost');
    }

    #[Test]
    public function the_account_is_deleted_through_the_listeners_with_their_warnings(): void
    {
        Mail::fake();
        $anna = $this->visitor(['first_name' => 'Anna']);
        DB::table('test_orders')->insert([['user_id' => $anna->id], ['user_id' => $anna->id]]);
        DB::table('users_identities')->insert(['user_id' => $anna->id, 'provider' => 'google', 'provider_user_id' => '42', 'created_at' => now()]);
        app(ResetLinks::class)->issue($anna);
        $this->actingAs($anna, 'site');

        $this->get('/account/security')->assertOk()
            ->assertSee('Orders №1, №2 will be delivered, but you will not see them here.')
            ->assertSee('Delete forever');

        $this->postJson('/account/delete', ['password' => 'wrong-one'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertNotNull(User::query()->find($anna->id));

        $this->postJson('/account/delete', ['password' => self::PASSWORD])->assertOk()->assertJsonPath('redirect', 'http://localhost');

        $this->assertSame([$anna->id, true], OrdersModule::$deleting, 'The listener ran while the row was still there.');
        $this->assertNull(User::query()->find($anna->id), 'Deleted for good, not softly.');
        $this->assertSame(0, DB::table('users_identities')->count());
        $this->assertSame(0, DB::table(ResetLinks::TABLE)->count());
        $this->assertSame(2, DB::table('test_orders')->whereNull('user_id')->count(), 'The module unlinked its rows.');
        $this->assertFalse(Auth::guard('site')->check());
        Mail::assertQueued(AccountDeleted::class, static fn (AccountDeleted $mail): bool => $mail->hasTo($anna->email));
    }

    #[Test]
    public function a_listener_that_refuses_keeps_the_account_whole(): void
    {
        Mail::fake();
        OrdersModule::$refuse = true;
        $anna = $this->visitor();
        $this->actingAs($anna, 'site');
        $this->withoutExceptionHandling();

        try {
            $this->post('/account/delete', ['password' => self::PASSWORD]);
            $this->fail('The refusal of the listener went unnoticed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The orders refuse.', $exception->getMessage());
        }

        $this->assertNotNull(User::query()->find($anna->id));
        Mail::assertNothingQueued();
    }

    #[Test]
    public function an_account_without_a_password_is_deleted_by_typing_its_address(): void
    {
        Mail::fake();
        $anna = $this->visitor(['password' => null, 'email' => 'anna@example.test']);
        $this->actingAs($anna, 'site');

        $this->get('/account/security')->assertSee('Type the address of the account to confirm.');
        $this->postJson('/account/delete', ['email' => 'other@example.test'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'This is not the address of the account.');

        $this->postJson('/account/delete', ['email' => ' Anna@Example.test'])->assertOk();

        $this->assertNull(User::query()->find($anna->id));
        Mail::assertQueued(AccountDeleted::class, static fn (AccountDeleted $mail): bool => $mail->hasTo('anna@example.test'));
    }

    #[Test]
    public function the_letter_of_a_deleted_account_needs_no_account(): void
    {
        $anna = $this->visitor(['first_name' => 'Anna', 'locale' => 'pl']);
        $letter = new AccountDeleted($anna->name, $anna->email, $anna->locale);
        $anna->delete();

        $letter = unserialize(serialize($letter));
        $html = $letter->render();

        $this->assertSame('pl', $letter->locale);
        $this->assertStringContainsString('Anna', $html);
    }

    /**
     * Another device of the visitor: a session signed in, with its stamp.
     *
     * @return array<string, mixed>
     */
    private function elsewhere(User $user): array
    {
        $session = ['login_site_'.sha1(SessionGuard::class) => $user->id, TrackVisitor::STAMP => TrackVisitor::stamp($user)];

        $this->withSession($session)->get('/account/sign-in')->assertRedirect('http://localhost');
        Auth::forgetGuards();

        return $session;
    }

    /** @param  array<string, mixed>  $session */
    private function assertSignedOut(array $session): void
    {
        Auth::forgetGuards();
        $this->flushSession();

        $this->withSession($session)->get('/account/sign-in')->assertOk();
        $this->assertFalse(Auth::guard('site')->check(), 'The other device is signed out.');
    }
}
