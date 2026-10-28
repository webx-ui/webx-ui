<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Users\Doctor\FieldsCheck;
use WebxUi\Users\Fields\FieldCatalog;
use WebxUi\Users\Fields\FieldValues;
use WebxUi\Users\Fields\Placement;
use WebxUi\Users\Fields\UserField;
use WebxUi\Users\Fields\UserFields;
use WebxUi\Users\Models\User;

/**
 * The fields of the site on its visitors (spec §10): one declaration in the config reaches the
 * registration, the profile, the panel's screen and the description MCP gives; whether it must
 * be filled in is a setting of the site, not code; a field that cannot work is refused.
 */
final class FieldsTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuthServiceProvider::class, McpServiceProvider::class, SettingsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-users.fields', [
            'birthday' => ['type' => 'wx-date-picker', 'label' => 'Date of birth', 'rules' => ['before:today']],
            'club-card' => [
                'type' => 'wx-input', 'label' => 'Club card', 'unique' => true, 'visitorEditable' => false,
                'placements' => ['profile', 'panel'], 'rules' => ['regex:/^[0-9]{6,12}$/'],
            ],
            'size' => ['type' => 'wx-select', 'label' => 'Size', 'props' => ['options' => ['S', 'M', ['value' => 'L', 'label' => 'Large']]], 'position' => 50],
        ]);
    }

    #[Test]
    public function a_field_of_the_config_is_asked_in_the_registration_after_the_built_in_ones_and_kept_in_extra(): void
    {
        $html = (string) $this->get('/account/register')->assertOk()->getContent();

        preg_match_all('~<(?:input|select|textarea)[^>]*name="([a-z][a-z_]*|fields\[[a-z-]+\])"~', $html, $names);
        // `size` comes first by its position; the club card is not of the registration.
        $this->assertSame(['back', 'first_name', 'last_name', 'phone', 'email', 'password', 'fields[size]', 'fields[birthday]'], $names[1]);
        $this->assertStringContainsString('Date of birth', $html);
        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('>Large<', $html);

        $this->post('/account/register', [
            'email' => 'anna@example.test',
            'password' => self::PASSWORD,
            'fields' => ['birthday' => '1990-04-01', 'size' => 'L', 'club-card' => '123456'],
        ])->assertRedirect();

        $anna = User::query()->sole();
        // Nothing of a field that is not of the place is kept, whoever sends it.
        $this->assertSame(['size' => 'L', 'birthday' => '1990-04-01'], $anna->extraRaw());
        $this->assertSame('1990-04-01', $anna->extra('birthday'));
    }

    #[Test]
    public function the_fields_are_checked_by_their_type_and_their_own_rules_with_the_built_in_ones_at_once(): void
    {
        $this->postJson('/account/register', [
            'email' => 'anna@example.test',
            'password' => 'short',
            'fields' => ['birthday' => '2999-01-01', 'size' => 'XXL'],
        ])->assertStatus(422)->assertJsonValidationErrors(['password', 'fields.birthday', 'fields.size']);

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function the_profile_shows_a_field_the_visitor_may_not_change_and_drops_it_from_what_they_send(): void
    {
        $anna = $this->visitor(['extra' => ['club-card' => '123456', 'birthday' => '1990-04-01']]);
        $values = $this->app->make(FieldValues::class);

        $controls = array_column($values->controls(Placement::Profile, $anna), null, 'name');
        $this->assertSame(['fields.size', 'fields.birthday', 'fields.club-card'], array_keys($controls));
        $this->assertTrue($controls['fields.club-card']['readonly']);
        $this->assertSame('123456', $controls['fields.club-card']['value']);
        $this->assertFalse($controls['fields.birthday']['readonly']);
        $this->assertSame('1990-04-01', $controls['fields.birthday']['value']);

        $stored = $values->validate(['birthday' => '1991-05-02', 'club-card' => '999999'], Placement::Profile, $anna);
        $this->assertSame(['size' => null, 'birthday' => '1991-05-02'], $stored);
        $this->assertArrayNotHasKey('fields.club-card', $values->formRules(Placement::Profile));
    }

    #[Test]
    public function the_panel_gets_nodes_and_mcp_a_description_from_the_same_declaration(): void
    {
        $catalog = $this->app->make(FieldCatalog::class);

        $nodes = array_column($catalog->nodes(), null, 'name');
        $this->assertSame(['size', 'birthday', 'club-card'], array_keys($nodes));
        $this->assertSame(['id' => 'field-club-card', 'type' => 'wx-input', 'name' => 'club-card', 'label' => 'Club card'], $nodes['club-card']);

        $described = array_column($catalog->describe(), null, 'name');
        $this->assertSame(['first_name', 'last_name', 'phone', 'size', 'birthday', 'club-card'], array_keys($described));
        $this->assertSame([
            'name' => 'club-card',
            'type' => 'wx-input',
            'label' => 'Club card',
            'placements' => ['profile', 'panel'],
            'requirement' => 'optional',
            'unique' => true,
            'visitor_editable' => false,
            'source' => 'site',
            'built_in' => false,
        ], $described['club-card']);

        // The panel and an agent send what they change, and that is checked by the same rules.
        $values = $this->app->make(FieldValues::class);
        $this->assertSame(['club-card' => '654321'], $values->validate(['club-card' => '654321'], Placement::Panel, whole: false, prefix: ''));

        try {
            $values->validate(['club-card' => 'abc'], Placement::Panel, whole: false, prefix: '');
            $this->fail('A value against the field rules went through.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('club-card', $exception->errors());
        }
    }

    #[Test]
    public function whether_a_field_is_asked_is_a_setting_not_code(): void
    {
        $settings = $this->app->make(Settings::class);

        $settings->save([
            'users.fields.birthday' => 'required',
            'users.fields.size' => 'hidden',
            'users.fields.first_name' => 'hidden',
            'users.fields.phone' => 'required',
        ]);

        $html = (string) $this->get('/account/register')->assertOk()->getContent();
        $this->assertStringNotContainsString('name="fields[size]"', $html);
        $this->assertStringNotContainsString('name="first_name"', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*name="fields\[birthday\]"[^>]*required~', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*name="phone"[^>]*required~', $html);

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'first_name' => 'Anna', 'fields' => ['size' => 'M']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'fields.birthday'])
            ->assertJsonMissingValidationErrors(['first_name', 'fields.size']);

        $this->post('/account/register', [
            'email' => 'anna@example.test', 'password' => self::PASSWORD, 'first_name' => 'Anna', 'phone' => '+800 1234 5678',
            'fields' => ['birthday' => '1990-04-01', 'size' => 'M'],
        ])->assertRedirect();

        // A hidden field is not kept, whatever is sent.
        $anna = User::query()->sole();
        $this->assertNull($anna->first_name);
        $this->assertSame(['birthday' => '1990-04-01'], $anna->extraRaw());

        // The panel's node says required too; the setting changes back without code.
        $nodes = array_column($this->app->make(FieldCatalog::class)->nodes(), null, 'name');
        $this->assertTrue($nodes['birthday']['props']['required']);

        $settings->save(['users.fields.birthday' => 'optional']);
        $nodes = array_column($this->app->make(FieldCatalog::class)->nodes(), null, 'name');
        $this->assertArrayNotHasKey('props', $nodes['birthday']);
    }

    #[Test]
    public function a_field_is_required_by_its_declaration_until_the_setting_says_otherwise(): void
    {
        $this->app->make(UserFields::class)->register(new UserField('company', 'wx-input', 'Company', required: true, source: 'b2b'));

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.company']);

        $this->app->make(Settings::class)->save(['users.fields.company' => 'optional']);

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => self::PASSWORD])->assertOk();
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function a_unique_value_of_another_visitor_is_refused_and_ones_own_is_not(): void
    {
        $anna = $this->visitor(['extra' => ['club-card' => '123456']]);
        $values = $this->app->make(FieldValues::class);

        try {
            $values->validate(['club-card' => '123456'], Placement::Panel, $this->visitor(), whole: false, prefix: '');
            $this->fail('A taken value went through.');
        } catch (ValidationException $exception) {
            $this->assertSame(['club-card' => ['This value is already taken.']], $exception->errors());
        }

        $this->assertSame(['club-card' => '123456'], $values->validate(['club-card' => '123456'], Placement::Panel, $anna, whole: false, prefix: ''));
    }

    #[Test]
    public function index_field_writes_a_migration_of_the_site_whose_column_the_unique_check_uses(): void
    {
        $directory = database_path('migrations');
        File::ensureDirectoryExists($directory);
        $before = File::glob($directory.'/*_index_users_extra_club_card.php');
        array_map(File::delete(...), $before);

        $notes = array_filter($this->app->make(FieldsCheck::class)->run(), static fn (Diagnosis $diagnosis): bool => $diagnosis->note);
        $this->assertCount(1, $notes);

        try {
            $this->assertSame(0, Artisan::call('webx:users:index-field', ['name' => 'club-card']));
            $written = File::glob($directory.'/*_index_users_extra_club_card.php');
            $this->assertCount(1, $written);
            // Twice is refused: one migration a field.
            $this->assertSame(1, Artisan::call('webx:users:index-field', ['name' => 'club-card']));
            $this->assertSame(1, Artisan::call('webx:users:index-field', ['name' => 'nope']));

            $migration = require $written[0];
            $migration->up();

            $this->assertTrue(Schema::hasColumn('users', 'extra_club_card'));
            $anna = $this->visitor(['extra' => ['club-card' => '123456']]);
            $this->assertSame('123456', User::query()->whereKey($anna->id)->value('extra_club_card'));

            $field = $this->app->make(UserFields::class)->get('club-card');
            $this->assertNotNull($field);
            $this->assertTrue($this->app->make(FieldValues::class)->taken($field, '123456'));
            $this->assertFalse($this->app->make(FieldValues::class)->taken($field, '123456', $anna));
            $this->assertSame([], array_filter($this->app->make(FieldsCheck::class)->run(), static fn (Diagnosis $diagnosis): bool => $diagnosis->note));

            $migration->down();
            $this->assertFalse(Schema::hasColumn('users', 'extra_club_card'));
        } finally {
            array_map(File::delete(...), File::glob($directory.'/*_index_users_extra_club_card.php'));
        }
    }
}
