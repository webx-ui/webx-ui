<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Panel;

use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Users\Account\DeletionWarnings;
use WebxUi\Users\Mail\EmailChanged;
use WebxUi\Users\Mail\Invitation;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Mail\VerifyEmail;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\UsersModule;

/**
 * «Customers» as the panel reaches it (spec §16): the menu, the permissions, the list with its
 * filters at one cost for any page, the card and its actions, the groups — and nothing in any
 * answer that is a password, a hash or a token.
 */
final class PanelTest extends PanelTestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-users.fields', [
            'club-card' => ['type' => 'wx-input', 'label' => 'Club card', 'unique' => true, 'visitorEditable' => false, 'placements' => ['profile', 'panel']],
        ]);
    }

    #[Test]
    public function customers_are_a_group_of_three_entries_each_shown_by_its_permission(): void
    {
        $modules = self::rows($this->asAdministrator()->getJson('/api/cms/manifest')->assertOk()->json('data.modules'))->keyBy('id');

        $this->assertSame('users', $modules['users']['group']);
        $this->assertSame([UsersModule::VIEW, UsersModule::MANAGE, UsersModule::SETTINGS], $modules['users']['permissions']);
        $this->assertSame('users', $modules['users-groups']['group']);
        $this->assertSame('users', $modules['users-settings']['group']);

        // The keys of the providers are not the business of whoever looks people up, and back.
        $viewer = self::rows($this->asAdministrator([UsersModule::VIEW])->getJson('/api/cms/manifest')->json('data.modules'))->keyBy('id');
        $this->assertTrue($viewer['users']['allowed'] ?? true);
        $this->assertFalse($viewer['users-settings']['allowed'] ?? true);

        $keeper = self::rows($this->asAdministrator([UsersModule::SETTINGS])->getJson('/api/cms/manifest')->json('data.modules'))->keyBy('id');
        $this->assertFalse($keeper['users']['allowed'] ?? true);
        $this->assertTrue($keeper['users-settings']['allowed'] ?? false);
    }

    #[Test]
    public function a_reader_reads_and_writes_nothing_and_the_settings_are_their_own_permission(): void
    {
        $anna = $this->visitor();
        $this->asAdministrator([UsersModule::VIEW]);

        $this->getJson($this->api())->assertOk();
        $this->getJson($this->api($anna->id))->assertOk();
        $this->getJson($this->api('groups'))->assertOk();
        $this->putJson($this->api($anna->id), ['values' => ['first_name' => 'Boris']])->assertForbidden();
        $this->postJson($this->api($anna->id.'/block'))->assertForbidden();
        $this->deleteJson($this->api($anna->id))->assertForbidden();
        $this->postJson($this->api('groups'), ['code' => 'club', 'name' => 'Club'])->assertForbidden();
        $this->getJson($this->api('settings'))->assertForbidden();

        $this->asAdministrator([UsersModule::SETTINGS]);
        $this->getJson($this->api())->assertForbidden();
        $this->getJson($this->api('settings'))->assertOk();
    }

    #[Test]
    public function the_list_searches_and_filters_as_the_spec_says_and_says_nothing_secret(): void
    {
        $club = Group::query()->create(['code' => 'club', 'name' => 'Club']);
        $anna = $this->visitor(['first_name' => 'Anna', 'last_name' => 'Petrova', 'phone' => '+800 1234 5678']);
        $anna->forceFill(['email_verified_at' => now(), 'last_sign_in_at' => now()])->save();
        $anna->groups()->attach($club->id);
        $boris = $this->visitor(['first_name' => 'Boris', 'password' => null]);
        $boris->forceFill(['blocked_at' => now(), 'created_at' => now()->subYear()])->save();
        Identity::query()->create(['user_id' => $boris->id, 'provider' => 'google', 'provider_user_id' => 'g-1', 'email' => $boris->email]);

        $this->asAdministrator([UsersModule::VIEW]);

        $ids = fn (array $query): array => self::rows($this->getJson($this->api().'?'.http_build_query($query))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$anna->id], $ids(['search' => 'Petrova Anna']));
        $this->assertSame([$anna->id], $ids(['search' => '80012345']));
        $this->assertSame([$boris->id], $ids(['search' => $boris->email]));
        $this->assertSame([$anna->id], $ids(['group' => 'club']));
        $this->assertSame([$anna->id], $ids(['verified' => 1]));
        $this->assertSame([$boris->id], $ids(['blocked' => 1]));
        $this->assertSame([$boris->id], $ids(['provider' => 'google']));
        $this->assertSame([$anna->id], $ids(['provider' => 'password']));
        $this->assertSame([$anna->id], $ids(['from' => now()->subDay()->toDateString()]));
        $this->assertSame([$anna->id, $boris->id], $ids(['sort' => '-last_sign_in_at']));

        $row = self::rows($this->getJson($this->api())->json('data'))->firstWhere('id', $anna->id);
        $this->assertSame('Anna Petrova', $row['name']);
        $this->assertSame([['code' => 'club', 'name' => 'Club']], $row['groups']);
        $this->assertSame(['password'], $row['ways']);
        $this->assertTrue($row['verified']);

        $body = (string) $this->getJson($this->api())->getContent();
        $this->assertStringNotContainsString('remember_token', $body);
        $this->assertStringNotContainsString('g-1', $body, 'the id of a person at a provider');
        $this->assertStringNotContainsString((string) $anna->getAuthPassword(), $body);
    }

    #[Test]
    public function a_page_of_fifty_costs_what_a_page_of_ten_does(): void
    {
        $clubs = [Group::query()->create(['code' => 'club', 'name' => 'Club'])->id, Group::query()->create(['code' => 'wholesale', 'name' => 'Wholesale'])->id];

        for ($i = 0; $i < 50; $i++) {
            $user = $this->visitor();
            $user->groups()->attach($clubs);
            Identity::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'g-'.$i]);
        }

        $this->asAdministrator([UsersModule::VIEW]);
        // Warm: the administrator, the roles, the providers' rows are read once a request.
        $this->getJson($this->api().'?per_page=10')->assertOk();

        // The administrator and their roles, the providers, the count, the page, its groups and
        // its links — the same for ten rows and for fifty.
        $ten = $this->assertQueriesWithin(8, fn () => $this->getJson($this->api().'?per_page=10')->assertOk()->json('data'));
        $fifty = $this->assertQueriesWithin(8, fn () => $this->getJson($this->api().'?per_page=50')->assertOk()->json('data'));

        $this->assertCount(10, $ten);
        $this->assertCount(50, $fifty);
        $this->assertNoRepeatedQueries(fn () => $this->getJson($this->api().'?per_page=50&group=club')->assertOk());
    }

    #[Test]
    public function the_card_saves_the_profile_the_fields_of_the_site_and_the_groups(): void
    {
        Group::query()->create(['code' => 'club', 'name' => 'Club']);
        Group::query()->create(['code' => 'wholesale', 'name' => 'Wholesale']);
        $anna = $this->visitor();
        $this->asAdministrator();

        $screen = $this->getJson('/api/cms/screens/users.form')->assertOk()->json('data');
        $encoded = (string) json_encode($screen);
        $this->assertStringContainsString('"field-club-card"', $encoded, 'the field of the site is on the card');
        $this->assertStringNotContainsString('project-fields-empty', $encoded);
        $this->assertStringContainsString('"wholesale"', $encoded, 'the groups to tick');
        // The address is always required (`UserForm::rules()`), the names are not.
        $this->assertSame([true, false], [
            Tree::find($screen['root'], 'email')['required'] ?? false,
            Tree::find($screen['root'], 'first-name')['required'] ?? false,
        ]);

        $answer = $this->putJson($this->api($anna->id), ['values' => [
            'first_name' => 'Anna',
            'phone' => '+800 1234 5678',
            'club-card' => 'C-7',
            'groups' => ['wholesale', 'club'],
        ]])->assertOk()->json('data');

        $this->assertSame('Anna', $answer['values']['first_name']);
        $this->assertSame('C-7', $answer['values']['club-card']);
        $this->assertEqualsCanonicalizing(['club', 'wholesale'], $answer['values']['groups']);
        $this->assertEqualsCanonicalizing(['club', 'wholesale'], $anna->refresh()->toVisitor()->groups);

        // The same rules as the site's forms and the agent's: one card per number.
        $boris = $this->visitor();
        $this->putJson($this->api($boris->id), ['values' => ['club-card' => 'C-7']])
            ->assertUnprocessable()->assertJsonValidationErrors('club-card');
        $this->putJson($this->api($boris->id), ['values' => ['email' => $anna->email]])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson($this->api($boris->id), ['values' => ['groups' => ['vip']]])
            ->assertUnprocessable()->assertJsonValidationErrors('groups');

        // Only what was sent is checked and written.
        $this->putJson($this->api($anna->id), ['values' => ['last_name' => 'Petrova']])->assertOk();
        $this->assertSame('C-7', $anna->refresh()->extra('club-card'));
        $this->assertSame(['club', 'wholesale'], $anna->groups()->orderBy('code')->pluck('code')->all());
    }

    #[Test]
    public function a_new_address_set_by_the_administrator_is_unconfirmed_and_both_addresses_are_told(): void
    {
        Mail::fake();
        $anna = $this->visitor();
        $anna->forceFill(['email_verified_at' => now()])->save();
        $old = $anna->email;

        $this->asAdministrator()->putJson($this->api($anna->id), ['values' => ['email' => ' New@Example.test ']])->assertOk()
            ->assertJsonPath('data.user.verified', false);

        $this->assertSame('new@example.test', $anna->refresh()->email);
        $this->assertNull($anna->email_verified_at);
        Mail::assertQueued(VerifyEmail::class, static fn (VerifyEmail $mail): bool => $mail->hasTo('new@example.test'));
        Mail::assertQueued(EmailChanged::class, static fn (EmailChanged $mail): bool => $mail->hasTo($old));
    }

    #[Test]
    public function a_visitor_made_in_the_panel_has_no_password_and_is_invited_to_set_one(): void
    {
        Mail::fake();

        $answer = $this->asAdministrator()->postJson($this->api(), ['invite' => true, 'values' => [
            'email' => 'invited@example.test',
            'first_name' => 'Ivan',
            'password' => 'never-taken',
        ]])->assertCreated()->json('data');

        $user = User::query()->findOrFail($answer['user']['id']);
        $this->assertNull($user->password, 'the panel never sets a password');
        $this->assertFalse($answer['user']['has_password']);

        $link = null;
        Mail::assertQueued(Invitation::class, static function (Invitation $mail) use (&$link): bool {
            $link = $mail->link;

            return $mail->hasTo('invited@example.test') && $mail->days === 7;
        });

        $this->assertSame('invite', DB::table('users_password_resets')->where('user_id', $user->id)->value('purpose'));
        $this->assertTrue(now()->addDays(6)->lessThan(DB::table('users_password_resets')->where('user_id', $user->id)->value('expires_at')));

        $this->get((string) parse_url((string) $link, PHP_URL_PATH))->assertOk()->assertSee('Set a password');

        $this->postJson($this->api(), ['values' => ['email' => 'invited@example.test']])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    #[Test]
    public function the_actions_of_the_card_send_letters_block_confirm_and_answer_without_links(): void
    {
        Mail::fake();
        $anna = $this->visitor();
        $this->asAdministrator();

        $body = $this->postJson($this->api($anna->id.'/reset-link'))->assertOk()->assertExactJson(['data' => ['sent' => true]]);
        Mail::assertQueued(ResetPassword::class);
        $this->assertStringNotContainsString('/account/reset/', (string) $body->getContent());

        $this->postJson($this->api($anna->id.'/verification'))->assertOk();
        Mail::assertQueued(VerifyEmail::class);

        $this->postJson($this->api($anna->id.'/verify'))->assertOk()->assertJsonPath('data.user.verified', true);
        $this->postJson($this->api($anna->id.'/verification'))->assertUnprocessable();

        $this->postJson($this->api($anna->id.'/block'))->assertOk()->assertJsonPath('data.user.blocked', true);
        $this->postJson($this->api($anna->id.'/reset-link'))->assertUnprocessable();
        $this->post('/account/sign-in', ['email' => $anna->email, 'password' => self::PASSWORD]);
        $this->assertGuest('site');

        $this->postJson($this->api($anna->id.'/unblock'))->assertOk()->assertJsonPath('data.user.blocked', false);
        $this->getJson($this->api(999))->assertNotFound();
    }

    #[Test]
    public function the_last_way_to_sign_in_is_not_unlinked_from_the_panel_either(): void
    {
        config()->set('webx-users.social.keys.google', ['enabled' => true, 'client_id' => 'id', 'client_secret' => 'secret']);
        $boris = $this->visitor(['password' => null]);
        Identity::query()->create(['user_id' => $boris->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);
        $this->asAdministrator();

        $ways = $this->getJson($this->api($boris->id))->assertOk()->json('data.user.ways');
        $this->assertSame([['key' => 'google', 'can_unlink' => false]], array_map(static fn (array $way): array => ['key' => $way['key'], 'can_unlink' => $way['can_unlink']], $ways));

        $this->deleteJson($this->api($boris->id.'/ways/google'))->assertUnprocessable()->assertJsonValidationErrors('provider');

        $boris->forceFill(['password' => 'a-password-now'])->save();
        $this->deleteJson($this->api($boris->id.'/ways/google'))->assertOk()->assertJsonPath('data.user.ways.0.key', 'password');
        $this->assertSame(0, Identity::query()->count());
    }

    #[Test]
    public function deleting_shows_what_the_modules_say_and_goes_through_their_listeners(): void
    {
        Mail::fake();
        $this->app->make(DeletionWarnings::class)->register(static fn (): string => 'Orders stay without a customer.');
        $club = Group::query()->create(['code' => 'club', 'name' => 'Club']);
        $anna = $this->visitor();
        $anna->groups()->attach($club->id);
        $this->asAdministrator();

        $this->getJson($this->api($anna->id.'/warnings'))->assertOk()->assertExactJson(['data' => ['warnings' => ['Orders stay without a customer.']]]);
        $this->deleteJson($this->api($anna->id))->assertNoContent();

        $this->assertNull(User::query()->find($anna->id));
        $this->assertSame(0, DB::table('users_group_user')->count());
        $this->assertSame(1, Group::query()->count(), 'the group stays');
    }

    #[Test]
    public function groups_are_a_code_and_a_name_and_go_without_asking_anybody(): void
    {
        $this->asAdministrator();

        $club = $this->postJson($this->api('groups'), ['code' => ' Club ', 'name' => ' Club '])->assertCreated()->json('data');
        $this->assertSame(['club', 'Club', 0], [$club['code'], $club['name'], $club['users_count']]);

        $this->postJson($this->api('groups'), ['code' => 'club', 'name' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson($this->api('groups'), ['code' => 'two words', 'name' => 'Bad'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson($this->api('groups'), ['code' => 'wholesale', 'name' => 'A wholesale'])->assertCreated();

        $anna = $this->visitor();
        $anna->groups()->attach($club['id']);

        $list = $this->getJson($this->api('groups'))->assertOk()->json('data');
        $this->assertSame(['A wholesale', 'Club'], array_column($list, 'name'), 'by name');
        $this->assertSame(1, $list[1]['users_count']);

        $this->putJson($this->api('groups/'.$club['id']), ['code' => 'club', 'name' => 'Club members'])->assertOk()->assertJsonPath('data.name', 'Club members');
        $this->deleteJson($this->api('groups/'.$club['id']))->assertNoContent();
        $this->assertSame([], $anna->refresh()->toVisitor()->groups);
    }

    #[Test]
    public function a_save_of_the_card_is_in_the_journal_and_a_password_never_is(): void
    {
        $anna = $this->visitor();
        $this->asAdministrator()->putJson($this->api($anna->id), ['values' => ['first_name' => 'Anna']])->assertOk();
        $anna->forceFill(['password' => 'another-password'])->save();

        $rows = DB::table('cms_history')->where('subject_type', User::TYPE)->where('subject_id', $anna->id)->get();
        $all = (string) $rows->toJson();

        $this->assertStringContainsString('first_name', $all);
        $this->assertStringContainsString('masked', $all);
        $this->assertStringNotContainsString('another-password', $all);
        $this->assertStringNotContainsString((string) $anna->refresh()->getAuthPassword(), $all);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function rows(mixed $rows): Collection
    {
        /** @var array<int, array<string, mixed>> $list */
        $list = is_array($rows) ? $rows : [];

        return new Collection($list);
    }
}
