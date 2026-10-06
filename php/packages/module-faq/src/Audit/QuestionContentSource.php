<?php

declare(strict_types=1);

namespace WebxUi\Faq\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Content\ModelContentSource;
use WebxUi\Faq\Models\Question;

/**
 * The questions and answers for the site audit: an answer is where a link to a stand hides —
 * unpublished ones included, which a crawl never sees.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class QuestionContentSource extends ModelContentSource
{
    public function id(): string
    {
        return 'faq';
    }

    protected function models(): array
    {
        return ['' => Question::class];
    }

    protected function columns(Model $model): array
    {
        return ['question', 'answer'];
    }

    protected function editUrl(Model $model): string
    {
        return '/faq?question='.$model->getKey();
    }
}
