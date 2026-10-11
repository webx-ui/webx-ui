<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route;

/**
 * Where an entity with no translation in a language is shown in that language anyway (§8.7).
 *
 * A missing translation is still a missing row: nothing is written for it. But a type that says
 * `showsUntranslated` lets the resolver answer the default language's path under the other
 * language's prefix — the interface in the language the reader chose, the content in the one it
 * was written in. Three places have to agree on when that happens: the resolver that answers it,
 * the `<head>` that closes it to the index and points its canonical home, and the language
 * switcher that links to it. The last two ask here, so that a link never leads to an address the
 * `<head>` would not recognise as one.
 */
final class Untranslated
{
    public function __construct(
        private readonly RouteTypes $types,
        private readonly Locales $locales,
        private readonly SiteUrl $site,
        private readonly Config $config,
    ) {}

    /**
     * The default language's canonical row this entity is shown by in `$locale`; null when it is
     * not shown that way — it has a row of its own there, its type did not ask for this, it is not
     * on the site, or the language is not in the path at all.
     */
    public function source(Model $entity, string $locale): ?Route
    {
        if (! $this->applies($locale)) {
            return null;
        }

        $type = $this->types->for($entity);

        if ($type === null || ! $type->showsUntranslated) {
            return null;
        }

        $source = null;

        foreach (Route::query()->forEntity($entity)->canonical()->get() as $row) {
            if ($row->locale === $locale) {
                return null;
            }

            if ($row->locale === $this->locales->defaultCode()) {
                $source = $row;
            }
        }

        if ($source === null || ($entity instanceof Visible && ! $entity->isVisible($source->locale))) {
            return null;
        }

        // Another entity's row on the same path in this language is what that address answers
        // with, so a link there would open somebody else's page.
        $taken = Route::query()->where('locale', $locale)->where('path', $source->path)->exists();

        return $taken ? null : $source;
    }

    /** Whether this entity, asked for in this language, is shown without a translation. */
    public function is(?object $entity, string $locale): bool
    {
        return $entity instanceof Model && $this->source($entity, $locale) !== null;
    }

    /** The path it is shown at in `$locale`, prefix and all, without a leading slash. */
    public function path(Model $entity, string $locale): ?string
    {
        $source = $this->source($entity, $locale);

        return $source === null ? null : UrlNormaliser::join($this->site->prefix($locale), $source->path);
    }

    /**
     * The path of the original, with a leading slash — what the canonical of the untranslated
     * answer names. A path rather than an address: whoever builds the address decides whether
     * the front controller is in it.
     */
    public function original(Model $entity, string $locale): ?string
    {
        $source = $this->source($entity, $locale);

        return $source === null ? null : '/'.UrlNormaliser::join($this->site->prefix($source->locale), $source->path);
    }

    /**
     * Only where the language is in the path, and only for a language that is not the default.
     * With one address for every language the original and the untranslated answer are the same
     * address, and closing one to the index would close the other.
     */
    private function applies(string $locale): bool
    {
        return (string) $this->config->get('webx-localization.strategy', 'prefix') === 'prefix'
            && $locale !== $this->locales->defaultCode()
            && $this->locales->has($locale);
    }
}
