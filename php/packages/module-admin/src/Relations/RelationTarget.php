<?php

declare(strict_types=1);

namespace WebxUi\Admin\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * What a record can be related to: the services of a site, its recipes (§3.3 of the recipes spec).
 *
 * The defaults read the model the way most of this system's models are built — a translated
 * `title`, a `Visible` answer, soft deletes — so a module that has nothing more to say registers
 * one line. A module that does (a subtitle, a thumbnail) extends this and overrides only that:
 *
 *     $targets->register(new RelationTarget(
 *         key: 'service',
 *         model: Service::class,
 *         permission: 'services.view',
 *         label: 'webx-services::relations.service',
 *     ));
 *
 * The key is the one the address registry knows the entity by, so an entity has one name
 * everywhere it is named.
 */
class RelationTarget
{
    /** As many as anybody reads before typing another letter. */
    public const LIMIT = 20;

    private ?bool $positioned = null;

    /**
     * @param  class-string<Model>  $model
     * @param  string|null  $permission  Who may see the candidates in the picker; null — anybody.
     * @param  string  $label  The translation key of what one of these is called: "Service".
     */
    public function __construct(
        public readonly string $key,
        public readonly string $model,
        public readonly ?string $permission = null,
        public readonly string $label = '',
    ) {}

    public function label(): string
    {
        return $this->label === '' ? $this->key : (string) __($this->label);
    }

    /**
     * The records as the site may show them: what is in the bin is not here.
     *
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        return ($this->model)::query();
    }

    /**
     * What the picker offers for a term: the first few, in the model's own order.
     *
     * @param  list<int>  $except  Records the picker must not offer — the record being edited, when it relates to its own kind.
     * @return list<array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool}>
     */
    public function candidates(string $term, string $locale, int $limit = self::LIMIT, array $except = []): array
    {
        $query = $this->query();

        if ($except !== []) {
            $query->whereKeyNot($except);
        }

        if ($term !== '') {
            $this->search($query, $term, $locale);
        }

        $records = $this->order($query)->limit($limit)->get();

        return array_values($records->map(fn (Model $record): array => $this->row($record, $locale))->all());
    }

    /**
     * The chosen records by id, in the order asked — what a form opens with. The bin included:
     * the editor has to see that a chosen service went there, not find it silently gone.
     *
     * @param  list<int>  $ids
     * @return list<array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool}>
     */
    public function describe(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        $found = $this->query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($ids)
            ->get()
            ->keyBy(static fn (Model $record): int => (int) $record->getKey());

        $rows = [];

        foreach ($ids as $id) {
            $record = $found->get($id);

            if ($record instanceof Model) {
                $rows[] = $this->row($record, $locale);
            }
        }

        return $rows;
    }

    /**
     * One record as the picker draws it.
     *
     * @return array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool}
     */
    public function row(Model $record, string $locale): array
    {
        $trashed = method_exists($record, 'trashed') && $record->trashed();

        return [
            'id' => (int) $record->getKey(),
            'title' => $this->title($record, $locale),
            'subtitle' => $this->subtitle($record, $locale),
            'thumb' => $this->thumb($record),
            'visible' => ! $trashed && $this->visible($record, $locale),
            'trashed' => $trashed,
        ];
    }

    /**
     * Whether the site shows the record: by the entity's own `Visible` answer when it has one. An
     * invisible target drops out on the site and stays in the panel, marked.
     */
    public function visible(Model $record, ?string $locale = null): bool
    {
        if (method_exists($record, 'trashed') && $record->trashed()) {
            return false;
        }

        return method_exists($record, 'isVisible') ? (bool) $record->isVisible($locale) : true;
    }

    /**
     * The name in the panel's language, falling back through the languages the site has, then
     * to the id — a service with no title anywhere is still a row somebody chose.
     */
    protected function title(Model $record, string $locale): string
    {
        foreach (['title', 'name'] as $attribute) {
            $value = method_exists($record, 'getTranslation') && $this->translates($record, $attribute)
                ? $record->getTranslation($attribute, $locale)
                : $record->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '#'.$record->getKey();
    }

    /** What tells two records of the same name apart: nothing, unless the module says. */
    protected function subtitle(Model $record, string $locale): ?string
    {
        return null;
    }

    /** A small picture for the row: none, unless the module says where it is. */
    protected function thumb(Model $record): ?string
    {
        return null;
    }

    /**
     * Narrow the candidates to a term: the translated title in any language, or the plain one.
     *
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $term, string $locale): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $model = $query->getModel();

        if (method_exists($model, 'scopeWhereTranslationLikeAny') && $this->translates($model, 'title')) {
            $query->scopes(['whereTranslationLikeAny' => ['title', $like]]);

            return;
        }

        $query->where($model->qualifyColumn('title'), 'like', $like);
    }

    /**
     * The model's own order when it keeps one by hand, the newest first otherwise.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function order(Builder $query): Builder
    {
        $model = $query->getModel();

        // Asked of the schema once per target rather than on every keystroke of the picker.
        $this->positioned ??= $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'position');

        if ($this->positioned) {
            $query->orderBy($model->qualifyColumn('position'));
        }

        return $query->orderBy($model->getQualifiedKeyName());
    }

    private function translates(Model $record, string $attribute): bool
    {
        return method_exists($record, 'isTranslatableAttribute') && $record->isTranslatableAttribute($attribute);
    }
}
