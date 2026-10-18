<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Mail\EmailChangeLink;
use WebxUi\Auth\Models\CmsUser;

/**
 * Moving your own sign-in address: a letter to the new one, and the link in it that makes the
 * move (WEBX_UI_PERMISSIONS.md §8).
 */
final class EmailChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('webx-auth.email_change', true);
        Mail::fake();
    }

    #[Test]
    public function asking_sends_a_letter_to_the_new_address_and_changes_nothing_yet(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->postJson('api/cms/auth/me/email', [
            'email' => 'new.address@example.test',
            'current_password' => 'correct-horse-battery',
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.email', 'new.address@example.test');

        Mail::assertSent(EmailChangeLink::class, static fn (EmailChangeLink $mail): bool => $mail->hasTo('new.address@example.test'));

        // The letter says what the link does and carries it.
        Mail::assertSent(EmailChangeLink::class, function (EmailChangeLink $mail): bool {
            $html = $mail->render();
            $this->assertStringContainsString(e($mail->link), $html);
            $this->assertStringContainsString('Confirm the address', $html);

            return true;
        });
        $this->assertSame('admin@example.test', $admin->fresh()?->email);
    }

    #[Test]
    public function the_link_in_the_letter_makes_the_move_once_and_writes_it_down(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $link = $this->ask($admin, 'new.address@example.test');
        $second = $this->ask($admin, 'another.address@example.test');

        // Followed from the mailbox, which need not be a signed-in browser.
        $this->app->make('auth')->guard('cms')->logout();

        $this->get($link)->assertRedirectContains('/cms/?email-change=done');
        $this->assertSame('new.address@example.test', $admin->fresh()?->email);

        $row = HistoryEntry::query()
            ->where('subject_type', CmsUser::TYPE)
            ->where('subject_id', $admin->id)
            ->where('event', HistoryEntry::UPDATED)
            ->sole();
        $this->assertSame('email', $row->changes[0]['field'] ?? null);

        // The address it moved from is gone, so every other letter in flight is dead.
        $this->get($link)->assertRedirectContains('/cms/?email-change=expired');
        $this->get($second)->assertRedirectContains('/cms/?email-change=expired');
        $this->assertSame('new.address@example.test', $admin->refresh()->email);
    }

    #[Test]
    public function a_link_that_ran_out_or_was_tampered_with_changes_nothing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $link = $this->ask($admin, 'new.address@example.test');

        $this->get(str_replace('new.address', 'attacker', $link))->assertRedirectContains('/cms/?email-change=expired');

        $this->travel(61)->minutes();
        $this->get($link)->assertRedirectContains('/cms/?email-change=expired');

        $this->assertSame('admin@example.test', $admin->fresh()?->email);
    }

    #[Test]
    public function an_address_somebody_took_in_the_meantime_is_not_taken_twice(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $link = $this->ask($admin, 'new.address@example.test');
        $this->admin('new.address@example.test');

        $this->get($link)->assertRedirectContains('/cms/?email-change=taken');
        $this->assertSame('admin@example.test', $admin->fresh()?->email);
    }

    #[Test]
    public function the_current_password_and_a_free_new_address_are_required(): void
    {
        $admin = $this->admin();
        $this->admin('taken@example.test');
        $this->actingAs($admin, 'cms');

        $this->postJson('api/cms/auth/me/email', ['email' => 'new.address@example.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->postJson('api/cms/auth/me/email', ['email' => 'new.address@example.test', 'current_password' => 'wrong-horse-battery'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->postJson('api/cms/auth/me/email', ['email' => 'taken@example.test', 'current_password' => 'correct-horse-battery'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson('api/cms/auth/me/email', ['email' => 'admin@example.test', 'current_password' => 'correct-horse-battery'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson('api/cms/auth/me/email', [
            'email' => 'new.address@example.test',
            'current_password' => 'correct-horse-battery',
            'is_super' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_super');

        Mail::assertNothingSent();
    }

    #[Test]
    public function without_mail_the_address_is_not_offered_to_change(): void
    {
        // Decision 8 (a): a letter that cannot leave is not a "check your inbox".
        config()->set('webx-auth.email_change', null);
        config()->set('mail.default', 'log');

        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->getJson('api/cms/auth/me')->assertJsonPath('data.emailChange', false);

        $this->postJson('api/cms/auth/me/email', ['email' => 'new.address@example.test', 'current_password' => 'correct-horse-battery'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        config()->set('mail.default', 'smtp');
        $this->getJson('api/cms/auth/me')->assertJsonPath('data.emailChange', true);

        Mail::assertNothingSent();
    }

    #[Test]
    public function a_visitor_cannot_ask(): void
    {
        $this->postJson('api/cms/auth/me/email', ['email' => 'new.address@example.test'])->assertUnauthorized();
    }

    /** Ask for a move and hand back the link the letter carried. */
    private function ask(CmsUser $admin, string $email): string
    {
        $this->postJson('api/cms/auth/me/email', ['email' => $email, 'current_password' => 'correct-horse-battery'])
            ->assertStatus(202);

        $link = null;

        Mail::assertSent(EmailChangeLink::class, static function (EmailChangeLink $mail) use ($email, &$link): bool {
            if ($mail->hasTo($email)) {
                $link = $mail->link;
            }

            return true;
        });

        $this->assertIsString($link);

        return $link;
    }
}
