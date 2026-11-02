<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\Directories\Localities;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\SyncFailure;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Localization\Locales;

/** The towns of `getCities` (§6.2) in the base's shape, and the cursor over their pages. */
final class LocalitiesTest extends TestCase
{
    private function carrier(?string $key = self::KEY, int $pageSize = 500): NovaPoshtaCarrier
    {
        return new NovaPoshtaCarrier(
            $this->app->make(Client::class),
            new AccountKey($key),
            new Pace(0),
            $this->app->make(Locales::class),
            pageSize: $pageSize,
        );
    }

    /**
     * @param  list<string>  $locales
     * @return list<LocalityRecord>
     */
    private static function records(string $fixture, array $locales = ['en', 'uk']): array
    {
        /** @var list<array<string, mixed>> $data */
        $data = self::fixture($fixture)['data'];

        return (new Localities($locales))->records($data);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function entry(array $fields = []): array
    {
        return [
            'Description' => 'Приклад',
            'DescriptionRu' => 'Пример',
            'Ref' => '00000000-0000-4000-8000-000000000001',
            'Delivery1' => '0', 'Delivery2' => '0', 'Delivery3' => '0', 'Delivery4' => '0',
            'Delivery5' => '0', 'Delivery6' => '0', 'Delivery7' => '0',
            'Area' => '00000000-0000-4000-8000-000000000002',
            'CityID' => '1',
            'SettlementTypeDescription' => 'село',
            'AreaDescription' => 'Полтавська',
            'AreaDescriptionRu' => 'Полтавская',
            ...$fields,
        ];
    }

    #[Test]
    public function a_recorded_town_comes_without_its_qualifier_with_its_type_region_and_days(): void
    {
        $town = self::records('Address.getCities.page-1')[0];

        $this->assertSame('fc5f1e3c-928e-11e9-898c-005056b24375', $town->externalId);
        $this->assertSame('UA', $town->country);
        $this->assertSame('Абазівка', $town->name);
        $this->assertSame('village', $town->kind);
        $this->assertSame('Полтавська', $town->region);
        // The district of the qualifier tells namesakes apart; the area part is the region.
        $this->assertSame('Полтавський р-н', $town->district);
        $this->assertNull($town->lat);
        // Only the languages of the site: the site speaks en and uk, the carrier uk and ru.
        $this->assertSame(['uk' => 'Абазівка'], $town->names);
        $this->assertSame(['uk' => 'Полтавська'], $town->regionNames);
        $this->assertSame(['courier'], $town->features);
        $this->assertSame([
            'kind' => 'село',
            'area_ref' => '71508137-9b87-11de-822f-000c2965ae0e',
            'city_id' => '2667',
            'qualifier' => 'Полтавський р-н, Полтавська обл',
        ], $town->meta);
    }

    #[Test]
    public function every_recorded_type_is_one_of_the_bases_and_the_days_give_the_courier(): void
    {
        $records = [
            ...self::records('Address.getCities.page-1'),
            ...self::records('Address.getCities.page-2'),
            ...self::records('Address.getCities.last-page'),
        ];
        $kinds = array_count_values(array_map(static fn (LocalityRecord $record): string => $record->kind, $records));
        ksort($kinds);

        $this->assertSame(['city' => 5, 'township' => 2, 'village' => 53], $kinds);
        $byName = [];

        foreach ($records as $record) {
            $byName[$record->name.' '.($record->meta['qualifier'] ?? '')] = $record;
        }

        // All seven days «0» — the carrier does not go to doors there.
        $this->assertSame([], $byName['Авангард Дніпропетровська обл.']->features);
        $this->assertSame(['courier'], $byName['Авангард ']->features);
        $this->assertSame('township', $byName['Авангард ']->kind);
        $this->assertSame('Шум\'яч', $byName['Шум\'яч ']->name);
    }

    #[Test]
    public function the_capital_is_a_region_of_its_own(): void
    {
        [$capital, $namesake] = self::records('Address.getCities.find-by-string', ['uk', 'ru']);

        $this->assertSame(Localities::CAPITAL, $capital->externalId);
        $this->assertSame('Київ', $capital->name);
        $this->assertSame('city', $capital->kind);
        $this->assertSame('Київ', $capital->region);
        $this->assertSame(['uk' => 'Київ', 'ru' => 'Киев'], $capital->names);
        $this->assertSame(['uk' => 'Київ', 'ru' => 'Киев'], $capital->regionNames);
        // A town whose name starts like the capital's is in its own area.
        $this->assertSame('Львівська', $namesake->region);
        $this->assertSame(['uk' => 'Львівська', 'ru' => 'Львовская'], $namesake->regionNames);
    }

    #[Test]
    public function a_type_prefix_and_the_word_for_the_area_are_taken_off(): void
    {
        $parse = static fn (array $fields): ?LocalityRecord => (new Localities(['uk']))->record(self::entry($fields));

        $this->assertSame('Київ', $parse(['Description' => 'м. Київ'])?->name);
        $this->assertSame('Ворзель', $parse(['Description' => 'смт Ворзель'])?->name);
        $this->assertSame('Ворзель', $parse(['Description' => 'с.м.т. Ворзель (Бучанський р-н)'])?->name);
        $this->assertSame('Приклад', $parse(['Description' => 'с.Приклад'])?->name);
        // A name that only begins with the letters of a prefix is a name.
        $this->assertSame('Смтарівка', $parse(['Description' => 'Смтарівка'])?->name);
        $this->assertSame('Селище', $parse(['Description' => 'Селище'])?->name);
        $this->assertSame('Полтавська', $parse(['AreaDescription' => 'Полтавська область'])?->region);
        $this->assertSame('Полтавська', $parse(['AreaDescription' => 'Полтавська обл.'])?->region);
    }

    #[Test]
    public function an_unknown_type_is_other_with_the_carriers_word_and_a_town_without_a_ref_is_left_out(): void
    {
        $localities = new Localities(['uk']);

        $other = $localities->record(self::entry(['SettlementTypeDescription' => 'хутір']));
        $this->assertSame('other', $other?->kind);
        $this->assertSame('хутір', $other?->meta['kind'] ?? null);
        $this->assertSame('city', $localities->record(self::entry(['SettlementTypeDescription' => 'Місто']))?->kind);

        $this->assertSame([], $localities->records([self::entry(['Ref' => '']), self::entry(['Description' => ' '])]));
    }

    #[Test]
    public function the_cursor_is_the_last_page_pulled_and_a_short_page_ends_the_list(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(self::answer(array_fill(0, 3, self::entry()), total: 7))
            ->push(self::answer(array_fill(0, 3, self::entry()), total: 7))
            ->push(self::answer([self::entry()], total: 7))
            ->push(self::answer([], total: 7)),
        ]);
        $carrier = $this->carrier(pageSize: 3);

        $first = $carrier->pull(Directory::Localities, null);
        $fourth = $carrier->pull(Directory::Localities, '3');
        $short = $carrier->pull(Directory::Localities, '4');
        $empty = $carrier->pull(Directory::Localities, '5');

        $this->assertSame(['1', '4', null, null], [$first->next, $fourth->next, $short->next, $empty->next]);
        $sent = [];
        Http::assertSent(static function (Request $request) use (&$sent): bool {
            $sent[] = [$request['modelName'], $request['calledMethod'], $request['methodProperties']];

            return $request['apiKey'] === self::KEY;
        });
        $this->assertSame([
            ['Address', 'getCities', ['Page' => '1', 'Limit' => '3']],
            ['Address', 'getCities', ['Page' => '4', 'Limit' => '3']],
            ['Address', 'getCities', ['Page' => '5', 'Limit' => '3']],
            ['Address', 'getCities', ['Page' => '6', 'Limit' => '3']],
        ], $sent);
    }

    #[Test]
    public function the_directories_are_the_towns_and_the_points_nightly_then_the_streets_weekly(): void
    {
        $directories = $this->carrier()->directories();

        $this->assertSame([Directory::Localities, Directory::Points, Directory::Streets], array_map(static fn ($schedule) => $schedule->directory, $directories));
        $this->assertSame(['10 2 * * *', '10 2 * * *', '30 0 * * 0'], array_map(static fn ($schedule) => $schedule->cron, $directories));
        $this->assertSame([500, 500, 2000], array_map(static fn ($schedule) => $schedule->pageSize, $directories));
    }

    #[Test]
    public function without_a_stored_key_nothing_is_sent(): void
    {
        Http::fake();

        try {
            $this->carrier(key: null)->pull(Directory::Localities, null);
            $this->fail('A pull without a key was expected to fail.');
        } catch (CarrierUnavailable $failure) {
            $this->assertSame(SyncFailure::KeyRejected, $failure->failure);
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function the_key_is_the_one_the_panel_stored(): void
    {
        Http::fake([self::URL => Http::response(self::answer([], total: 0))]);
        DeliveryCarrier::query()->create(['key' => NovaPoshtaCarrier::KEY, 'credentials' => ['api_key' => ' stored-key ']]);

        $this->app->make(NovaPoshtaCarrier::class)->pull(Directory::Localities, null);

        Http::assertSent(static fn (Request $request): bool => $request['apiKey'] === 'stored-key');
        $this->assertSame('stored-key', (new AccountKey)->get());
        $this->assertSame('override', (new AccountKey(' override '))->get());
    }

    #[Test]
    public function a_cursor_that_is_not_a_page_sends_nothing(): void
    {
        Http::fake();

        foreach ([[Directory::Localities, 'L7'], [Directory::Points, 'P1'], [Directory::Streets, '7']] as [$directory, $cursor]) {
            try {
                $this->carrier()->pull($directory, $cursor);
                $this->fail("{$directory->value} at «{$cursor}» was expected to fail.");
            } catch (CarrierUnavailable $failure) {
                $this->assertSame(SyncFailure::BadResponse, $failure->failure);
            }
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function the_pace_keeps_the_requests_apart(): void
    {
        $pace = new Pace(1200);
        $started = microtime(true);
        $pace->wait();
        $pace->wait();
        $pace->wait();

        // 1 200 a minute is one every 50 ms: the second and the third wait.
        $this->assertGreaterThanOrEqual(0.095, microtime(true) - $started);

        $free = new Pace(0);
        $started = microtime(true);
        $free->wait();
        $free->wait();
        $this->assertLessThan(0.02, microtime(true) - $started);
    }
}
