<?php

declare(strict_types=1);

namespace WebxUi\Services\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * Services, for whatever points at them: a recipe, later a review or a question (§3.3 of the
 * recipes spec). What the defaults do not know is the two things a row needs to be told apart —
 * the categories, as the link picker shows them, and the cover.
 */
final class ServiceTarget extends RelationTarget
{
    public function __construct()
    {
        parent::__construct(
            key: 'service',
            model: Service::class,
            permission: 'services.view',
            label: 'webx-services::relations.service',
        );
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Service::query()->with(['categories', 'cover']);

        return $query;
    }

    protected function subtitle(Model $record, string $locale): ?string
    {
        if (! $record instanceof Service) {
            return null;
        }

        $names = $record->categories
            ->map(static fn (ServiceCategory $category): string => $category->displayName($locale))
            ->all();

        return $names === [] ? null : implode(', ', $names);
    }

    protected function thumb(Model $record): ?string
    {
        $cover = $record instanceof Service ? $record->cover : null;

        return $cover instanceof MediaFile ? app(FileUrls::class)->thumbUrl($cover) : null;
    }
}
