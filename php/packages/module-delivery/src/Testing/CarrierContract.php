<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Testing;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\MethodType;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\PointKinds;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\Page;
use WebxUi\Delivery\Sync\PointRecord;
use WebxUi\Delivery\Sync\StreetRecord;
use WebxUi\Geography\LocalityKind;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Geography\Normalizer;

/**
 * The contract of a carrier's satellite (§13.3), for its PHPUnit test — on the answers of the
 * carrier's API recorded once, never on the live API:
 *
 *     final class CarrierContractTest extends TestCase
 *     {
 *         use CarrierContract;
 *
 *         protected function carrier(): Carrier { return new CarrierA(new RecordedApi(__DIR__.'/answers')); }
 *
 *         protected function methodTypes(): array { return [new BranchType, new LockerType, new CourierType]; }
 *     }
 *
 * What the base trusts a satellite with, checked here rather than found on a client's site: the
 * key and the countries; every directory walked from the first page to a `null` cursor, each
 * page the same when asked again from its cursor — the sync repeats a page that failed (§7.1);
 * records of the directory's own class, with ids unique in a pass and names the normalizer keeps
 * something of (§4.2); points and streets that point at the carrier's own towns; and types named
 * after the carrier, a point type with its kinds.
 */
trait CarrierContract
{
    /** The satellite's carrier, answering from the recorded answers. */
    abstract protected function carrier(): Carrier;

    /**
     * The satellite's method types.
     *
     * @return list<MethodType>
     */
    abstract protected function methodTypes(): array;

    /** Pages beyond which a directory is taken for a cursor that never ends. */
    protected function maxPages(): int
    {
        return 1000;
    }

    #[Test]
    public function the_carrier_names_itself_and_its_countries(): void
    {
        $carrier = $this->carrier();

        $this->assertMatchesRegularExpression('/^[a-z0-9-]{1,32}$/', $carrier->key(), 'The key of a carrier: lowercase letters, digits and dashes, at most 32.');
        $this->assertNotSame('store', $carrier->key(), '«store» is the shop itself.');
        $this->assertNotSame('', trim($carrier->title('en')));
        $this->assertNotSame([], $carrier->countries());

        foreach ($carrier->countries() as $country) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $country, 'Countries are ISO 3166-1 alpha-2 codes.');
        }

        foreach ([...$carrier->settings(), ...$carrier->credentials()] as $field) {
            $this->assertIsString($field['name'] ?? null, 'Every field of the settings and the keys has a name.');
        }
    }

    #[Test]
    public function the_types_are_the_carriers(): void
    {
        $carrier = $this->carrier();
        $types = $this->methodTypes();
        $this->assertNotSame([], $types);

        foreach ($types as $type) {
            $this->assertStringStartsWith($carrier->key().'.', $type->key(), 'A type is named after its carrier: carrier-a.branch.');
            $this->assertSame($carrier->key(), $type->carrier());

            if ($type->asks() === Asks::Point) {
                $this->assertNotSame([], $type->pointKinds(), "{$type->key()} asks for a point and names no kind of points.");

                foreach ($type->pointKinds() as $kind) {
                    $this->assertMatchesRegularExpression(PointKinds::KEY, $kind);
                }
            }
        }
    }

    #[Test]
    public function every_directory_runs_to_its_end_and_a_page_repeats(): void
    {
        $carrier = $this->carrier();

        if (! $carrier instanceof SyncsDirectories) {
            $this->markTestSkipped('The carrier keeps no directories.');
        }

        $directories = array_map(static fn ($schedule): Directory => $schedule->directory, $carrier->directories());
        $this->assertContains(Directory::Localities, $directories, 'Points and streets are bound by the carrier\'s towns: its localities come first.');

        $localities = [];

        foreach ($directories as $directory) {
            $ids = [];

            foreach ($this->walk($carrier, $directory) as [$cursor, $page]) {
                $again = $carrier->pull($directory, $cursor);
                $this->assertEquals($page, $again, "The page of {$directory->value} at cursor «{$cursor}» came back different: a failed page is pulled again from its cursor.");

                foreach ($page->records as $record) {
                    $id = $this->checkRecord($carrier, $directory, $record, $localities);
                    $this->assertArrayNotHasKey($id, $ids, "{$directory->value}: «{$id}» twice in one pass.");
                    $ids[$id] = true;

                    if ($directory === Directory::Localities) {
                        $localities[$id] = true;
                    }
                }
            }
        }
    }

    /**
     * Every page of a directory with the cursor it was pulled from.
     *
     * @return list<array{0: string|null, 1: Page}>
     */
    private function walk(SyncsDirectories $carrier, Directory $directory): array
    {
        $pages = [];
        $cursor = null;

        do {
            $page = $carrier->pull($directory, $cursor);
            $pages[] = [$cursor, $page];
            // A directory of one page ends at once: null after null is its end, not a loop.
            if ($page->next !== null) {
                $this->assertNotSame($cursor, $page->next, "{$directory->value}: the cursor «{$cursor}» points at itself.");
            }
            $cursor = $page->next;
            $this->assertLessThanOrEqual($this->maxPages(), count($pages), "{$directory->value}: no end after {$this->maxPages()} pages.");
        } while ($cursor !== null);

        return $pages;
    }

    /**
     * @param  array<string, true>  $localities  the carrier's towns of this pass
     * @return string the record's id
     */
    private function checkRecord(Carrier $carrier, Directory $directory, mixed $record, array $localities): string
    {
        $class = match ($directory) {
            Directory::Localities => LocalityRecord::class,
            Directory::Points => PointRecord::class,
            Directory::Streets => StreetRecord::class,
        };
        $this->assertInstanceOf($class, $record, "{$directory->value} brings {$class}.");
        /** @var LocalityRecord|PointRecord|StreetRecord $record */
        $this->assertNotSame('', trim($record->externalId));

        if ($record instanceof LocalityRecord) {
            $this->assertContains($record->country, $carrier->countries(), "Town {$record->externalId} is in a country the carrier does not name.");
            $this->assertNotSame('', Normalizer::key($record->name), "Town {$record->externalId}: nothing is left of the name «{$record->name}».");
            $this->assertContains($record->kind, LocalityKind::values(), "Town {$record->externalId}: «{$record->kind}» is not a type of locality.");
        }

        if ($record instanceof PointRecord) {
            $this->assertMatchesRegularExpression(PointKinds::KEY, $record->kind);
            $this->assertNotSame('', trim($record->address), "Point {$record->externalId} has no address.");
        }

        if ($record instanceof StreetRecord) {
            $this->assertNotSame('', Normalizer::key($record->name), "Street {$record->externalId}: nothing is left of the name «{$record->name}».");
            $this->assertContains($record->kind, StreetRecord::KINDS);
        }

        if (! $record instanceof LocalityRecord) {
            $this->assertArrayHasKey($record->localityExternalId, $localities, "{$directory->value} {$record->externalId} points at town {$record->localityExternalId}, which the carrier's localities do not have.");
        }

        return $record->externalId;
    }
}
