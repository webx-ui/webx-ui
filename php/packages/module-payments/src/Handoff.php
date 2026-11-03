<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Where the buyer goes to pay (spec §6.3): a plain redirect to a page the gateway made by API, or
 * a form of signed fields posted to the gateway's page. No card field may ever be in such a form
 * (decision 3): the shop never takes a card — `post()` refuses one by its name.
 */
final readonly class Handoff
{
    public const string REDIRECT = 'redirect';

    public const string POST = 'post';

    /** Words of a field name that would carry card data. */
    private const array CARD_WORDS = ['card', 'pan', 'cvv', 'cvv2', 'cvc', 'cvc2', 'expiry', 'exp'];

    /** @param  array<string, string>  $fields */
    private function __construct(
        public string $mode,
        public string $url,
        public array $fields,
        public ?string $gatewayRef,
        public ?CarbonImmutable $expiresAt,
    ) {}

    public static function redirect(string $url, ?string $gatewayRef = null, ?CarbonInterface $expiresAt = null): self
    {
        return new self(self::REDIRECT, self::url($url), [], $gatewayRef, $expiresAt !== null ? CarbonImmutable::instance($expiresAt) : null);
    }

    /**
     * A form the buyer's browser posts to the gateway: auto-submitted by a tiny page.
     *
     * @param  array<string, string>  $fields
     *
     * @throws InvalidArgumentException a field named like card data
     */
    public static function post(string $url, array $fields, ?string $gatewayRef = null, ?CarbonInterface $expiresAt = null): self
    {
        foreach ($fields as $name => $value) {
            if (self::isCardField((string) $name)) {
                throw new InvalidArgumentException("The field [{$name}] of a gateway's form reads like card data: the shop never takes a card (decision 3).");
            }
        }

        return new self(self::POST, self::url($url), array_map(strval(...), $fields), $gatewayRef, $expiresAt !== null ? CarbonImmutable::instance($expiresAt) : null);
    }

    public function isPost(): bool
    {
        return $this->mode === self::POST;
    }

    /** `card_number`, `cvv`, `exp_month`, `cardExpiry` — any word of the name; `company`, `expires_at` pass. */
    public static function isCardField(string $name): bool
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name))) ?: [];

        foreach ($words as $word) {
            if (in_array($word, self::CARD_WORDS, true) || str_starts_with($word, 'card')) {
                return true;
            }
        }

        return false;
    }

    private static function url(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['https', 'http'], true) || parse_url($url, PHP_URL_HOST) === null) {
            throw new InvalidArgumentException('A gateway\'s page is an absolute http(s) address.');
        }

        return $url;
    }
}
