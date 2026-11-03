<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta;

use WebxUi\Delivery\CarrierLocalities;
use WebxUi\Delivery\ConnectionResult;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\NamesPointLines;
use WebxUi\Delivery\Contracts\NamesSkipReasons;
use WebxUi\Delivery\Contracts\ProvidesSettingOptions;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\Directories\Localities;
use WebxUi\Delivery\NovaPoshta\Directories\Points;
use WebxUi\Delivery\NovaPoshta\Directories\Streets;
use WebxUi\Delivery\NovaPoshta\Sending\SenderOptions;
use WebxUi\Delivery\NovaPoshta\Waybills\Waybills;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\DirectorySchedule;
use WebxUi\Delivery\Sync\Page;
use WebxUi\Delivery\Sync\SyncFailure;
use WebxUi\Localization\Locales;

/**
 * Nova Poshta (`novaposhta`), the national carrier of Ukraine (§4): its key, the settings of the
 * sender and of a waybill, the check of the connection and its mark.
 *
 * The sender and its contact person come from the carrier's account, the town and the branch of
 * sending from the directories the base keeps — the base's `ProvidesSettingOptions` (§4.3, §5,
 * {@see SenderOptions}); the rest is typed or chosen from a fixed list.
 *
 * Its directories (§6) are pulled with the key stored in the panel, one request a page, paced by
 * {@see Pace}: the towns from `getCities` — the carrier leads Ukraine (decision 8), so the base
 * creates the shared towns and regions from them; then the points from `getWarehouses`, the whole
 * network page by page (§6.3); weekly, the streets of every town with a courier from `getStreet`,
 * town by town (§6.4) — the one directory a page of which is many requests.
 */
final class NovaPoshtaCarrier implements Carrier, NamesPointLines, NamesSkipReasons, ProvidesSettingOptions, SyncsDirectories
{
    public const string KEY = 'novaposhta';

    /** The languages the package speaks: a label of a field is given in all of them. */
    public const array LOCALES = ['en', 'uk', 'ru', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    /** Records a page of a directory: `Limit` 500 is what the carrier gives on the key (stage 3). */
    public const int PAGE_SIZE = 500;

    /** Nightly, towns first; the base pulls the points after the towns succeed (§6.1). */
    public const string NIGHTLY = '10 2 * * *';

    /** Streets once a week, the night from Sunday to Monday: the longest pass, ~3 hours (§6.4). */
    public const string WEEKLY = '30 0 * * 0';

    /** Streets a page of the base: rows are narrow (§6.1). */
    public const int STREETS_PAGE_SIZE = 2000;

    /** Towns read from the base at a time for the walk of the streets (§6.4). */
    public const int TOWNS_AT_A_TIME = 200;

    public function __construct(
        private readonly Client $client,
        private readonly AccountKey $key,
        private readonly Pace $pace,
        private readonly Locales $locales,
        private readonly int $pageSize = self::PAGE_SIZE,
        private readonly CarrierLocalities $towns = new CarrierLocalities,
        private readonly StreetsPass $streets = new StreetsPass,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(string $locale): string
    {
        return self::word('carrier.title', $locale);
    }

    /** The mark from the package's own assets (decision 13): a data URL, nothing to publish or route. */
    public function icon(): ?string
    {
        $svg = @file_get_contents(__DIR__.'/../resources/icons/novaposhta.svg');

        return $svg === false ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function countries(): array
    {
        return ['UA'];
    }

    public function settings(): array
    {
        return [
            ['name' => 'sender', 'type' => 'select', 'options' => 'carrier', 'label' => self::words('settings.sender')],
            ['name' => 'contact', 'type' => 'select', 'options' => 'carrier', 'depends' => ['sender'], 'label' => self::words('settings.contact')],
            ['name' => 'sender_city', 'type' => 'select', 'options' => 'carrier', 'search' => true, 'label' => self::words('settings.sender_city')],
            ['name' => 'sender_point', 'type' => 'select', 'options' => 'carrier', 'depends' => ['sender_city'], 'search' => true, 'label' => self::words('settings.sender_point')],
            [
                'name' => 'payment_method',
                'type' => 'select',
                'default' => 'Cash',
                'label' => self::words('settings.payment_method'),
                'options' => self::options('settings.payment_methods', ['Cash', 'NonCash']),
            ],
            [
                'name' => 'cargo_type',
                'type' => 'select',
                'default' => 'Parcel',
                'label' => self::words('settings.cargo_type'),
                'options' => self::options('settings.cargo_types', ['Parcel', 'Cargo', 'Documents']),
            ],
            [
                'name' => 'weight',
                'type' => 'number',
                'default' => 0.5,
                'min' => 0.1,
                'step' => 0.1,
                'precision' => 1,
                'label' => self::words('settings.weight'),
            ],
            [
                'name' => 'description',
                'type' => 'text',
                'default' => self::word('settings.description_default', 'uk'),
                'label' => self::words('settings.description'),
            ],
            // The seat of a parcel locker by default (decision 17): the locker takes up to these.
            ...array_map(static fn (string $name, int $most): array => [
                'name' => $name,
                'type' => 'number',
                'min' => 1,
                'max' => $most,
                'step' => 1,
                'precision' => 0,
                'label' => self::words('settings.'.$name),
            ], array_keys(Waybills::LOCKER_SIZE), Waybills::LOCKER_SIZE),
        ];
    }

    /**
     * The sender and its contact person from the account (reading only), the town and the branch
     * of sending from the base's directories (§4.3).
     */
    public function settingOptions(string $field, array $values, array $credentials, ?string $query = null): array
    {
        $key = is_string($credentials['api_key'] ?? null) ? trim($credentials['api_key']) : '';

        if ($key === '' && in_array($field, ['sender', 'contact'], true)) {
            throw CarrierUnavailable::keyRejected('No API key of Nova Poshta is given.');
        }

        return app(SenderOptions::class)->options($field, $values, $key, $query, app()->getLocale());
    }

    public function credentials(): array
    {
        return [['name' => 'api_key', 'type' => 'secret', 'label' => self::words('credentials.api_key')]];
    }

    /** The cheapest call of the account (§4.4): its senders, first page. Reads, never writes. */
    public function testConnection(array $credentials): ConnectionResult
    {
        $key = is_string($credentials['api_key'] ?? null) ? trim($credentials['api_key']) : '';

        if ($key === '') {
            return ConnectionResult::failed(self::word('connection.no_key'));
        }

        try {
            $answer = $this->client->call($key, 'Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => 1]);
        } catch (CarrierUnavailable $failure) {
            return ConnectionResult::failed(self::word($failure->failure === SyncFailure::KeyRejected ? 'connection.key_refused' : 'connection.no_answer'));
        }

        $senders = $answer->totalCount ?? count($answer->records());

        if ($senders === 0) {
            return ConnectionResult::failed(self::word('connection.no_sender'));
        }

        return ConnectionResult::ok((string) __('webx-delivery-novaposhta::novaposhta.connection.ok', ['count' => $senders]));
    }

    public function directories(): array
    {
        return [
            new DirectorySchedule(Directory::Localities, self::NIGHTLY, $this->pageSize),
            new DirectorySchedule(Directory::Points, self::NIGHTLY, $this->pageSize),
            new DirectorySchedule(Directory::Streets, $this->streets->cron, self::STREETS_PAGE_SIZE),
        ];
    }

    /**
     * A page of a directory. For towns and points the cursor is the number of the last page pulled
     * (§6.2, §6.3): '3' asks for page 4; the list ends on a page that is empty or shorter than the
     * limit. Points are the whole network without `CityRef` — a 303 to a ready file is followed by
     * the client. Streets go town by town, a page of them many requests ({@see Streets()}).
     */
    public function pull(Directory $directory, ?string $cursor): Page
    {
        $streets = $directory === Directory::Streets;

        if ($cursor !== null && preg_match($streets ? self::STREETS_CURSOR : '/^\d{1,6}$/', $cursor) !== 1) {
            throw CarrierUnavailable::badResponse("The cursor «{$cursor}» is not a page of Nova Poshta.");
        }

        $key = $this->key->get();

        if ($key === null) {
            // Nothing goes to the carrier without a key: the run fails with «the key was refused».
            throw CarrierUnavailable::keyRejected('No API key of Nova Poshta is stored.');
        }

        if ($streets) {
            return $this->streets($key, $cursor);
        }

        $number = $cursor === null ? 1 : (int) $cursor + 1;
        $this->pace->wait();
        $method = $directory === Directory::Points ? 'getWarehouses' : 'getCities';
        $answer = $this->client->call($key, 'Address', $method, ['Page' => $number, 'Limit' => $this->pageSize]);
        $entries = $answer->records();
        $next = $entries === [] || count($entries) < $this->pageSize ? null : (string) $number;

        if ($directory === Directory::Points) {
            $points = new Points($this->locales->codes());

            return new Page($points->records($entries), $next, $points->skipped());
        }

        return new Page((new Localities($this->locales->codes()))->records($entries), $next);
    }

    /** «<town>:<pages>»: the carrier's town and how many of its pages are pulled, 0 — all of them. */
    private const string STREETS_CURSOR = '/^([^:\s]{1,64}):(\d{1,5})$/';

    /**
     * A page of the streets (§6.4): the towns with a courier from the base, by their ids in order,
     * and `getStreet` of each with an empty search — all its streets, page by page of `Limit`; a
     * town ends on a page shorter than that. One pull goes on across towns until it has a page of
     * the base, or has made {@see StreetsPass::$requests} requests, or has run
     * {@see StreetsPass::$seconds} — so the base repeating a failed page repeats that much and no
     * more, and a pull never outlasts a slice of the queue's job.
     *
     * The cursor is the last town begun and how many of its pages are in: «<town>:3» goes on with
     * its page 4, «<town>:0» with the town after it. The pass ends when no town is left. A sample
     * ({@see StreetsPass::$sample}) walks only that many first towns — the pass on somebody
     * else's key, which must not spend its limits on 10 000 towns.
     */
    private function streets(string $key, ?string $cursor): Page
    {
        $town = null;
        $page = 1;
        $after = null;

        if ($cursor !== null) {
            preg_match(self::STREETS_CURSOR, $cursor, $match);
            $after = $match[1];

            if ((int) $match[2] > 0) {
                $town = $match[1];
                $page = (int) $match[2] + 1;
            }
        }

        $queue = [];
        $records = [];
        $requests = 0;
        $started = microtime(true);
        $next = $cursor;
        $limit = max(1, $this->streets->requests);

        while ($requests < $limit && count($records) < self::STREETS_PAGE_SIZE) {
            if ($requests > 0 && microtime(true) - $started >= $this->streets->seconds) {
                break;
            }

            if ($town === null) {
                $queue = $queue === [] ? $this->townsAfter($after) : $queue;

                if ($queue === []) {
                    return new Page($records, null);
                }

                $town = array_shift($queue);
                $page = 1;
            }

            $this->pace->wait();
            $answer = $this->client->call($key, 'Address', 'getStreet', [
                'CityRef' => $town,
                'FindByString' => '',
                'Page' => $page,
                'Limit' => $this->pageSize,
            ]);
            $requests++;
            $entries = $answer->records();
            array_push($records, ...Streets::records($town, $entries));

            if (count($entries) < $this->pageSize) {
                $next = $town.':0';
                $after = $town;
                $town = null;
            } else {
                $next = $town.':'.$page;
                $page++;
            }
        }

        return new Page($records, $next);
    }

    /**
     * The next towns with a courier after the given one, by the carrier's ids: one query to the
     * base for up to {@see TOWNS_AT_A_TIME}. A sample is the first towns of the whole list.
     *
     * @return list<string>
     */
    private function townsAfter(?string $after): array
    {
        if ($this->streets->sample === null) {
            return $this->towns->externalIds(self::KEY, $after, self::TOWNS_AT_A_TIME, 'courier');
        }

        $sample = $this->towns->externalIds(self::KEY, null, max(1, $this->streets->sample), 'courier');

        return array_values(array_filter($sample, static fn (string $id): bool => $after === null || strcmp($id, $after) > 0));
    }

    public function client(): Client
    {
        return $this->client;
    }

    /**
     * The carrier writes the street into a point's name («Відділення №21: вул. Дмитра Луценка, 8»):
     * under it the address only repeats it. A name without the street (no colon) gets the town —
     * the part of the address before its first comma.
     */
    public function pointLine(string $title, string $address): ?string
    {
        if (str_contains($title, ':')) {
            return null;
        }

        $town = trim(explode(',', $address, 2)[0]);

        return $town === '' ? null : $town;
    }

    /** The reasons {@see Points} leaves a point out for, as the screen of the carriers says them. */
    public function skipReason(string $reason, int $count, string $locale): ?string
    {
        $key = 'webx-delivery-novaposhta::novaposhta.remarks.'.$reason;
        $line = __($key, ['count' => $count], $locale);

        return is_string($line) && $line !== $key ? $line : null;
    }

    public static function word(string $key, ?string $locale = null): string
    {
        return (string) __('webx-delivery-novaposhta::novaposhta.'.$key, [], $locale);
    }

    /**
     * One line in every language of the package, as a field's label is given.
     *
     * @return array<string, string>
     */
    public static function words(string $key): array
    {
        $words = [];

        foreach (self::LOCALES as $locale) {
            $words[$locale] = self::word($key, $locale);
        }

        return $words;
    }

    /**
     * The options of a fixed select in the language of the request.
     *
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    private static function options(string $group, array $values): array
    {
        return array_map(static fn (string $value): array => ['value' => $value, 'label' => self::word($group.'.'.$value)], $values);
    }
}
