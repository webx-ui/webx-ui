<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Users\Emails\EmailChange;
use WebxUi\Users\Mail\EmailChanged;
use WebxUi\Users\Mail\EmailChangeLink;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;

/**
 * Moving the sign-in to a new address (spec §8.5, decision 16): the current password, a link to
 * the new address, nothing changed until it is followed; then the new address, confirmed, the
 * reset link of the old one gone, and a letter to the old one.
 */
final class EmailChangeTest extends TestCase
{
    #[Test]
    public function the_request_asks_for_the_password_and_changes_nothing_yet(): void
    {
        Mail::fake();
        $this->visitor(['email' => 'taken@example.test']);
        $anna = $this->visitor(['email' => 'anna@example.test']);

        $this->postJson('/account/email', ['email' => 'new@example.test', 'password' => self::PASSWORD])->assertStatus(401);

        $this->actingAs($anna, 'site');

        $this->postJson('/account/email', ['email' => 'new@example.test'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/account/email', ['email' => 'new@example.test', 'password' => 'wrong-one'])
            ->assertStatus(422)->assertJsonPath('errors.password.0', 'The password is wrong.');
        $this->postJson('/account/email', ['email' => ' Anna@example.test', 'password' => self::PASSWORD])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'This is the address you sign in with already.');
        $this->postJson('/account/email', ['email' => 'taken@example.test', 'password' => self::PASSWORD])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'This address belongs to another account.');
        Mail::assertNothingQueued();

        $this->postJson('/account/email', ['email' => 'New@Example.test', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('heading', 'We have sent a link to n…@example.test. Your address changes once you follow it.');

        Mail::assertQueued(EmailChangeLink::class, static fn (EmailChangeLink $mail): bool => $mail->hasTo('new@example.test'));
        $this->assertSame('anna@example.test', $anna->refresh()->email, 'Nothing changes before the link.');
        $this->assertSame('new@example.test', session(EmailChange::PENDING));
    }

    #[Test]
    public function the_link_moves_the_sign_in_confirms_it_and_tells_the_old_address(): void
    {
        Mail::fake();
        $anna = $this->visitor(['email' => 'anna@example.test']);
        app(ResetLinks::class)->issue($anna);
        $this->actingAs($anna, 'site');
        $link = $this->requestLink('new@example.test');

        $this->get($link)->assertOk()->assertSee('Address changed')->assertSee('new@example.test');

        $anna->refresh();
        $this->assertSame('new@example.test', $anna->email);
        $this->assertTrue($anna->hasVerifiedEmail(), 'Whoever opened the link reads that mailbox.');
        $this->assertSame(0, DB::table(ResetLinks::TABLE)->count(), 'The reset link of the old address dies.');
        $this->assertNull(session(EmailChange::PENDING));
        Mail::assertQueued(EmailChanged::class, static fn (EmailChanged $mail): bool => $mail->hasTo('anna@example.test') && $mail->email === 'n…@example.test');

        // Once: the fingerprint of the old address does not match any more.
        $this->get($link)->assertSee('The link is out of date');
    }

    #[Test]
    public function a_link_dies_with_time_with_another_move_and_with_the_address_taken_meanwhile(): void
    {
        Mail::fake();
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $this->actingAs($anna, 'site');

        $first = $this->requestLink('one@example.test');
        $second = $this->requestLink('two@example.test');
        $this->visitor(['email' => 'two@example.test']);

        $this->get($second)->assertSee('The link is out of date');
        $this->assertSame('anna@example.test', $anna->refresh()->email);

        $this->travel(61)->minutes();
        $this->get($first)->assertSee('The link is out of date');
        $this->assertDatabaseHas('users', ['id' => $anna->id, 'email' => 'anna@example.test']);
    }

    #[Test]
    public function an_account_without_a_password_is_confirmed_by_the_mailbox_alone(): void
    {
        Mail::fake();
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $anna->forceFill(['password' => null])->save();
        $this->actingAs(User::query()->findOrFail($anna->id), 'site');

        $this->postJson('/account/email', ['email' => 'new@example.test'])->assertOk();
        Mail::assertQueued(EmailChangeLink::class, 1);
    }

    #[Test]
    public function letters_to_new_addresses_are_limited(): void
    {
        Mail::fake();
        $this->actingAs($this->visitor(), 'site');

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/account/email', ['email' => "new-{$attempt}@example.test", 'password' => self::PASSWORD])->assertOk();
        }

        $this->postJson('/account/email', ['email' => 'new-4@example.test', 'password' => self::PASSWORD])->assertStatus(429);
        Mail::assertQueued(EmailChangeLink::class, 3);
    }

    private function requestLink(string $email): string
    {
        $this->postJson('/account/email', ['email' => $email, 'password' => self::PASSWORD])->assertOk();

        /** @var EmailChangeLink $letter */
        $letter = Mail::queued(EmailChangeLink::class, static fn (EmailChangeLink $mail): bool => $mail->hasTo($email))->last();

        return $letter->link;
    }
}
