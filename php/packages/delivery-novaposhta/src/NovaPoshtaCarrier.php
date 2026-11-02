<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta;

use WebxUi\Delivery\ConnectionResult;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\Directories\Localities;
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
 * The settings here are the ones typed or chosen from a fixed list. The sender, its contact
 * person and the branch of sending come from the carrier's account and directories through the
 * base's `ProvidesSettingOptions` together with the waybill (§4.3, §5; stage 7 of §17).
 *
 * Its directories (§6) are pulled with the key stored in the panel, one request a page, paced by
 * {@see Pace}: the towns from `getCities` — the carrier leads Ukraine (decision 8), so the base
 * creates the shared towns and regions from them; points and streets come with stages 5 and 6.
 */
final class NovaPoshtaCarrier implements Carrier, SyncsDirectories
{
    public const string KEY = 'novaposhta';

    /** The languages the package speaks: a label of a field is given in all of them. */
    public const array LOCALES = ['en', 'uk', 'ru', 'de', 'pl', 'fr', 'es', 'it', 'pt', 'tr'];

    /** Records a page of a directory: `Limit` 500 is what the carrier gives on the key (stage 3). */
    public const int PAGE_SIZE = 500;

    /** Nightly, towns first; the base pulls the points after the towns succeed (§6.1). */
    public const string NIGHTLY = '10 2 * * *';

    public function __construct(
        private readonly Client $client,
        private readonly AccountKey $key,
        private readonly Pace $pace,
        private readonly Locales $locales,
        private readonly int $pageSize = self::PAGE_SIZE,
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
        ];
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
        return [new DirectorySchedule(Directory::Localities, self::NIGHTLY, $this->pageSize)];
    }

    /**
     * A page of a directory. The cursor is the number of the last page pulled (§6.2): '3' asks
     * for page 4; the list ends on a page that is empty or shorter than the limit.
     */
    public function pull(Directory $directory, ?string $cursor): Page
    {
        if ($directory !== Directory::Localities) {
            throw CarrierUnavailable::badResponse("Nova Poshta keeps no {$directory->value} directory yet.");
        }

        if ($cursor !== null && preg_match('/^\d{1,6}$/', $cursor) !== 1) {
            throw CarrierUnavailable::badResponse("The cursor «{$cursor}» is not a page of Nova Poshta.");
        }

        $key = $this->key->get();

        if ($key === null) {
            // Nothing goes to the carrier without a key: the run fails with «the key was refused».
            throw CarrierUnavailable::keyRejected('No API key of Nova Poshta is stored.');
        }

        $number = $cursor === null ? 1 : (int) $cursor + 1;
        $this->pace->wait();
        $answer = $this->client->call($key, 'Address', 'getCities', ['Page' => $number, 'Limit' => $this->pageSize]);
        $entries = $answer->records();
        $records = (new Localities($this->locales->codes()))->records($entries);

        return new Page($records, $entries === [] || count($entries) < $this->pageSize ? null : (string) $number);
    }

    public function client(): Client
    {
        return $this->client;
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
