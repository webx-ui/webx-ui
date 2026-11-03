<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\Directories\Localities;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\Types\BranchType;
use WebxUi\Delivery\NovaPoshta\Types\CourierType;
use WebxUi\Delivery\NovaPoshta\Types\LockerType;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Testing\CarrierContract;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Localization\Locales;

/**
 * The base's contract of a satellite (§13.2) on the answers recorded on the key (stage 3): the
 * towns' first, second and last pages and the page past the end, cut to 20 records each — so the
 * carrier is asked for pages of 20 here, and the walk ends on the empty page as it would on the key.
 * The points are the recorded answer of a small town of the last page — seven points, one page.
 */
final class CarrierContractTest extends TestCase
{
    use CarrierContract;

    /** The recorded page the carrier gives for each `Page` asked. */
    private const array PAGES = [
        '1' => 'Address.getCities.page-1',
        '2' => 'Address.getCities.page-2',
        '3' => 'Address.getCities.last-page',
    ];

    protected function carrier(): Carrier
    {
        Http::fake([self::URL => static function (Request $request) {
            $page = (string) ($request['methodProperties']['Page'] ?? '');

            if ($request['calledMethod'] === 'getWarehouses') {
                return Http::response(self::fixture($page === '1' ? 'Address.getWarehouses.small-city' : 'Address.getWarehouses.network-past-the-end'));
            }

            return Http::response(self::fixture(self::PAGES[$page] ?? 'Address.getCities.past-the-end'));
        }]);

        return new NovaPoshtaCarrier(
            $this->app->make(Client::class),
            new AccountKey(self::KEY),
            new Pace(0),
            $this->app->make(Locales::class),
            pageSize: 20,
        );
    }

    protected function methodTypes(): array
    {
        return [new BranchType, new LockerType, new CourierType];
    }

    #[Test]
    public function no_name_keeps_a_type_prefix_or_the_qualifier_in_brackets(): void
    {
        $carrier = $this->carrier();
        $this->assertInstanceOf(NovaPoshtaCarrier::class, $carrier);
        $cursor = null;
        $names = 0;

        do {
            $page = $carrier->pull(Directory::Localities, $cursor);

            foreach ($page->records as $record) {
                $this->assertInstanceOf(LocalityRecord::class, $record);
                $this->assertDoesNotMatchRegularExpression(Localities::PREFIX, $record->name, "A type prefix is left in «{$record->name}».");
                $this->assertStringNotContainsString('(', $record->name);
                $this->assertSame('UA', $record->country);
                $names++;
            }

            $cursor = $page->next;
        } while ($cursor !== null);

        // Three recorded pages of 20, then the empty one ends the walk.
        $this->assertSame(60, $names);
        Http::assertSentCount(4);
        $this->assertSame(['getCities'], array_values(array_unique(array_column($this->journalEntries(), 'method'))));
    }
}
