<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Settings\Models\Setting;

/**
 * The lists of the "Contacts" tab, written the way an agent filling a site wrote them: rows of
 * its own guessing were kept with the guessed keys dropped, and it was told they were saved.
 */
#[DefineEnvironment('everyPart')]
final class ContactListsTest extends TestCase
{
    use RefreshDatabase;

    protected function everyPart(Application $app): void
    {
        $app['config']->set('webx-settings.uses', ['contacts' => ['phones', 'emails', 'addresses', 'hours', 'messengers', 'socials']]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function set(array $arguments): array
    {
        /** @var array<string, mixed> */
        return ($this->app->make(ToolRegistry::class)->tool('settings_set')->tool->handler)($arguments);
    }

    /**
     * @return iterable<string, array{string, list<array<string, mixed>>, string}>
     */
    public static function wrongRows(): iterable
    {
        yield 'an address under a key of its own' => ['contacts.addresses', [['street' => ['uk' => 'Street 1'], 'primary' => true]], 'contacts.addresses.0.street'];
        yield 'an address with no text' => ['contacts.addresses', [['latitude' => 50.45, 'longitude' => 30.52]], 'contacts.addresses.0.address'];
        yield 'a latitude in words' => ['contacts.addresses', [['address' => ['uk' => 'Street 1'], 'latitude' => 'north', 'longitude' => 30.52]], 'contacts.addresses.0.latitude'];
        yield 'a latitude off the globe' => ['contacts.addresses', [['address' => ['uk' => 'Street 1'], 'latitude' => 120, 'longitude' => 30.52]], 'contacts.addresses.0.latitude'];
        yield 'half a pair of coordinates' => ['contacts.addresses', [['address' => ['uk' => 'Street 1'], 'latitude' => 50.45]], 'contacts.addresses.0.longitude'];
        yield 'a map that is not a link' => ['contacts.addresses', [['address' => ['uk' => 'Street 1'], 'map' => 'somewhere']], 'contacts.addresses.0.map'];
        yield 'a phone as "phone"' => ['contacts.phones', [['phone' => '+800 1234 5678']], 'contacts.phones.0.phone'];
        yield 'a messenger nobody offers' => ['contacts.phones', [['number' => '+800 1234 5678', 'messengers' => ['pager']]], 'contacts.phones.0.messengers'];
        yield 'a day by its full name' => ['contacts.hours', [['days' => ['monday'], 'opens' => '09:00', 'closes' => '18:00']], 'contacts.hours.0.days'];
        yield 'a time that is not HH:MM' => ['contacts.hours', [['days' => ['mon'], 'opens' => '9am', 'closes' => '18:00']], 'contacts.hours.0.opens'];
        yield 'an hour past the day' => ['contacts.hours', [['days' => ['mon'], 'opens' => '09:00', 'closes' => '25:00']], 'contacts.hours.0.closes'];
        yield 'a network nobody offers' => ['contacts.socials', [['network' => 'myspace', 'url' => 'https://example.com/me']], 'contacts.socials.0.network'];
        yield 'a profile that is not a link' => ['contacts.socials', [['network' => 'instagram', 'url' => 'example']], 'contacts.socials.0.url'];
        yield 'a social under a key of its own' => ['contacts.socials', [['network' => 'instagram', 'link' => 'https://www.instagram.com/example']], 'contacts.socials.0.link'];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    #[Test]
    #[DataProvider('wrongRows')]
    public function a_row_of_the_wrong_shape_is_refused_under_its_row_and_key(string $key, array $rows, string $path): void
    {
        $refused = $this->set(['key' => $key, 'value' => $rows]);

        $this->assertFalse($refused['ok']);
        $this->assertArrayHasKey($path, $refused['errors']);
        $this->assertFalse(Setting::query()->where('key', $key)->exists());

        // The panel's form goes through the same checks.
        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/settings', ['values' => [$key => $rows]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$path]);
    }

    #[Test]
    public function rows_in_the_shape_of_the_screen_are_kept(): void
    {
        $rows = [
            'contacts.addresses' => [['address' => ['uk' => 'Street 1'], 'latitude' => 50.45, 'longitude' => 30.52, 'map' => 'https://maps.example.com/?q=1', 'primary' => true]],
            'contacts.phones' => [['number' => '+800 1234 5678', 'label' => ['uk' => 'Sales'], 'primary' => true, 'messengers' => ['telegram']]],
            'contacts.hours' => [['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '18:00']],
            'contacts.socials' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/example']],
        ];

        foreach ($rows as $key => $value) {
            $saved = $this->set(['key' => $key, 'value' => $value]);

            $this->assertTrue($saved['ok'], $key.': '.json_encode($saved['errors'] ?? null));
            $this->assertTrue($saved['applied']);
        }

        $this->assertSame($rows['contacts.addresses'], settings()->raw()['contacts.addresses']);
        $this->assertSame('+800 1234 5678', contacts()->phones()[0]->number);
        $this->assertSame('https://www.instagram.com/example', contacts()->socials()[0]->url);
    }

    #[Test]
    public function the_tools_say_what_one_row_takes(): void
    {
        $list = ($this->app->make(ToolRegistry::class)->tool('settings_list')->tool->handler)([]);
        $addresses = array_values(array_filter($list, static fn (array $one): bool => $one['key'] === 'contacts.addresses'))[0];

        $this->assertSame(['address', 'latitude', 'longitude', 'map', 'primary'], array_keys($addresses['item']));
        $this->assertSame(['type' => 'wx-textarea', 'localized' => true, 'value' => 'string', 'maxlength' => 300], $addresses['item']['address']);
        $this->assertSame(['type' => 'wx-input-number', 'value' => 'number', 'min' => -90, 'max' => 90], $addresses['item']['latitude']);
        $this->assertSame('URL', $addresses['item']['map']['value']);

        $hours = ($this->app->make(ToolRegistry::class)->tool('settings_get')->tool->handler)(['key' => 'contacts.hours']);
        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], $hours['item']['days']['options']);
        $this->assertSame('time, HH:MM', $hours['item']['opens']['value']);

        // A setting that is not a list has no rows to describe.
        $this->assertArrayNotHasKey('item', ($this->app->make(ToolRegistry::class)->tool('settings_get')->tool->handler)(['key' => 'contacts.timezone']));
    }
}
