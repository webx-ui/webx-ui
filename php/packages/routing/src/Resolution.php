<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use WebxUi\Routing\Models\Route;

/**
 * What the registry found for a request, and how it got there.
 *
 * The resolver leaves this on the request so that whatever answers afterwards does not have to
 * ask the registry a second question it has already answered. `module-seo` is the first taker:
 * the `<head>` of a page needs the entity the address belongs to, and that is exactly what
 * `Resolution::of($request)?->entity` hands it.
 */
final class Resolution
{
    /** Where the resolver leaves this on the request. */
    public const ATTRIBUTE = 'webx.route';

    public function __construct(
        public readonly Route $route,
        public readonly string $tail,
        public readonly ?RouteType $type = null,
        public readonly ?Model $entity = null,
        public readonly ?string $locale = null,
    ) {}

    public static function of(Request $request): ?self
    {
        $resolution = $request->attributes->get(self::ATTRIBUTE);

        return $resolution instanceof self ? $resolution : null;
    }

    /** Whether the address that was asked for is the one the entity has now. */
    public function isCanonical(): bool
    {
        return ! $this->route->isAlias();
    }

    /**
     * Whether the row is another language's: the entity has no translation in the one that was
     * asked for, and is shown in the default language's words under its prefix (§8.7).
     *
     * The row is the default language's canonical; `$locale` is the language of the address.
     */
    public function isUntranslated(): bool
    {
        return $this->locale !== null && $this->locale !== $this->route->locale;
    }

    public function with(RouteType $type, Model $entity): self
    {
        return new self($this->route, $this->tail, $type, $entity, $this->locale);
    }

    /** The same find, answered in a language it has no row in. */
    public function in(string $locale): self
    {
        return new self($this->route, $this->tail, $this->type, $this->entity, $locale);
    }
}
