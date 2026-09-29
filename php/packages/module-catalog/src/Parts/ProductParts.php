<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Parts;

use InvalidArgumentException;

/**
 * The parts of the product form, filled from the providers of the satellites (§7.4).
 *
 * The core registers none: price, unit and categories are its own columns. A satellite adds one
 * line to its provider, and the form, the agent's tools and the journal pick it up.
 */
final class ProductParts
{
    /** @var array<string, ProductPart> */
    private array $parts = [];

    public function register(ProductPart $part): void
    {
        $key = $part->key();

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException("[{$key}] is not a key for a product part: lowercase letters, digits and single hyphens.");
        }

        if (isset($this->parts[$key]) && $part::class !== $this->parts[$key]::class) {
            throw new InvalidArgumentException("A product part with the key [{$key}] is registered already, by ".$this->parts[$key]::class.'.');
        }

        $this->parts[$key] = $part;
    }

    /**
     * @return array<string, ProductPart>
     */
    public function all(): array
    {
        return $this->parts;
    }

    public function find(string $key): ?ProductPart
    {
        return $this->parts[$key] ?? null;
    }

    /**
     * Sort the fields of the form that are not the core's by the part they belong to.
     *
     * @param  array<string, mixed>  $values  Field name as on the screen (`stock.status`) → value.
     * @return array<string, array<string, mixed>> Part key → field without the prefix → value.
     *
     * @throws InvalidArgumentException naming the first field nobody claims
     */
    public function split(array $values): array
    {
        $shares = [];

        foreach ($values as $name => $value) {
            [$key, $field] = array_pad(explode('.', (string) $name, 2), 2, '');

            if ($field === '' || ! isset($this->parts[$key])) {
                throw new InvalidArgumentException(sprintf(
                    'The field [%s] of the product form is stored by nobody: name it <part>.<field> and register the part with ProductParts::register(). Known parts: %s.',
                    $name,
                    $this->parts === [] ? 'none' : implode(', ', array_keys($this->parts)),
                ));
            }

            $shares[$key][$field] = $value;
        }

        return $shares;
    }
}
