<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Editing\LastChange;

/**
 * The register behind the panel on the site (WEBX_UI_SITE_BAR.md, §6).
 *
 * Two lists, both filled from service providers:
 *
 * - **records** — by the type an address carries in the registry: where the panel edits one,
 *   behind which permission, what it is called. Every section with a `RouteType` registers its
 *   types here, so the bar can say «this is the page Pricing» and open it in the panel;
 * - **sections** — the tabs of the bar, each with its own answer to «is there anything for this
 *   administrator here». The bar is shown only when at least one says yes: a bar with nothing in
 *   it is a trace on the page and no help.
 *
 * The frame registers the first section itself — «Page», for a record somebody may open — and the
 * SEO and blocks tabs come from their own packages, so this class never learns what they are.
 */
final class SiteBar
{
    /** @var array<string, EntityLink> */
    private array $links = [];

    /** @var array<string, Closure(HasPermissions, ?Model, ?EntityLink): bool> */
    private array $sections = [];

    public function entity(string $type, EntityLink $link): void
    {
        $this->links[$type] = $link;
    }

    public function link(string $type): ?EntityLink
    {
        return $this->links[$type] ?? null;
    }

    /** @return array<string, EntityLink> */
    public function links(): array
    {
        return $this->links;
    }

    /**
     * A tab of the bar and when it has something to show.
     *
     * @param  Closure(HasPermissions, ?Model, ?EntityLink): bool  $available  Asked with the record behind the address, if any.
     */
    public function section(string $name, Closure $available): void
    {
        $this->sections[$name] = $available;
    }

    /**
     * The tabs this administrator gets for this record (or for an address without one), in the
     * order they were registered.
     *
     * @return list<string>
     */
    public function sections(HasPermissions $user, ?Model $entity, ?string $type): array
    {
        $link = $type === null ? null : $this->link($type);
        $found = [];

        foreach ($this->sections as $name => $available) {
            if ($available($user, $entity, $link)) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /** Whether this administrator may open the record in the panel — what «Page» is about. */
    public function mayOpen(HasPermissions $user, ?Model $entity, ?EntityLink $link): bool
    {
        return $entity instanceof Model && $link instanceof EntityLink && $user->hasPermission($link->permission);
    }

    /**
     * The record as the bar shows it in its head and on the «Page» tab.
     *
     * @return array<string, mixed>
     */
    public function describe(string $type, Model $entity, EntityLink $link, HasPermissions $user, ?string $locale): array
    {
        return [
            'type' => $type,
            'id' => $entity->getKey(),
            'kind' => (string) __($link->kind),
            'label' => $link->label instanceof Closure ? ($link->label)($entity, $locale) : self::label($entity, $locale),
            'status' => self::status($entity, $locale),
            'draft' => self::draft($entity, $user),
            'updatedAt' => self::moment($entity->getAttribute('updated_at')),
            'edit' => ($link->edit)($entity),
            'can' => [
                'edit' => $user->hasPermission($link->permission),
                'publish' => $link->publish !== null && $user->hasPermission($link->permission),
            ],
        ];
    }

    /** Its title or name in the language of the address, the way the audit names a record. */
    public static function label(Model $entity, ?string $locale): string
    {
        foreach (['title', 'name', 'question'] as $column) {
            $translated = method_exists($entity, 'isTranslatableAttribute') && $entity->isTranslatableAttribute($column);
            $value = $translated && method_exists($entity, 'getTranslation')
                ? $entity->getTranslation($column, $locale)
                : $entity->getAttribute($column);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#'.$entity->getKey();
    }

    /**
     * Whether a visitor sees the record, in four words: `published`, `scheduled` (a date that has
     * not come yet), `unpublished` and `trashed`.
     *
     * Read off what the models already answer — `isVisible()` folds in each module's own idea of
     * «on the site» (a closed vacancy, a hidden category above a product) — rather than off a
     * column, so a module never has to explain itself here twice.
     */
    public static function status(Model $entity, ?string $locale): string
    {
        if (method_exists($entity, 'trashed') && $entity->trashed()) {
            return 'trashed';
        }

        if (method_exists($entity, 'isVisible') && (bool) $entity->isVisible($locale)) {
            return 'published';
        }

        if (method_exists($entity, 'publishedAtColumn')) {
            $at = $entity->getAttribute((string) $entity->publishedAtColumn());

            if ($at instanceof CarbonInterface && $at->isFuture()) {
                return 'scheduled';
            }
        }

        return method_exists($entity, 'isVisible') ? 'unpublished' : 'published';
    }

    /**
     * Whether somebody has prepared changes the site does not show yet, and who.
     *
     * Asked of the record rather than of the bar, so a draft left in the panel by a colleague is
     * reported before the first edit on the site lands in it (§9).
     *
     * @return array{differs: bool, by: string|null, at: string|null}|null
     */
    private static function draft(Model $entity, HasPermissions $user): ?array
    {
        if (! method_exists($entity, 'hasDraft') || ! method_exists($entity, 'changedFields')) {
            return null;
        }

        if (! $entity->hasDraft() || $entity->changedFields() === []) {
            return ['differs' => false, 'by' => null, 'at' => null];
        }

        $last = LastChange::of($entity, $user);

        return ['differs' => true, 'by' => $last['author'] ?? null, 'at' => $last['at'] ?? null];
    }

    private static function moment(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toAtomString() : null;
    }
}
