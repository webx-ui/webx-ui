<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use WebxUi\Payments\Credentials;

/**
 * What the journal wipes from the events of one trace (spec §20.5): the method's keys, the headers
 * and fields the gateway declared secret, and the secrets that turn up on the way — an OAuth token
 * read from one answer is wiped from every later event of the same action.
 *
 * Mutable on purpose and shared by the events of a trace; it holds values only in memory.
 */
final class ExchangeSecrets
{
    /** Shorter values are not looked for: they would match by chance (§20.5, rule 1). */
    public const int MIN_LENGTH = 8;

    /** Headers whose value is always a secret, whatever the gateway says (§20.5, rule 2). */
    public const array HEADERS = ['authorization', 'proxy-authorization', 'cookie', 'set-cookie'];

    /** @var array<string, string> value => name of the field it was found under */
    private array $found = [];

    private readonly Credentials $credentials;

    /** @var list<string> */
    private readonly array $headers;

    /** @var list<string> */
    private readonly array $fields;

    /**
     * @param  list<string>  $headers  `Gateway::secretHeaders()`
     * @param  list<string>  $fields  `Gateway::secretFields()`
     */
    public function __construct(
        ?Credentials $credentials = null,
        array $headers = [],
        array $fields = [],
    ) {
        $this->credentials = $credentials ?? Credentials::none();
        $this->headers = array_values(array_unique([...self::HEADERS, ...array_map(strtolower(...), $headers)]));
        $this->fields = array_values(array_unique($fields));
    }

    public static function none(): self
    {
        return new self(Credentials::none());
    }

    /** A secret made at run time (`GatewayHttp::secret()`), or one read from a secret field. */
    public function add(string $value, string $name): void
    {
        if (strlen($value) >= self::MIN_LENGTH) {
            $this->found[$value] ??= $name;
        }
    }

    public function isSecretHeader(string $name): bool
    {
        return in_array(strtolower($name), $this->headers, true);
    }

    /** @return list<string> */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Every value to wipe with the name it is replaced by; the method's keys first.
     *
     * @return array<string, string> value => name
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->credentials->reveal() as $name => $value) {
            if (strlen($value) >= self::MIN_LENGTH) {
                $values[$value] ??= $name;
            }
        }

        foreach ($this->found as $value => $name) {
            $values[(string) $value] ??= $name;
        }

        return $values;
    }
}
