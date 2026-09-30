<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Contracts\Config\Repository as Config;
use LogicException;

/**
 * Every facet the site has, in the order they were registered (§7.1), then those of the sources.
 *
 * The order is the default one — a category with no settings of its own, and no configured
 * ancestor, shows every facet in it (§6.2). The core registers first, so the category and the
 * price lead, and the satellites follow in the order their providers boot.
 *
 * A facet that lives in the database — a property — is not registered at boot: its module gives
 * a {@see FacetSource}, which is asked lazily, on the first read, and its answer is kept until
 * the end of the request ({@see flush()}). Its facets follow the registered ones.
 *
 * The code is checked here, once, rather than trusted: `_` is the one character a filter
 * address splits on (§8.1 of the architecture), and a facet whose code carried it would make
 * every address of that facet mean two things. It is checked in every language that has a code
 * of its own in the config, and once for all the rest. A source's codes are checked by its module
 * when they are saved, against {@see taken()} — they live in the database, and a refusal at read
 * time would be a catalogue that does not open — so here a source's facet whose key is taken is
 * only left out, and reported.
 */
final class Facets
{
    public const CODE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const KEY = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

    /** Stands for every language the config names no code for; never a language of its own. */
    private const ANY_LOCALE = '*';

    /** @var array<string, Facet> */
    private array $facets = [];

    /** @var list<FacetSource> */
    private array $sources = [];

    /** @var array<string, Facet>|null key → facet, what the sources said this request */
    private ?array $sourced = null;

    /** @var array<string, FacetSource> key → the source it came from */
    private array $origin = [];

    public function __construct(private readonly Config $config) {}

    public function register(Facet $facet): void
    {
        $key = $facet->key();

        if (preg_match(self::KEY, $key) !== 1) {
            throw new LogicException("A facet key is `[a-z0-9._-]`; [{$key}] is not.");
        }

        foreach ($this->locales() as $locale) {
            $code = $facet->code($locale);
            $in = $locale === self::ANY_LOCALE ? '' : " in [{$locale}]";

            if (preg_match(self::CODE, $code) !== 1) {
                throw new LogicException("A facet code is `[a-z0-9-]` — `_` splits a filter address; [{$code}] of [{$key}]{$in} is not.");
            }

            foreach ($this->facets as $other) {
                if ($other->key() !== $key && $other->code($locale) === $code) {
                    throw new LogicException("The code [{$code}] of the facet [{$key}]{$in} is taken by [{$other->key()}].");
                }
            }
        }

        $this->facets[$key] = $facet;
        $this->sourced = null;
    }

    /** A module whose facets live in the database; asked on the first read of a request. */
    public function source(FacetSource $source): void
    {
        $this->sources[] = $source;
        $this->sourced = null;
    }

    /** @return list<FacetSource> */
    public function sources(): array
    {
        return $this->sources;
    }

    /** The source a facet came from; null for a registered one. */
    public function sourceOf(string $key): ?FacetSource
    {
        $this->load();

        return $this->origin[$key] ?? null;
    }

    /**
     * Forget what the sources said: the next read asks them again. For whoever saved what a
     * source reads, and for a process that outlives a request — a queue worker.
     */
    public function flush(): void
    {
        $this->sourced = null;
    }

    public function forget(string $key): void
    {
        unset($this->facets[$key]);
        $this->sourced = null;
    }

    /** @return list<Facet> */
    public function all(): array
    {
        return array_values([...$this->facets, ...$this->load()]);
    }

    public function find(string $key): ?Facet
    {
        return $this->facets[$key] ?? $this->load()[$key] ?? null;
    }

    public function byCode(string $code, string $locale): ?Facet
    {
        return $this->taken($code, $locale);
    }

    /**
     * Who holds this code in this language, other than `$except` — what a module that names its
     * facets in the database asks before it saves a code (§3.4 of the properties spec).
     */
    public function taken(string $code, string $locale, ?string $except = null): ?Facet
    {
        foreach ($this->all() as $facet) {
            if ($facet->key() !== $except && $facet->code($locale) === $code) {
                return $facet;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(static fn (Facet $facet): string => $facet->key(), $this->all());
    }

    /**
     * @return array<string, Facet>
     */
    private function load(): array
    {
        if ($this->sourced !== null) {
            return $this->sourced;
        }

        $this->sourced = [];
        $this->origin = [];

        foreach ($this->sources as $source) {
            foreach ($source->facets() as $facet) {
                $key = $facet->key();

                if (preg_match(self::KEY, $key) !== 1 || isset($this->facets[$key]) || isset($this->sourced[$key])) {
                    report(new LogicException("The facet [{$key}] of ".$source::class.' is left out: its key is taken or not `[a-z0-9._-]`.'));

                    continue;
                }

                $this->sourced[$key] = $facet;
                $this->origin[$key] = $source;
            }
        }

        return $this->sourced;
    }

    /**
     * The languages a registered code can differ in: those the config names, and one for the
     * rest — so the check needs no database, which at boot may not exist yet.
     *
     * @return list<string>
     */
    private function locales(): array
    {
        $locales = [self::ANY_LOCALE];

        foreach ((array) $this->config->get('webx-catalog.facet_codes', []) as $codes) {
            foreach (is_array($codes) ? array_keys($codes) : [] as $locale) {
                $locales[] = (string) $locale;
            }
        }

        return array_values(array_unique($locales));
    }
}
