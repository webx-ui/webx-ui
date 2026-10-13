<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\Editing\LastChange;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Routing\RouteTypes;

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

    /** @var (Closure(Model, ?int): ?string)|null */
    private ?Closure $preview = null;

    public function entity(string $type, EntityLink $link): void
    {
        $this->links[$type] = $link;
    }

    /**
     * Where the draft of a record is shown as the page it will be — the blocks module's preview,
     * since the frame has none of its own. The head of the bar links to it next to «Publish»: the
     * page in front of the editor is the site, and after a reload a draft is nowhere on it.
     *
     * @param  Closure(Model, ?int): ?string  $url  The record and the administrator → a signed link, or null.
     */
    public function previews(Closure $url): void
    {
        $this->preview = $url;
    }

    public function link(string $type): ?EntityLink
    {
        return $this->links[$type] ?? null;
    }

    /**
     * A record the bar may name, by the type of its addresses and its key — what the bar's own
     * writes (publishing, a block's fields) start from. Null for a type nobody registered here or
     * in the address registry, and for a record that is not there.
     *
     * @return array{0: Model, 1: EntityLink}|null
     */
    public function record(string $type, string|int $id): ?array
    {
        $link = $this->link($type);

        if ($link === null || ! class_exists(RouteTypes::class)) {
            return null;
        }

        $container = Container::getInstance();
        $routeType = $container->make(RouteTypes::class)->find($type);

        if ($routeType === null) {
            return null;
        }

        /** @var Model $model */
        $model = $container->make($routeType->model);
        $entity = $model->newQuery()->find($id);

        return $entity instanceof Model ? [$entity, $link] : null;
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
            // Only while there is a draft to look at: a signed link is made on every read.
            'preview' => $this->previewOf($entity, $user),
            // What the record's editor in the panel calls it and the revision it holds: the bar
            // says «open» in the same place, and publishes only what it has seen.
            'editing' => $this->records()?->nameOf($entity),
            'revision' => $this->records()?->revisionOf($entity),
            'can' => [
                'edit' => $user->hasPermission($link->permission),
                'publish' => $link->publish !== null && $user->hasPermission($link->permission),
            ],
        ];
    }

    private function previewOf(Model $entity, HasPermissions $user): ?string
    {
        if ($this->preview === null || ! method_exists($entity, 'hasDraft') || ! $entity->hasDraft()) {
            return null;
        }

        $id = $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;

        return ($this->preview)($entity, is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null);
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
     * `changes` counts the fields that differ; `mine` says whether the draft is this administrator's
     * alone — until it is, the bar warns that their edits go into somebody else's.
     *
     * @return array{differs: bool, by: string|null, at: string|null, changes: int, mine: bool}|null
     */
    private static function draft(Model $entity, HasPermissions $user): ?array
    {
        if (! method_exists($entity, 'hasDraft') || ! method_exists($entity, 'changedFields')) {
            return null;
        }

        $changed = $entity->hasDraft() ? $entity->changedFields() : [];

        if ($changed === []) {
            return ['differs' => false, 'by' => null, 'at' => null, 'changes' => 0, 'mine' => true];
        }

        return ['differs' => true, 'changes' => count($changed)] + self::hands($entity, $user);
    }

    /**
     * Whose the draft is: somebody else's as long as anybody else wrote into it since the record
     * was last published — not only when the last save was theirs. «Publish» puts the whole draft
     * on the site, a colleague's half-made title with it, so the warning stays after this
     * administrator's first edit on top. `by` names that somebody; when there is none, the last
     * hand on it.
     *
     * Read off the versions: every save of a draft is an autosave, a draft written over is kept as
     * `overwritten`, and a publication starts the count again.
     *
     * @return array{by: string|null, at: string|null, mine: bool}
     */
    public static function hands(Model $entity, mixed $user): array
    {
        $last = LastChange::of($entity, $user);
        $me = $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;
        $other = null;

        if (method_exists($entity, 'versions') && $me !== null) {
            $since = $entity->versions()->reorder()->where('kind', EntityVersion::KIND_PUBLISHED)->max('id');

            $other = $entity->versions()
                ->whereIn('kind', [EntityVersion::KIND_AUTOSAVE, EntityVersion::KIND_OVERWRITTEN])
                ->when($since !== null, static fn ($query) => $query->where('id', '>', $since))
                ->whereNotNull('author_id')
                ->where('author_id', '!=', $me)
                ->first();
        }

        if ($other instanceof EntityVersion) {
            $names = Authors::names($user, [$other->author_id]);

            return [
                'by' => $names[(int) $other->author_id] ?? null,
                'at' => $other->created_at?->toAtomString(),
                'mine' => false,
            ];
        }

        $author = $last['author_id'] ?? null;

        return [
            'by' => $last['author'] ?? null,
            'at' => $last['at'] ?? null,
            'mine' => $author !== null && $me !== null && (string) $author === (string) $me,
        ];
    }

    private function records(): ?EditedRecords
    {
        $container = Container::getInstance();

        return $container->bound(EditedRecords::class) ? $container->make(EditedRecords::class) : null;
    }

    private static function moment(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toAtomString() : null;
    }
}
