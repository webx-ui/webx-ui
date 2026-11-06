<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use InvalidArgumentException;
use WebxUi\Payments\Contracts\MethodType;
use WebxUi\Payments\Contracts\ProvidesPaymentMarks;

/**
 * The payment systems a method can show (groups spec §3.2): the key, the words for `alt`, a file or
 * nothing — no file draws a plate. The order here is the order on the page. A satellite adds its
 * own from `boot()` through `callAfterResolving(PaymentMarks::class)`; a key registered again keeps
 * its place and takes the new words and file.
 */
final class PaymentMarks
{
    public const string APPLE_PAY = 'apple-pay';

    public const string KEY = '/^[a-z0-9-]+$/';

    private const string FILES = __DIR__.'/../resources/marks/';

    /** @var array<string, array{alt: string, file: string|null}> by key */
    private array $marks = [];

    /** @var array<string, PaymentMark> what the files said, read once per process */
    private array $read = [];

    public function __construct()
    {
        $this->register('visa', 'Visa', self::FILES.'visa.svg');
        $this->register('mastercard', 'Mastercard', self::FILES.'mastercard.svg');
        $this->register(self::APPLE_PAY, 'Apple Pay', self::FILES.'applepay.svg');
        $this->register('google-pay', 'Google Pay', self::FILES.'googlepay.svg');
        $this->register('privat24', 'Приват24');
        $this->register('privatbank', 'ПриватБанк');
        $this->register('mono', 'monobank');
    }

    /** @param  string|null  $file  a whole path to an SVG; null — a plate with the words of `alt` */
    public function register(string $key, string $alt, ?string $file = null): void
    {
        if (preg_match(self::KEY, $key) !== 1) {
            throw new InvalidArgumentException("[{$key}] is not a payment mark key: lowercase letters, digits and dashes.");
        }

        $this->marks[$key] = ['alt' => $alt, 'file' => $file];
        unset($this->read[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->marks[$key]);
    }

    /** @return list<string> in the order of the page */
    public function keys(): array
    {
        return array_keys($this->marks);
    }

    /**
     * A mark to draw. Never fails: a key nobody registered and a file that went missing are drawn
     * as a plate, because a checkout must not fall over a picture.
     */
    public function get(string $key): PaymentMark
    {
        return $this->read[$key] ??= $this->read($key);
    }

    /**
     * What is registered of these keys, once each, in the order of the page.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function known(array $keys): array
    {
        return array_values(array_filter($this->keys(), static fn (string $key): bool => in_array($key, $keys, true)));
    }

    /**
     * The marks of a method: a key not registered is skipped here and named by `webx:doctor`.
     *
     * @return list<string>
     */
    public function of(Method $method, MethodType $type): array
    {
        return $type instanceof ProvidesPaymentMarks ? $this->known($type->marks($method)) : [];
    }

    private function read(string $key): PaymentMark
    {
        $alt = $this->marks[$key]['alt'] ?? $key;
        $file = $this->marks[$key]['file'] ?? null;
        $svg = $file !== null && is_file($file) ? file_get_contents($file) : false;

        if ($svg === false) {
            return new PaymentMark($key, $alt);
        }

        return new PaymentMark($key, $alt, $file, substr(hash('xxh128', $svg), 0, 16), PaymentMark::widthOf($svg));
    }
}
