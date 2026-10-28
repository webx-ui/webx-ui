<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Mcp;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Users\Account\DeletionWarnings;
use WebxUi\Users\Mail\EmailChanged;
use WebxUi\Users\Mail\Invitation;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Mail\VerifyEmail;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\ProviderSettings;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\UsersModule;

/**
 * «Customers» by the agent's doors (spec §17): the same list, the same card and its rules, the
 * same groups and settings as the panel — and never a password, a hash, a link of a letter, a
 * token, a secret of a network or the id of a person at it.
 */
final class McpTest extends McpTestCase
{
    private const string SECRET = 'top-secret-value-4815162342';

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
    public function the_section_offers_its_tools_behind_its_scopes_and_permissions(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame([
            'users_list', 'users_get', 'users_create', 'users_update', 'users_delete', 'users_send_password_reset',
            'users_resend_verification', 'users_unlink_provider', 'users_groups_list', 'users_groups_save',
            'users_groups_delete', 'users_settings_get', 'users_settings_set',
        ], array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf(UsersModule::ID)));

        $this->assertSame(['users:read'], $registry->tool('users_list')->scopes());
        $this->assertSame(['users:write'], $registry->tool('users_update')->scopes());
        $this->assertSame([UsersModule::VIEW, UsersModule::MANAGE], $registry->tool('users_get')->permissions());
        $this->assertSame([UsersModule::MANAGE], $registry->tool('users_delete')->permissions());
        $this->assertSame([UsersModule::SETTINGS], $registry->tool('users_settings_get')->permissions());
        $this->assertSame([UsersModule::SETTINGS], $registry->tool('users_settings_set')->permissions());

        foreach ($registry->toolsOf(UsersModule::ID) as $bound) {
            if ($bound->tool->mutating) {
                $this->assertArrayHasKey('dry_run', $bound->tool->inputSchema['properties'], $bound->fullName());
            }
        }

        $this->assertStringContainsString('personal data', $registry->tool('users_list')->tool->description);
        $this->assertContains('users://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_reads_and_is_refused_a_write_and_the_settings_are_their_own_permission(): void
    {
        $anna = $this->visitor();
        $reader = $this->administrator([UsersModule::VIEW]);

        $this->agent('users_list', [], $reader)->assertOk();
        $this->agent('users_get', ['user' => $anna->id], $reader)->assertOk();
        $this->agent('users_update', ['user' => $anna->id, 'first_name' => 'Boris'], $reader)->assertHasErrors(['[users.manage]']);
        $this->agent('users_settings_get', [], $reader)->assertHasErrors(['[users.settings]']);

        $keeper = $this->administrator([UsersModule::SETTINGS]);
        $this->agent('users_settings_get', [], $keeper)->assertOk();
        $this->agent('users_list', [], $keeper)->assertHasErrors(['[users.view]']);
    }

    #[Test]
    public function the_list_filters_as_the_panel_does_and_answers_group_codes(): void
    {
        $club = Group::query()->create(['code' => 'club', 'name' => 'Club']);
        $anna = $this->visitor(['first_name' => 'Anna', 'last_name' => 'Petrova', 'phone' => '+800 1234 5678']);
        $anna->forceFill(['email_verified_at' => now(), 'last_sign_in_at' => now()])->save();
        $anna->groups()->attach($club->id);
        $boris = $this->visitor(['first_name' => 'Boris', 'password' => null]);
        $boris->forceFill(['blocked_at' => now(), 'created_at' => now()->subYear()])->save();
        Identity::query()->create(['user_id' => $boris->id, 'provider' => 'google', 'provider_user_id' => 'g-1', 'email' => $boris->email]);

        $ids = fn (array $arguments): array => array_column($this->content($this->agent('users_list', $arguments))['users'], 'id');

        $this->assertSame([$anna->id], $ids(['search' => 'Petrova Anna']));
        $this->assertSame([$anna->id], $ids(['search' => '80012345']));
        $this->assertSame([$anna->id], $ids(['group' => 'club']));
        $this->assertSame([$anna->id], $ids(['verified' => true]));
        $this->assertSame([], $ids(['verified' => false, 'blocked' => false]));
        $this->assertSame([$boris->id], $ids(['blocked' => true]));
        $this->assertSame([$boris->id], $ids(['provider' => 'google']));
        $this->assertSame([$anna->id], $ids(['from' => now()->subDay()->toDateString()]));
        $this->assertSame([$anna->id, $boris->id], $ids(['sort' => '-last_sign_in_at']));

        $page = $this->content($this->agent('users_list', ['per_page' => 1]));
        $this->assertSame([2, 1, 2], [$page['total'], $page['per_page'], $page['last_page']]);

        $row = $this->content($this->agent('users_list', ['search' => 'Anna']))['users'][0];
        $this->assertSame(['club'], $row['groups']);
        $this->assertSame(['password'], $row['ways']);
        $this->assertSame('+800 1234 5678', $row['phone']);

        $this->agent('users_list', ['group' => 'vip'])->assertHasErrors(['There is no group vip. Known groups: club.']);
        $this->agent('users_list', ['sort' => 'password'])->assertHasErrors(['`sort` is one of']);
        $this->agent('users_list', ['from' => 'yesterday'])->assertHasErrors(['YYYY-MM-DD']);
    }

    #[Test]
    public function the_agent_and_the_panel_read_and_write_the_same_fields_and_groups(): void
    {
        Mail::fake();
        Group::query()->create(['code' => 'club', 'name' => 'Club']);
        Group::query()->create(['code' => 'wholesale', 'name' => 'Wholesale']);
        $anna = $this->visitor();

        // Written by the agent, read by the panel.
        $this->agent('users_update', [
            'user' => $anna->email,
            'first_name' => 'Anna',
            'fields' => ['club-card' => 'C-7'],
            'groups' => ['wholesale', 'club'],
        ])->assertOk();

        $panel = $this->asAdministrator()->getJson($this->api($anna->id))->assertOk()->json('data.values');
        $this->assertSame('Anna', $panel['first_name']);
        $this->assertSame('C-7', $panel['club-card']);
        $this->assertEqualsCanonicalizing(['club', 'wholesale'], $panel['groups']);

        // Written by the panel, read by the agent — under the same names.
        $this->putJson($this->api($anna->id), ['values' => ['club-card' => 'C-8', 'groups' => ['club']]])->assertOk();
        $got = $this->content($this->agent('users_get', ['user' => $anna->id]));
        $this->assertSame(['club-card' => 'C-8'], $got['fields']);
        $this->assertSame(['club'], $got['groups']);
        $this->assertSame('Anna', $got['profile']['first_name']);
        $this->assertSame($anna->email, $got['user']['email']);

        // The same rules: one card per number, one address per customer, known groups and fields only.
        $boris = $this->visitor();
        $this->agent('users_update', ['user' => $boris->id, 'fields' => ['club-card' => 'C-8']])->assertHasErrors(['club-card:']);
        $this->agent('users_update', ['user' => $boris->id, 'email' => $anna->email])->assertHasErrors(['Another customer signs in with this address.']);
        $this->agent('users_update', ['user' => $boris->id, 'groups' => ['vip']])->assertHasErrors(['There is no group vip. Known groups: club, wholesale.']);
        $this->agent('users_update', ['user' => $boris->id, 'fields' => ['shoe-size' => 42]])->assertHasErrors(['[shoe-size]']);
        $this->agent('users_update', ['user' => $boris->id, 'password' => 'never'])->assertHasErrors(['password']);
        $this->agent('users_get', ['user' => 999])->assertHasErrors(['Customer 999 does not exist.']);
        $this->agent('users_get', ['user' => 'nobody@example.test'])->assertHasErrors(['No customer signs in with nobody@example.test']);

        // Only what was sent changes.
        $this->agent('users_update', ['user' => $anna->id, 'last_name' => 'Petrova'])->assertOk();
        $this->assertSame('C-8', $anna->refresh()->extra('club-card'));
        $this->assertSame(['club'], $anna->groups()->pluck('code')->all());
    }

    #[Test]
    public function a_new_address_is_unconfirmed_and_the_block_shuts_the_door(): void
    {
        Mail::fake();
        $anna = $this->visitor();
        $anna->forceFill(['email_verified_at' => now()])->save();
        $old = $anna->email;

        $got = $this->content($this->agent('users_update', ['user' => $anna->id, 'email' => ' New@Example.test ', 'blocked' => true]));

        $this->assertSame('new@example.test', $got['user']['email']);
        $this->assertFalse($got['user']['verified']);
        $this->assertTrue($got['user']['blocked']);
        Mail::assertQueued(VerifyEmail::class, static fn (VerifyEmail $mail): bool => $mail->hasTo('new@example.test'));
        Mail::assertQueued(EmailChanged::class, static fn (EmailChanged $mail): bool => $mail->hasTo($old));

        $this->post('/account/sign-in', ['email' => 'new@example.test', 'password' => self::PASSWORD]);
        $this->assertGuest('site');

        $this->agent('users_update', ['user' => $anna->id, 'blocked' => false])->assertOk();
        $this->assertNull($anna->refresh()->blocked_at);
    }

    #[Test]
    public function a_customer_made_by_the_agent_has_no_password_and_is_invited_when_asked(): void
    {
        Mail::fake();
        Group::query()->create(['code' => 'club', 'name' => 'Club']);

        $made = $this->content($this->agent('users_create', ['email' => 'invited@example.test', 'first_name' => 'Ivan', 'groups' => ['club'], 'invite' => true]));

        $user = User::query()->findOrFail($made['user']['id']);
        $this->assertNull($user->password);
        $this->assertFalse($made['user']['has_password']);
        $this->assertSame(['club'], $made['groups']);
        Mail::assertQueued(Invitation::class, static fn (Invitation $mail): bool => $mail->hasTo('invited@example.test') && $mail->days === 7);

        $this->agent('users_create', ['email' => 'invited@example.test'])->assertHasErrors(['Another customer signs in with this address.']);
        $this->agent('users_create', ['email' => 'quiet@example.test'])->assertOk();
        Mail::assertQueuedCount(1);
    }

    #[Test]
    public function the_letters_are_sent_and_answered_without_a_link(): void
    {
        Mail::fake();
        $anna = $this->visitor();
        $boris = $this->visitor(['password' => null]);

        $this->assertSame(['sent' => true, 'letter' => 'password_reset', 'to' => $anna->email], $this->content($this->agent('users_send_password_reset', ['user' => $anna->id])));
        Mail::assertQueued(ResetPassword::class, static fn (ResetPassword $mail): bool => $mail->hasTo($anna->email));

        // An account without a password is invited to set one.
        $this->assertSame('invitation', $this->content($this->agent('users_send_password_reset', ['user' => $boris->id]))['letter']);
        Mail::assertQueued(Invitation::class);

        $this->agent('users_resend_verification', ['user' => $anna->id])->assertOk();
        Mail::assertQueued(VerifyEmail::class);

        $anna->forceFill(['email_verified_at' => now()])->save();
        $this->agent('users_resend_verification', ['user' => $anna->id])->assertHasErrors(['The address is confirmed already.']);

        $anna->forceFill(['blocked_at' => now()])->save();
        $this->agent('users_send_password_reset', ['user' => $anna->id])->assertHasErrors(['The customer is blocked: unblock them first.']);
        $this->agent('users_send_password_reset', ['user' => $anna->id, 'dry_run' => true])->assertHasErrors(['The customer is blocked']);
    }

    #[Test]
    public function the_last_way_to_sign_in_is_not_unlinked_by_the_agent_either(): void
    {
        config()->set('webx-users.social.keys.google', ['enabled' => true, 'client_id' => 'id', 'client_secret' => 'secret']);
        $boris = $this->visitor(['password' => null]);
        Identity::query()->create(['user_id' => $boris->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);

        $this->agent('users_unlink_provider', ['user' => $boris->id, 'provider' => 'google'])->assertHasErrors(['Set a password to unlink the last way to sign in.']);
        $this->agent('users_unlink_provider', ['user' => $boris->id, 'provider' => 'facebook'])->assertHasErrors(['This network is not linked.']);
        $this->agent('users_unlink_provider', ['user' => $boris->id, 'provider' => 'password'])->assertHasErrors(['A password is not unlinked']);

        $boris->forceFill(['password' => 'a-password-now'])->save();
        $this->agent('users_unlink_provider', ['user' => $boris->id, 'provider' => 'google', 'dry_run' => true])->assertOk();
        $this->assertSame(1, Identity::query()->count(), 'a dry run unlinks nothing');

        $ways = $this->content($this->agent('users_unlink_provider', ['user' => $boris->id, 'provider' => 'google']))['user']['ways'];
        $this->assertSame(['password'], array_column($ways, 'key'));
        $this->assertSame(0, Identity::query()->count());
    }

    #[Test]
    public function deleting_answers_the_warnings_of_the_modules_first(): void
    {
        Mail::fake();
        $this->app->make(DeletionWarnings::class)->register(static fn (): string => 'Orders stay without a customer.');
        $club = Group::query()->create(['code' => 'club', 'name' => 'Club']);
        $anna = $this->visitor();
        $anna->groups()->attach($club->id);

        $would = $this->content($this->agent('users_delete', ['user' => $anna->id, 'dry_run' => true]));
        $this->assertSame(['Orders stay without a customer.'], $would['warnings']);
        $this->assertNotNull(User::query()->find($anna->id));
        Mail::assertNothingQueued();

        $this->assertTrue($this->content($this->agent('users_delete', ['user' => $anna->id]))['deleted']);
        $this->assertNull(User::query()->find($anna->id));
        $this->assertSame(0, DB::table('users_group_user')->count());
    }

    #[Test]
    public function a_dry_run_writes_nothing_and_sends_nothing(): void
    {
        Mail::fake();
        Group::query()->create(['code' => 'club', 'name' => 'Club']);
        $anna = $this->visitor();

        $made = $this->content($this->agent('users_create', ['email' => 'new@example.test', 'groups' => ['club'], 'invite' => true, 'dry_run' => true]));
        $this->assertTrue($made['dry_run']);
        $this->assertNull($made['user']['id']);
        $this->assertSame(['club'], $made['groups']);

        $changed = $this->content($this->agent('users_update', ['user' => $anna->id, 'email' => 'other@example.test', 'groups' => ['club'], 'blocked' => true, 'dry_run' => true]));
        $this->assertSame('other@example.test', $changed['user']['email']);
        $this->assertTrue($changed['user']['blocked']);

        // A dry run is refused where the write would be.
        $this->agent('users_update', ['user' => $anna->id, 'email' => 'not-an-address', 'dry_run' => true])->assertHasErrors(['email:']);

        $this->agent('users_send_password_reset', ['user' => $anna->id, 'dry_run' => true])->assertOk();
        $this->agent('users_resend_verification', ['user' => $anna->id, 'dry_run' => true])->assertOk();
        $this->agent('users_groups_save', ['code' => 'wholesale', 'name' => 'Wholesale', 'dry_run' => true])->assertOk();
        $this->assertSame(0, $this->content($this->agent('users_groups_delete', ['code' => 'club', 'dry_run' => true]))['users_in_it']);

        $this->assertSame(1, User::query()->count());
        $this->assertSame($anna->email, $anna->refresh()->email);
        $this->assertNull($anna->blocked_at);
        $this->assertSame([], $anna->groups()->pluck('code')->all());
        $this->assertSame(['club'], Group::query()->pluck('code')->all());
        $this->assertSame(0, DB::table('users_password_resets')->count());
        Mail::assertNothingQueued();
    }

    #[Test]
    public function groups_are_saved_by_code_and_deleted_with_their_links(): void
    {
        $saved = $this->content($this->agent('users_groups_save', ['code' => 'Club', 'name' => 'Club']));
        $this->assertTrue($saved['created']);
        $this->assertSame('club', $saved['group']['code']);

        $anna = $this->visitor();
        $anna->groups()->attach($saved['group']['id']);

        $renamed = $this->content($this->agent('users_groups_save', ['code' => 'club', 'name' => 'Gold club', 'new_code' => 'club-gold']));
        $this->assertFalse($renamed['created']);
        $this->assertSame(['club-gold', 'Gold club', 1], [$renamed['group']['code'], $renamed['group']['name'], $renamed['group']['users_count']]);

        $this->agent('users_groups_save', ['code' => 'Not a code!', 'name' => 'X'])->assertHasErrors(['lowercase Latin letters']);
        $this->agent('users_groups_save', ['code' => 'vip', 'name' => 'VIP', 'new_code' => 'gold'])->assertHasErrors(['There is no group vip']);

        // The panel lists what the agent wrote, and the agent what the panel does.
        $this->assertSame(
            $this->asAdministrator()->getJson($this->api('groups'))->assertOk()->json('data'),
            $this->content($this->agent('users_groups_list'))['groups'],
        );

        $this->assertSame(1, $this->content($this->agent('users_groups_delete', ['code' => 'club-gold']))['users_taken_out']);
        $this->assertSame(0, Group::query()->count());
        $this->assertNotNull(User::query()->find($anna->id), 'the customer stays');
        $this->agent('users_groups_delete', ['code' => 'club-gold'])->assertHasErrors(['There is no group club-gold. Known groups: —.']);
    }

    #[Test]
    public function the_settings_are_read_and_set_without_a_secret(): void
    {
        ProviderSettings::query()->create(['key' => 'google', 'enabled' => false, 'options' => ['client_id' => 'google-id'], 'credentials' => ['client_secret' => self::SECRET]]);

        $settings = $this->content($this->agent('users_settings_get'));
        $google = self::provider($settings, 'google');

        $this->assertSame('open', $settings['registration']);
        $this->assertSame('optional', $settings['fields']['club-card']);
        $this->assertFalse($google['enabled']);
        $this->assertSame(['client_secret: set'], $google['credentials']);
        $this->assertSame(['client_id' => 'google-id'], $google['options']);

        $set = $this->content($this->agent('users_settings_set', [
            'values' => ['users.registration' => 'closed', 'users.fields.club-card' => 'required'],
            'providers' => ['google' => ['enabled' => true]],
        ]));

        $this->assertSame('closed', $set['registration']);
        $this->assertSame('required', $set['fields']['club-card']);
        $this->assertTrue(self::provider($set, 'google')['shown']);
        $this->get('/account/register')->assertNotFound();

        // Secrets are the panel's alone; nor does a setting the screen has not go through.
        $this->agent('users_settings_set', ['providers' => ['google' => ['client_secret' => 'new']]])->assertHasErrors(['set in the panel only']);
        $this->agent('users_settings_set', ['providers' => ['apple' => ['enabled' => true]]])->assertHasErrors(['There is no network apple. Known: google, facebook.']);
        $this->agent('users_settings_set', ['values' => ['users.password' => 'x']])->assertHasErrors(['[users.password]']);
        $this->agent('users_settings_set', ['values' => ['users.registration' => 'maybe']])->assertHasErrors(['users.registration:']);

        $this->agent('users_settings_set', ['values' => ['users.registration' => 'open'], 'dry_run' => true])->assertOk();
        $this->assertSame('closed', $this->content($this->agent('users_settings_get'))['registration']);
    }

    #[Test]
    public function the_catalog_is_made_of_the_registries(): void
    {
        Group::query()->create(['code' => 'club', 'name' => 'Club']);
        config()->set('webx-users.password_rules.min', 10);

        $catalog = ($this->resource('users://catalog')->handler)();

        $this->assertSame('open', $catalog['registration']);
        $this->assertSame(['first_name', 'last_name', 'phone', 'club-card'], array_column($catalog['fields'], 'name'));
        $card = $catalog['fields'][3];
        $this->assertSame(['wx-input', ['profile', 'panel'], true, false], [$card['type'], $card['placements'], $card['unique'], $card['visitor_editable']]);
        $this->assertSame(['google', 'facebook'], array_column($catalog['providers'], 'key'));
        $this->assertSame([['code' => 'club', 'name' => 'Club', 'users_count' => 0]], $catalog['groups']);
        $this->assertSame(['profile', 'security'], array_column($catalog['account_sections'], 'id'));
        $this->assertSame(10, $catalog['password']['min']);
        $this->assertSame(7, $catalog['links']['invitation_days']);
        $this->assertSame(5, $catalog['limits']['sign_in']);
    }

    #[Test]
    public function no_answer_and_no_line_of_the_journal_holds_a_secret(): void
    {
        Mail::fake();
        config()->set('webx-users.social.keys.google', ['enabled' => true, 'client_id' => 'id', 'client_secret' => self::SECRET]);
        ProviderSettings::query()->create(['key' => 'facebook', 'enabled' => true, 'options' => ['app_id' => 'facebook-id'], 'credentials' => ['app_secret' => self::SECRET]]);
        $anna = $this->visitor();
        $anna->forceFill(['remember_token' => 'remember-me-token-1234'])->save();
        Identity::query()->create(['user_id' => $anna->id, 'provider' => 'google', 'provider_user_id' => 'g-provider-id-77', 'email' => $anna->email]);

        $answers = [
            $this->agent('users_list'),
            $this->agent('users_get', ['user' => $anna->id]),
            $this->agent('users_update', ['user' => $anna->id, 'first_name' => 'Anna']),
            $this->agent('users_send_password_reset', ['user' => $anna->id]),
            $this->agent('users_resend_verification', ['user' => $anna->id]),
            $this->agent('users_settings_get'),
            $this->agent('users_settings_set', ['providers' => ['facebook' => ['enabled' => true]]]),
            $this->agent('users_create', ['email' => 'invited@example.test', 'invite' => true]),
        ];

        $said = implode("\n", [
            ...array_map(fn ($answer): string => (string) json_encode($this->content($answer)), $answers),
            (string) json_encode(($this->resource('users://catalog')->handler)()),
            (string) json_encode(DB::table('mcp_calls')->get()->all()),
        ]);

        $tokens = [];
        Mail::assertQueued(ResetPassword::class, static function (ResetPassword $mail) use (&$tokens): bool {
            $tokens[] = basename((string) parse_url($mail->link, PHP_URL_PATH));

            return true;
        });

        foreach ([self::SECRET, (string) $anna->getAuthPassword(), 'remember-me-token-1234', 'g-provider-id-77', 'provider_user_id', 'remember_token', ...$tokens] as $secret) {
            $this->assertStringNotContainsString($secret, $said);
        }

        $this->assertStringContainsString($anna->email, $said, 'an address is what users.view gives');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function provider(array $settings, string $key): array
    {
        foreach ((array) $settings['providers'] as $card) {
            if (is_array($card) && ($card['key'] ?? null) === $key) {
                return $card;
            }
        }

        self::fail("No provider [{$key}].");
    }
}
