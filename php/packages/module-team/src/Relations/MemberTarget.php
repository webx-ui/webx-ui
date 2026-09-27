<?php

declare(strict_types=1);

namespace WebxUi\Team\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Team\Models\Member;

/**
 * People of the team, for whatever points at them — the reviews of a doctor, the questions to a
 * specialist, on a later day (decision 12). The module itself points at nobody on the team.
 *
 * The picker tells two people apart by their job title and their photo. A person is called by a
 * `name` rather than the `title` the defaults read, so the title and the search are this class's.
 */
final class MemberTarget extends RelationTarget
{
    public function __construct()
    {
        parent::__construct(
            key: Member::TYPE,
            model: Member::class,
            permission: 'team.view',
            label: 'webx-team::relations.member',
        );
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Member::query();

        return $query;
    }

    protected function title(Model $record, string $locale): string
    {
        $name = $record instanceof Member ? $record->wordsIn('name', $locale, $this->defaultLocale()) : '';

        return $name !== '' ? $name : '#'.$record->getKey();
    }

    protected function subtitle(Model $record, string $locale): ?string
    {
        $job = $record instanceof Member ? $record->wordsIn('job_title', $locale, $this->defaultLocale()) : '';

        return $job === '' ? null : $job;
    }

    protected function thumb(Model $record): ?string
    {
        if (! $record instanceof Member || $record->photoPath() === null) {
            return null;
        }

        $photo = app(MediaValues::class)->resolve($record->photo, null);
        $thumb = is_array($photo) ? ($photo['thumb'] ?? $photo['url'] ?? null) : null;

        return is_string($thumb) ? $thumb : null;
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $term, string $locale): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(static function (Builder $nested) use ($like): void {
            $nested->scopes(['whereTranslationLikeAny' => ['name', $like]])
                ->orWhere(static fn (Builder $half): Builder => $half->scopes(['whereTranslationLikeAny' => ['job_title', $like]]));
        });
    }

    private function defaultLocale(): string
    {
        return app(Locales::class)->defaultCode();
    }
}
