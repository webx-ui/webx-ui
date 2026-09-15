<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Formatters\PathFormatter;
use WebxUi\Routing\Models\Route;

/**
 * The address an entity may actually have, once the neighbours are taken into account.
 *
 * A flat namespace means `Str::slug()` of a name is not unique, so every save asks this: is the
 * address the formatter produced free, and if not, what does the type's policy say. A suffix it
 * picks goes back into the entity's slug (§2, decision 4) and the formatter is asked again —
 * otherwise the registry would hold an address that is nowhere in the form, and the next save
 * would recompute something different.
 *
 * The real guarantee is the unique index, not this lookup: two imports running at once both see
 * a free address. `RouteSync` catches the violation and comes back here.
 */
class UniquePath
{
    /** As wide as the `path` column; see the migration for why it is not wider. */
    public const MAX_LENGTH = 255;

    /**
     * Enough room for a genuinely popular name, and a stop for a formatter that ignores the
     * slug we hand it — without this such a formatter would spin forever.
     */
    private const ATTEMPTS = 50;

    public function __construct(private readonly Reserved $reserved) {}

    public function for(Model $entity, string $locale, PathFormatter $formatter, OnConflict $policy): string
    {
        $path = $formatter->format($entity, $locale);
        $attribute = EntitySlug::attribute($entity);

        $this->assertFits($path, $attribute);

        $rejection = $this->rejection($path, $locale, $entity, $attribute);

        if ($rejection === null) {
            return $path;
        }

        // The root cannot be suffixed: `''` has no slug to add `-2` to, and a second home page
        // is a mistake worth refusing rather than moving to `-2`.
        if ($policy === OnConflict::Fail || $path === '') {
            throw $rejection;
        }

        $slug = EntitySlug::read($entity, $locale);
        $suffix = $this->nextSuffix($path, $locale);

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++, $suffix++) {
            EntitySlug::write($entity, $locale, $slug.'-'.$suffix);

            $candidate = $formatter->format($entity, $locale);
            $this->assertFits($candidate, $attribute);

            if ($this->rejection($candidate, $locale, $entity, $attribute) === null) {
                return $candidate;
            }
        }

        EntitySlug::write($entity, $locale, $slug);

        throw $rejection;
    }

    /**
     * Why this address cannot be used, or null if it can.
     *
     * Two reasons that behave the same way and read differently: another entity is already
     * there, or the application itself is (§10). A type with the `suffix` policy walks past
     * both — a reserved name is as good as taken for an import that must not stop.
     */
    private function rejection(string $path, string $locale, Model $entity, string $attribute): ?PathRejected
    {
        if ($this->reserved->taken($path)) {
            return PathRejected::reserved($path, $attribute);
        }

        return $this->taken($path, $locale, $entity) ? PathRejected::taken($path, $attribute) : null;
    }

    /**
     * Is this address somebody else's?
     *
     * Only a canonical row of another entity counts. An alias standing on the address gives way
     * to a live page (§2, decision 12), and a row of this same entity is the address it already
     * has.
     */
    public function taken(string $path, string $locale, Model $entity): bool
    {
        return Route::query()
            ->where('locale', $locale)
            ->where('path', $path)
            ->where('kind', Route::CANONICAL)
            ->where(function (Builder $query) use ($entity): void {
                $query
                    ->where('entity_type', '!=', $entity->getMorphClass())
                    ->orWhere('entity_id', '!=', $entity->getKey());
            })
            ->exists();
    }

    /**
     * The first `-N` worth trying for this address.
     *
     * One prefixed LIKE, which the unique index serves, and the arithmetic in PHP: picking the
     * maximum in SQL would mean parsing the tail of a string in a way every database spells
     * differently. The pattern is not escaped on purpose — `%` and `_` inside a path can only
     * widen the result set, never narrow it, and the regular expression below filters what
     * comes back anyway. Escaping would have to be spelled per driver to work at all.
     */
    private function nextSuffix(string $path, string $locale): int
    {
        /** @var list<string> $existing */
        $existing = Route::query()
            ->where('locale', $locale)
            ->where('path', 'like', $path.'%')
            ->pluck('path')
            ->all();

        $highest = 1;
        $pattern = '/^'.preg_quote($path, '/').'-(\d+)$/';

        foreach ($existing as $candidate) {
            if (preg_match($pattern, $candidate, $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return $highest + 1;
    }

    private function assertFits(string $path, string $attribute): void
    {
        if (mb_strlen($path) > self::MAX_LENGTH) {
            throw PathRejected::tooLong($path, self::MAX_LENGTH, $attribute);
        }
    }
}
