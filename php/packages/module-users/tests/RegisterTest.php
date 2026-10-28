<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Visitors\Events\VisitorRegistered;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Users\Models\User;

/**
 * The registration (spec §6.2, §15, decisions 4, 9 and 15): the fields in their order, the
 * visitor signed in the moment it is sent, a taken address said plainly, five a hour per IP, and
 * a site that closes it.
 */
final class RegisterTest extends TestCase
{
    #[Test]
    public function the_page_asks_in_the_order_of_the_spec_with_what_the_browser_fills_in(): void
    {
        $html = (string) $this->get('/account/register')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Create an account', $html);

        preg_match_all('~name="(first_name|last_name|phone|email|password)"~', $html, $names);
        $this->assertSame(['first_name', 'last_name', 'phone', 'email', 'password'], $names[1]);

        foreach (['given-name', 'family-name', 'tel', 'email', 'new-password'] as $autocomplete) {
            $this->assertStringContainsString('autocomplete="'.$autocomplete.'"', $html);
        }

        // No second password: «Show» on the field instead (decision 3).
        $this->assertStringNotContainsString('password_confirmation', $html);
        $this->assertStringContainsString('At least 8 characters.', $html);
        $this->assertStringContainsString('href="http://localhost/account/sign-in"', $html);
    }

    #[Test]
    public function a_registration_signs_in_at_once_and_says_so_twice(): void
    {
        Event::fake([VisitorRegistered::class, VisitorSignedIn::class]);

        $this->post('/account/register', [
            'first_name' => ' Anna ',
            'last_name' => '',
            'phone' => '+800 1234 5678',
            'email' => ' Anna@Example.TEST',
            'password' => 'long-enough',
            'back' => '/checkout',
        ])->assertRedirect('/checkout');

        $anna = User::query()->sole();
        $this->assertSame('anna@example.test', $anna->email);
        $this->assertSame('Anna', $anna->first_name);
        $this->assertNull($anna->last_name);
        $this->assertSame('+800 1234 5678', $anna->phone);
        $this->assertSame('en', $anna->locale);
        $this->assertTrue(Hash::check('long-enough', (string) $anna->password));
        // Confirming the address does not stand in the way (decision 9).
        $this->assertNull($anna->email_verified_at);
        $this->assertSame($anna->id, Auth::guard('site')->id());

        Event::assertDispatched(VisitorRegistered::class, static fn (VisitorRegistered $event): bool => $event->visitorId === $anna->id && $event->method === 'password');
        Event::assertDispatched(VisitorSignedIn::class, static fn (VisitorSignedIn $event): bool => $event->visitorId === $anna->id && $event->method === 'registration');
    }

    #[Test]
    public function only_the_address_and_the_password_are_required(): void
    {
        $this->postJson('/account/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonMissingValidationErrors(['first_name', 'last_name', 'phone']);

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => 'long-enough', 'phone' => 'call me'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    public function a_site_makes_the_password_stricter_in_the_config(): void
    {
        config(['webx-users.password_rules' => ['min' => 12, 'numbers' => true]]);

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => 'only-letters-here'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => 'letters-and-1234'])->assertOk();
    }

    #[Test]
    public function a_taken_address_is_said_plainly_whatever_its_case(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        $this->postJson('/account/register', ['email' => 'ANNA@example.test ', 'password' => 'long-enough'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'This address is already registered — sign in or restore your password.');

        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Auth::guard('site')->check());
    }

    #[Test]
    public function five_attempts_an_hour_from_one_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/account/register', ['email' => "taken-{$attempt}@example.test"])->assertStatus(422);
        }

        $this->postJson('/account/register', ['email' => 'new@example.test', 'password' => 'long-enough'])
            ->assertStatus(429)
            ->assertJsonPath('errors.email.0', fn (string $message): bool => str_starts_with($message, 'Too many attempts.'));

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function a_signed_in_visitor_is_sent_on_rather_than_shown_the_form(): void
    {
        $this->actingAs($this->visitor(), 'site');

        $this->get('/account/register?back=/orders')->assertRedirect('/orders');
        $this->get('/account/sign-in')->assertRedirect('http://localhost');
    }

    #[Test]
    public function a_closed_registration_is_no_page_and_no_link(): void
    {
        config(['webx-users.registration' => 'closed']);

        $this->get('/account/register')->assertNotFound();
        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => 'long-enough'])->assertNotFound();
        $this->get('/account/sign-in')->assertOk()->assertDontSee('/account/register', false);
        $this->assertSame(0, User::query()->count());
    }
}
