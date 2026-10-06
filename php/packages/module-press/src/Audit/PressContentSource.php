<?php

declare(strict_types=1);

namespace WebxUi\Press\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Content\ModelContentSource;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;

/**
 * The press section for the site audit: an outlet's summary, logo and website, and each of its
 * articles' excerpt, link and file. Both are edited on the outlet, so both open it.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class PressContentSource extends ModelContentSource
{
    public function id(): string
    {
        return 'press';
    }

    protected function models(): array
    {
        return ['outlet' => Outlet::class, 'article' => Article::class];
    }

    protected function columns(Model $model): array
    {
        return $model instanceof Article ? ['excerpt', 'url', 'file'] : ['summary', 'logo', 'website_url'];
    }

    protected function editUrl(Model $model): string
    {
        return '/press?outlet='.($model instanceof Article ? $model->getAttribute('outlet_id') : $model->getKey());
    }

    /** An article is shown unless it is hidden; whether its outlet is, the outlet's record says. */
    protected function published(Model $model): bool
    {
        return $model instanceof Article ? ! $model->getAttribute('is_hidden') : parent::published($model);
    }
}
