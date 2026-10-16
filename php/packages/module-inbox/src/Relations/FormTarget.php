<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Inbox\Models\Form;

/**
 * The forms of the site, for whatever chooses one — the application form of a vacancy (§4.3 of
 * the vacancies spec).
 *
 * No permission: the name of a form is no secret, and an editor who picks the form a vacancy
 * answers with is not somebody who has to read the submissions. A disabled form stays chosen and
 * is marked in the picker, the way a service in the bin is — the site sees no form there.
 */
final class FormTarget extends RelationTarget
{
    public const KEY = 'inbox-form';

    public function __construct()
    {
        parent::__construct(
            key: self::KEY,
            model: Form::class,
            permission: null,
            label: 'webx-inbox::relations.form',
        );
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Form::query();

        return $query;
    }

    public function visible(Model $record, ?string $locale = null): bool
    {
        return $record instanceof Form && $record->is_enabled;
    }

    /**
     * The row with the slug under its own name, beside the subtitle that shows it: `wx-inbox-form`
     * keeps the slug rather than the id, and a picker that read it off a line meant for people
     * would break the day that line says something else.
     *
     * @return array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool, slug?: string}
     */
    public function row(Model $record, string $locale): array
    {
        $row = parent::row($record, $locale);

        if ($record instanceof Form) {
            $row['slug'] = $record->slug;
        }

        return $row;
    }

    /** The slug: what a template names the form by, and what tells two "Contact" forms apart. */
    protected function subtitle(Model $record, string $locale): ?string
    {
        return $record instanceof Form ? $record->slug : null;
    }

    /**
     * By the title, as every target, and by the slug: a block written before the picker holds a
     * slug, and the picker names it by asking for exactly that — so the form whose slug it is
     * comes first, ahead of the ones that only contain it.
     *
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $term, string $locale): void
    {
        $titles = $query->getModel()->newQuery();
        parent::search($titles, $term, $locale);

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $slug = $query->getModel()->qualifyColumn('slug');

        $query->where(static function (Builder $where) use ($titles, $slug, $like): void {
            $where->addNestedWhereQuery($titles->getQuery())->orWhere($slug, 'like', $like);
        })->orderByRaw($slug.' = ? desc', [$term]);
    }
}
