<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\AccountSection;
use WebxUi\Users\Emails\EmailChange;
use WebxUi\Users\Models\User;
use WebxUi\Users\Tests\Fixtures\OrdersModule;

/**
 * The account (spec §11): the profile, the menu of its sections — the module's own and one a
 * shop's module adds without a line of this module changed — and what a page of it costs.
 */
final class AccountTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), OrdersModule::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-users.fields', [
            'nickname' => ['type' => 'wx-input', 'label' => 'Nickname', 'placements' => ['profile'], 'rules' => ['max:20']],
            'club-card' => ['type' => 'wx-input', 'label' => 'Club card', 'visitorEditable' => false, 'placements' => ['profile', 'panel']],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_orders', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
        });
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_and_back(): void
    {
        $this->get('/account/profile')->assertRedirect('http://localhost/account/sign-in?back=%2Faccount%2Fprofile');
        $this->get('/account')->assertRedirect();
        $this->get('/account/orders')->assertRedirect('http://localhost/account/sign-in?back=%2Faccount%2Forders');
        $this->getJson('/account/security')->assertStatus(401);
    }

    #[Test]
    public function the_account_opens_on_its_first_section(): void
    {
        $this->actingAs($this->visitor(), 'site');

        $this->get('/account')->assertRedirect('http://localhost/account/profile');
        $this->get('/pl/account')->assertRedirect('http://localhost/pl/account/profile');
    }

    #[Test]
    public function the_profile_shows_and_keeps_the_visitors_data(): void
    {
        $anna = $this->visitor(['first_name' => 'Anna', 'phone' => '+800 1234 5678', 'extra' => ['club-card' => 'C-77']]);
        $this->actingAs($anna, 'site');

        $html = (string) $this->get('/account/profile')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();

        $this->assertStringContainsString('<h1 class="webx-account__title">Profile</h1>', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*name="first_name"[^>]*value="Anna"~', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*name="fields\[club-card\]"[^>]*value="C-77"~', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*name="fields\[club-card\]"[^>]*readonly~', $html);
        $this->assertStringContainsString($anna->email, $html);
        $this->assertStringContainsString('not confirmed', $html);
        $this->assertStringContainsString('name="password"', $html, 'The change of the address asks for the current password.');

        $this->postJson('/account/profile', ['first_name' => ' Anne ', 'last_name' => 'Smith', 'phone' => '', 'fields' => ['nickname' => 'annie', 'club-card' => 'FORGED']])
            ->assertOk()
            ->assertJsonPath('heading', 'Saved.');

        $anna->refresh();
        $this->assertSame('Anne', $anna->first_name);
        $this->assertSame('Smith', $anna->last_name);
        $this->assertNull($anna->phone);
        $this->assertSame(['club-card' => 'C-77', 'nickname' => 'annie'], $anna->extra, 'A field the visitor may not change keeps its value.');

        $this->postJson('/account/profile', ['phone' => 'call me', 'fields' => ['nickname' => str_repeat('a', 21)]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'fields.nickname']);
    }

    #[Test]
    public function the_profile_says_which_address_waits_for_its_link(): void
    {
        $anna = $this->confirmed();
        $this->actingAs($anna, 'site');

        $this->withSession([EmailChange::PENDING => 'new@example.test'])
            ->get('/account/profile')
            ->assertSee('Waiting for confirmation: new@example.test')
            ->assertSee('webx-account__status--ok', false)
            ->assertDontSee('not confirmed');
    }

    #[Test]
    public function a_module_adds_its_section_without_a_change_of_this_module(): void
    {
        $anna = $this->visitor();
        DB::table('test_orders')->insert([['user_id' => $anna->id], ['user_id' => $anna->id], ['user_id' => null]]);
        $this->actingAs($anna, 'site');

        $html = (string) $this->get('/account/orders')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();

        preg_match_all('~<a href="([^"]+)" class="webx-account__item webx-account__item--([a-z]+)( is-active)?"~', $html, $items, PREG_SET_ORDER);
        $this->assertSame(
            [['http://localhost/account/profile', 'profile', ''], ['http://localhost/account/orders', 'orders', ' is-active'], ['http://localhost/account/security', 'security', '']],
            array_map(static fn (array $item): array => [$item[1], $item[2], $item[3] ?? ''], $items),
            'In position order: the profile 10, the orders 30, security 90.',
        );
        $this->assertMatchesRegularExpression('~My orders</span>\s*<span class="webx-account__badge">2</span>~', $html);
        $this->assertStringContainsString('The orders of the visitor', $html);
        $this->assertStringContainsString('aria-current="page"', $html);

        // A page of the section under its pattern lights it too; the copy behind the language has its addresses.
        $this->get('/account/orders/7')->assertSee('webx-account__item--orders is-active', false);
        $this->get('/pl/account/orders')->assertOk()->assertSee('href="http://localhost/pl/account/profile"', false);
    }

    #[Test]
    public function a_section_for_a_confirmed_address_asks_to_confirm_it(): void
    {
        $this->actingAs($this->visitor(), 'site');
        $this->get('/account/balance')->assertForbidden()->assertSee('Confirm your e-mail');

        Auth::forgetGuards();
        $this->flushSession();
        $this->actingAs($this->confirmed(), 'site');
        $this->get('/account/balance')->assertOk()->assertSee('The balance');
        $this->get('/pl/account/balance')->assertOk();
    }

    #[Test]
    public function the_site_hides_a_section_by_its_id(): void
    {
        config(['webx-users.account.hidden' => ['orders']]);
        $this->actingAs($this->visitor(), 'site');

        $this->get('/account/profile')->assertOk()->assertDontSee('webx-account__item--orders', false);
    }

    #[Test]
    public function two_modules_with_one_id_is_a_mistake(): void
    {
        $this->expectExceptionMessage('The section of the account "orders" is registered already.');

        app(AccountMenu::class)->register(new AccountSection(id: 'orders', label: 'Orders', route: 'test.orders.account.index'));
    }

    #[Test]
    public function a_page_of_the_account_costs_the_visitor_the_badges_and_nothing_twice(): void
    {
        $anna = $this->visitor();
        // Through the session, as a browser comes: the guard finds the visitor on every request.
        $session = ['login_site_'.sha1(SessionGuard::class) => $anna->id];
        $this->withSession($session)->get('/account/profile')->assertOk();
        Auth::forgetGuards();

        // The visitor (1) — the data of a visitor is theirs and never cached (§18) — and one query
        // of each module with a badge (the orders: 1). The profile reads nothing else.
        $this->assertQueriesWithin(2, fn () => $this->withSession($session)->get('/account/profile')->assertOk());
        Auth::forgetGuards();
        $this->assertNoRepeatedQueries(fn () => $this->withSession($session)->get('/account/security')->assertOk());
    }

    private function confirmed(): User
    {
        $user = $this->visitor();
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
