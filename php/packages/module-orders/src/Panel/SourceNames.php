<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The names of the sources a module writes the order's history under (§8): `payment:card-a` is
 * the method «Card (gateway A)» to a person, not its key. A module registers its prefix — the part
 * before the colon — with a closure that names the rest; the card's timeline and its notes give the
 * name beside the source, and the source stays as it was written.
 *
 * Each source is named once a request: a timeline of fifty lines by one gateway asks once.
 */
final class SourceNames
{
    /** @var array<string, Closure(string): ?string> */
    private array $names = [];

    /** @var array<string, string|null> */
    private array $known = [];

    /** @param  Closure(string): ?string  $name  the rest of the source after «prefix:» → its name, or null */
    public function register(string $prefix, Closure $name): void
    {
        $this->names[$prefix] = $name;
        $this->known = [];
    }

    /** The name of a source in a person's words, or null — the panel says the source as it is. */
    public function title(string $source): ?string
    {
        if (array_key_exists($source, $this->known)) {
            return $this->known[$source];
        }

        [$prefix, $rest] = str_contains($source, ':') ? explode(':', $source, 2) : [$source, ''];
        $name = $this->names[$prefix] ?? null;
        $title = null;

        if ($name !== null && $rest !== '') {
            try {
                $title = $name($rest);
            } catch (Throwable $e) {
                Log::warning("webx-orders: the name of the source «{$source}» failed", ['exception' => $e]);
            }
        }

        return $this->known[$source] = is_string($title) && trim($title) !== '' ? $title : null;
    }
}
