<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Settings\Contacts\Hours;
use WebxUi\Settings\Contacts\PhoneNumber;
use WebxUi\Settings\Settings;

/**
 * WIDGETS §12.1, §16: the "Contacts" tab — numbers in E.164 and refused without a country code,
 * the hours' status at the edges of an interval, on a day off, on a special date and in another
 * zone; what the site reads through `contacts()`.
 */
final class ContactsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_tab_is_on_the_settings_screen(): void
    {
        $names = array_column(app(ScreenRegistry::class)->fields(Settings::SCREEN), 'name');

        foreach (Contacts::KEYS as $key) {
            $this->assertContains($key, $names);
        }
    }

    #[Test]
    public function a_number_is_shown_as_typed_and_dialled_in_e164(): void
    {
        $number = PhoneNumber::parse('+44 (20) 7946-0958');
        $this->assertNotNull($number);
        $this->assertSame('+44 (20) 7946-0958', $number->number);
        $this->assertSame('+442079460958', $number->e164);
        $this->assertSame('tel:+44-20-7946-0958', $number->href);

        // 00 dials out in most of the world, and is the same number.
        $this->assertSame('+442079460958', PhoneNumber::parse('0044 20 7946 0958')?->e164);
        // An extension goes into the link, which E.164 has no room for.
        $this->assertSame('tel:+1-202-555-0143;ext=12', PhoneNumber::parse('+1 202 555 0143 ext. 12')?->href);

        // Without the country code there is no number: the site's language does not say which country.
        $this->assertNull(PhoneNumber::parse('020 7946 0958'));
        $this->assertSame(PhoneNumber::NO_COUNTRY, PhoneNumber::problem('020 7946 0958'));
        $this->assertSame(PhoneNumber::INVALID, PhoneNumber::problem('+44 12'));
        $this->assertSame(PhoneNumber::INVALID, PhoneNumber::problem('call us'));
        $this->assertNull(PhoneNumber::problem('+380 44 393 08 10'));
    }

    #[Test]
    public function saving_a_number_without_its_country_code_asks_for_it(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => [
                'contacts.phones' => [['number' => '+44 20 7946 0958'], ['number' => '020 7946 0321']],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contacts.phones.1.number']);

        // Under the row and the field, where the panel shows it.
        $this->assertSame(
            '020 7946 0321 has no country code. Start it with + and the code: +44 20 7946 0958.',
            $response->json('errors')['contacts.phones.1.number'][0],
        );

        $this->assertSame([], contacts()->phones());

        // The same check over MCP: an agent asked to change the number gets told what to add.
        $tool = $this->app->make(ToolRegistry::class)->tool('settings_set')->tool->handler;
        $refused = $tool(['key' => 'contacts.phones', 'value' => [['number' => '7946 0958']]]);
        $this->assertFalse($refused['ok']);
        $this->assertArrayHasKey('contacts.phones.0.number', $refused['errors']);

        $saved = $tool(['key' => 'contacts.timezone', 'value' => 'Mars/Olympus']);
        $this->assertFalse($saved['ok']);
    }

    #[Test]
    public function the_site_reads_its_contacts_on_the_current_language(): void
    {
        app(Settings::class)->save([
            'contacts.phones' => [
                ['number' => '+1 202 555 0143', 'label' => ['ru' => 'Поддержка', 'uk' => 'Підтримка'], 'primary' => false, 'messengers' => []],
                ['number' => '+44 20 7946 0958', 'label' => ['ru' => 'Продажи'], 'primary' => true, 'messengers' => ['whatsapp', 'telegram', 'viber', 'signal']],
            ],
            'contacts.emails' => [['email' => 'hello@example.com', 'label' => null], ['email' => 'not an address', 'label' => null]],
            'contacts.addresses' => [['address' => ['ru' => 'Пример-стрит, 1'], 'latitude' => 51.5074, 'longitude' => -0.1278, 'map' => null, 'primary' => true]],
            'contacts.messengers' => [['channel' => 'telegram', 'url' => 'https://t.me/example_bot', 'label' => null]],
            'contacts.socials' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/example', 'label' => null]],
        ]);

        $contacts = contacts();
        $this->assertCount(2, $contacts->phones());
        $this->assertSame('+442079460958', $contacts->primaryPhone()?->e164);
        $this->assertSame('Продажи', $contacts->primaryPhone()->label);
        $this->assertSame('Підтримка', $contacts->phones('uk')[0]->label);

        $this->assertSame([
            'https://wa.me/442079460958',
            'https://t.me/+442079460958',
            'viber://chat?number=%2B442079460958',
            'https://signal.me/#p/+442079460958',
        ], array_map(static fn ($channel): string => $channel->url, $contacts->primaryPhone()->messengers));

        // The number's chats first, then the channels of their own — the quick-contact list.
        $this->assertSame(['whatsapp', 'telegram', 'viber', 'signal', 'telegram'], array_map(static fn ($chat): string => $chat->kind, $contacts->chats()));

        $this->assertSame(['hello@example.com'], array_map(static fn ($email): string => $email->address, $contacts->emails()));
        $this->assertSame('Пример-стрит, 1', $contacts->primaryAddress()?->text);
        $this->assertSame('https://www.openstreetmap.org/?mlat=51.5074&mlon=-0.1278#map=17/51.5074/-0.1278', $contacts->primaryAddress()->mapUrl());
        $this->assertSame('instagram', $contacts->socials()[0]->kind);
        $this->assertFalse($contacts->isEmpty());
    }

    #[Test]
    public function the_hours_say_open_or_closed_at_the_edges_of_an_interval(): void
    {
        $hours = $this->week('Europe/Berlin');
        $berlin = static fn (string $at): CarbonImmutable => CarbonImmutable::parse($at, 'Europe/Berlin');

        // Monday 2026-10-12.
        $this->assertSame('today', $hours->status($berlin('2026-10-12 08:59'))->kind());
        $this->assertSame('09:00', $hours->status($berlin('2026-10-12 08:59'))->next?->format('H:i'));
        $this->assertTrue($hours->openNow($berlin('2026-10-12 09:00')));
        $this->assertSame('13:00', $hours->status($berlin('2026-10-12 09:00'))->until?->format('H:i'));

        // The break: closed at 13:00 sharp, open again at 14:00.
        $this->assertFalse($hours->openNow($berlin('2026-10-12 13:00')));
        $this->assertSame('today', $hours->status($berlin('2026-10-12 13:30'))->kind());
        $this->assertTrue($hours->openNow($berlin('2026-10-12 14:00')));
        $this->assertTrue($hours->openNow($berlin('2026-10-12 18:59')));
        $this->assertFalse($hours->openNow($berlin('2026-10-12 19:00')));
        $this->assertSame('tomorrow', $hours->status($berlin('2026-10-12 19:00'))->kind());

        // Friday evening, Saturday off, Sunday off: "Monday at 9".
        $this->assertSame('later', $hours->status($berlin('2026-10-16 20:00'))->kind());
        $this->assertSame('off-later', $hours->status($berlin('2026-10-17 12:00'))->kind());
        $this->assertSame('off-tomorrow', $hours->status($berlin('2026-10-18 12:00'))->kind());
        $this->assertSame('2026-10-19 09:00', $hours->status($berlin('2026-10-18 12:00'))->next?->format('Y-m-d H:i'));
    }

    #[Test]
    public function a_special_date_replaces_its_weekday(): void
    {
        $hours = Hours::fromSettings(
            [['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '18:00']],
            [
                ['date' => '2026-12-25', 'closed' => true, 'label' => 'Christmas'],
                ['date' => '2026-12-24', 'closed' => false, 'opens' => '09:00', 'closes' => '13:00'],
            ],
            'Europe/London',
        );
        $london = static fn (string $at): CarbonImmutable => CarbonImmutable::parse($at, 'Europe/London');

        $this->assertFalse($hours->openNow($london('2026-12-25 10:00')));
        $this->assertSame('off-later', $hours->status($london('2026-12-25 10:00'))->kind());
        $this->assertSame('2026-12-28', $hours->status($london('2026-12-25 10:00'))->next?->format('Y-m-d'));
        $this->assertSame('13:00', $hours->status($london('2026-12-24 10:00'))->until?->format('H:i'));
        $this->assertFalse($hours->openNow($london('2026-12-24 14:00')));
        $this->assertSame(['2026-12-24', '2026-12-25'], array_keys($hours->upcoming($london('2026-12-20 10:00'), 10)));
        $this->assertSame('Christmas', $hours->exceptions()['2026-12-25']['label']);
    }

    #[Test]
    public function the_status_is_in_the_sites_zone_not_the_visitors(): void
    {
        $hours = $this->week('America/New_York');

        // 14:30 in Berlin is 08:30 in New York: not open yet there.
        $moment = CarbonImmutable::parse('2026-10-12 14:30', 'Europe/Berlin');
        $this->assertFalse($hours->openNow($moment));
        $this->assertSame('today', $hours->status($moment)->kind());
        $this->assertSame('09:00 America/New_York', $hours->status($moment)->next?->format('H:i e'));

        // An unknown zone is the application's, not an exception on every page.
        $this->assertSame('UTC', Hours::zone('Mars/Olympus')->getName());
    }

    #[Test]
    public function past_midnight_round_the_clock_and_the_night_the_clocks_change(): void
    {
        $night = Hours::fromSettings([['days' => ['fri', 'sat'], 'opens' => '22:00', 'closes' => '02:00']], [], 'Europe/Berlin');
        $berlin = static fn (string $at): CarbonImmutable => CarbonImmutable::parse($at, 'Europe/Berlin');

        // Saturday at one in the morning is Friday night.
        $this->assertTrue($night->openNow($berlin('2026-10-17 01:00')));
        $this->assertSame('2026-10-17 02:00', $night->status($berlin('2026-10-17 01:00'))->until?->format('Y-m-d H:i'));
        $this->assertFalse($night->openNow($berlin('2026-10-18 02:00')));

        $always = Hours::fromSettings([['days' => array_keys(Hours::DAYS), 'opens' => '00:00', 'closes' => '00:00']], [], 'UTC');
        $this->assertSame('always', $always->status(CarbonImmutable::parse('2026-10-14 03:00', 'UTC'))->kind());

        // 25 October 2026: Berlin's clocks go back at 03:00; nine is still nine.
        $sunday = Hours::fromSettings([['days' => ['sun'], 'opens' => '09:00', 'closes' => '17:00']], [], 'Europe/Berlin');
        $this->assertSame('09:00', $sunday->status($berlin('2026-10-25 00:30'))->next?->format('H:i'));
        $this->assertSame('today', $sunday->status($berlin('2026-10-25 00:30'))->kind());
    }

    #[Test]
    public function the_week_folds_into_rows_of_the_same_hours(): void
    {
        $this->assertSame([
            ['days' => [1, 2, 3, 4, 5], 'intervals' => [[540, 780], [840, 1140]]],
            ['days' => [6, 7], 'intervals' => []],
        ], $this->week('UTC')->rows());
    }

    #[Test]
    public function contacts_kept_under_a_key_of_the_sites_own_are_read_and_moved(): void
    {
        $this->app['config']->set('webx-settings.contacts.legacy.phones', 'contacts.phone');
        // A key of a project's patch: stored, read back as stored.
        app(Settings::class)->save(['contacts.phone' => '+44 20 7946 0958', 'contacts.email' => 'hello@example.com']);

        $this->assertSame('+442079460958', contacts()->primaryPhone()?->e164);

        $this->artisan('webx:settings:contacts', ['--from' => ['contacts.phone', 'contacts.email']])->assertSuccessful();

        $raw = app(Settings::class)->raw();
        $this->assertSame('+44 20 7946 0958', $raw['contacts.phones'][0]['number']);
        $this->assertSame('hello@example.com', $raw['contacts.emails'][0]['email']);

        // Again: nothing to move twice.
        $this->artisan('webx:settings:contacts', ['--from' => ['contacts.phone']])->expectsOutputToContain('Nothing to move')->assertSuccessful();

        // A number without its code is not moved, and the command says why.
        app(Settings::class)->save(['contacts.phone' => '020 7946 0321']);
        $this->artisan('webx:settings:contacts', ['--from' => ['contacts.phone']])->assertFailed();
    }

    private function week(string $zone): Hours
    {
        return Hours::fromSettings([
            ['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '13:00'],
            ['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '14:00', 'closes' => '19:00'],
        ], [], $zone);
    }
}
