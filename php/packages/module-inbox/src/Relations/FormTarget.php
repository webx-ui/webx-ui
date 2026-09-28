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

    /** The slug: what a template names the form by, and what tells two "Contact" forms apart. */
    protected function subtitle(Model $record, string $locale): ?string
    {
        return $record instanceof Form ? $record->slug : null;
    }
}
