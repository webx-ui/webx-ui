<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Settings\Settings;

/**
 * The site's contacts as data (WIDGETS §12.1): the "Contacts" tab of the settings, read by the
 * widgets, the SEO markup and the theme's templates — one place to change a phone number rather
 * than five templates. `contacts()->primaryPhone()`, `contacts()->hours()->openNow()`.
 *
 * Words of a row ("Sales") are on the current language, or on the one asked for. What does not
 * read as a contact — a number without its country code saved before the check, an address of a
 * network that is not a link — is skipped, so a template never prints a link that leads nowhere.
 *
 * A site that kept its contacts under keys of its own (`contacts.phone`, a project's patch) names
 * them in `webx-settings.contacts.legacy` and they are read while the new list is empty, until
 * `php artisan webx:settings:contacts --from=<key>` moves them over.
 */
final class Contacts
{
    public const array KEYS = [
        'phones' => 'contacts.phones',
        'emails' => 'contacts.emails',
        'addresses' => 'contacts.addresses',
        'hours' => 'contacts.hours',
        'exceptions' => 'contacts.hours-exceptions',
        'timezone' => 'contacts.timezone',
        'messengers' => 'contacts.messengers',
        'socials' => 'contacts.socials',
    ];

    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly Config $config,
    ) {}

    /** @return list<Phone> */
    public function phones(?string $locale = null): array
    {
        return $this->memo('phones', $locale, function () use ($locale): array {
            $phones = [];

            foreach ($this->rows('phones', $locale) as $row) {
                $number = PhoneNumber::parse(self::text($row['number'] ?? null) ?? '');

                if ($number === null) {
                    continue;
                }

                $messengers = [];

                foreach ((array) ($row['messengers'] ?? []) as $kind) {
                    $channel = is_string($kind) ? Channel::forNumber($kind, $number) : null;

                    if ($channel !== null) {
                        $messengers[] = $channel;
                    }
                }

                $phones[] = new Phone($number->number, $number->e164, $number->href, self::text($row['label'] ?? null), ($row['primary'] ?? false) === true, $messengers);
            }

            return $phones;
        });
    }

    /** The number marked main, or the first: a site with numbers always has a main one. */
    public function primaryPhone(?string $locale = null): ?Phone
    {
        $phones = $this->phones($locale);

        foreach ($phones as $phone) {
            if ($phone->primary) {
                return $phone;
            }
        }

        return $phones[0] ?? null;
    }

    /** @return list<Email> */
    public function emails(?string $locale = null): array
    {
        return $this->memo('emails', $locale, function () use ($locale): array {
            $emails = [];

            foreach ($this->rows('emails', $locale) as $row) {
                $address = self::text($row['email'] ?? null);

                if ($address !== null && filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                    $emails[] = new Email($address, self::text($row['label'] ?? null));
                }
            }

            return $emails;
        });
    }

    /** @return list<Address> */
    public function addresses(?string $locale = null): array
    {
        return $this->memo('addresses', $locale, function () use ($locale): array {
            $addresses = [];

            foreach ($this->rows('addresses', $locale) as $row) {
                $text = self::text($row['address'] ?? null);
                $latitude = self::coordinate($row['latitude'] ?? null, 90);
                $longitude = self::coordinate($row['longitude'] ?? null, 180);

                if ($text === null) {
                    continue;
                }

                $map = self::text($row['map'] ?? null);
                $addresses[] = new Address(
                    $text,
                    $latitude !== null && $longitude !== null ? $latitude : null,
                    $latitude !== null && $longitude !== null ? $longitude : null,
                    $map !== null && self::isWebLink($map) ? $map : null,
                    ($row['primary'] ?? false) === true,
                );
            }

            return $addresses;
        });
    }

    public function primaryAddress(?string $locale = null): ?Address
    {
        $addresses = $this->addresses($locale);

        foreach ($addresses as $address) {
            if ($address->primary) {
                return $address;
            }
        }

        return $addresses[0] ?? null;
    }

    public function hours(?string $locale = null): Hours
    {
        return $this->memo('hours', $locale, fn (): Hours => Hours::fromSettings(
            $this->settings->get(self::KEYS['hours'], null, $locale),
            $this->settings->get(self::KEYS['exceptions'], null, $locale),
            (string) self::text($this->settings->get(self::KEYS['timezone'])),
        ));
    }

    /**
     * Channels that are not a number's: a bot, a channel, a business chat link.
     *
     * @return list<Channel>
     */
    public function messengers(?string $locale = null): array
    {
        return $this->memo('messengers', $locale, fn (): array => $this->channels('messengers', 'channel', $locale, true));
    }

    /**
     * Every chat the site can be written to: each number's messengers, then the channels of
     * their own — what the quick-contact button lists.
     *
     * @return list<Channel>
     */
    public function chats(?string $locale = null): array
    {
        $chats = [];

        foreach ($this->phones($locale) as $phone) {
            foreach ($phone->messengers as $channel) {
                $chats[$channel->url] = new Channel($channel->kind, $channel->url, $phone->label);
            }
        }

        foreach ($this->messengers($locale) as $channel) {
            $chats[$channel->url] ??= $channel;
        }

        return array_values($chats);
    }

    /** @return list<Channel> */
    public function socials(?string $locale = null): array
    {
        return $this->memo('socials', $locale, fn (): array => $this->channels('socials', 'network', $locale, false));
    }

    public function isEmpty(): bool
    {
        return $this->phones() === [] && $this->emails() === [] && $this->addresses() === []
            && $this->hours()->isEmpty() && $this->messengers() === [] && $this->socials() === [];
    }

    /** Saving starts over: the next read is of what was saved. */
    public function forget(): void
    {
        $this->memo = [];
    }

    /**
     * A messenger's link may be its own scheme (`viber://`, `tg://`), a social network's is a
     * web page; neither is ever `javascript:`.
     */
    public static function isLink(string $url, bool $schemes): bool
    {
        if (self::isWebLink($url)) {
            return true;
        }

        return $schemes && preg_match('~^(?:tg|viber|whatsapp|signal|sgnl|skype|line|weixin|fb-messenger)://\S+$~i', $url) === 1;
    }

    public static function isWebLink(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('~^https?://~i', $url) === 1;
    }

    /** @return list<Channel> */
    private function channels(string $list, string $kind, ?string $locale, bool $schemes): array
    {
        $channels = [];

        foreach ($this->rows($list, $locale) as $row) {
            $name = self::text($row[$kind] ?? null);
            $url = self::text($row['url'] ?? null);

            if ($name !== null && $url !== null && preg_match('/^[a-z0-9-]+$/', $name) === 1 && self::isLink($url, $schemes)) {
                $channels[] = new Channel($name, $url, self::text($row['label'] ?? null));
            }
        }

        return $channels;
    }

    /**
     * The rows of a list as the site reads them — or, while the list is empty, the value of the
     * key the site used before, one row per value.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $list, ?string $locale): array
    {
        $rows = $this->settings->get(self::KEYS[$list], null, $locale);
        $rows = is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];

        if ($rows !== []) {
            return $rows;
        }

        $legacy = $this->config->get("webx-settings.contacts.legacy.{$list}");
        $field = ['phones' => 'number', 'emails' => 'email', 'addresses' => 'address'][$list] ?? null;

        if (! is_string($legacy) || $legacy === '' || $field === null) {
            return [];
        }

        $value = $this->settings->get($legacy, null, $locale);

        return array_values(array_map(
            static fn (string $one): array => [$field => $one],
            array_filter(is_array($value) ? $value : [$value], static fn (mixed $one): bool => is_string($one) && trim($one) !== ''),
        ));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    private function memo(string $what, ?string $locale, callable $read): mixed
    {
        $key = $what.'|'.($locale ?? app()->getLocale());

        return $this->memo[$key] ??= $read();
    }

    private static function coordinate(mixed $value, int $limit): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $value = (float) $value;

        return abs($value) <= $limit ? $value : null;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }
}
