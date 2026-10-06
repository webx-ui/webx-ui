<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Content\ModelContentSource;
use WebxUi\Reviews\Models\Review;

/**
 * The reviews for the site audit: the text, the reviewer's profile link and photo — unpublished
 * ones included, which a crawl never sees.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class ReviewContentSource extends ModelContentSource
{
    public function id(): string
    {
        return 'reviews';
    }

    protected function models(): array
    {
        return ['' => Review::class];
    }

    protected function columns(Model $model): array
    {
        return ['text', 'job_title', 'profile_url', 'photo'];
    }

    protected function editUrl(Model $model): string
    {
        return '/reviews?review='.$model->getKey();
    }
}
