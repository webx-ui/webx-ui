<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Audit;

use Illuminate\Support\Carbon;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Inbox\Antispam\Captcha;
use WebxUi\Inbox\Antispam\Refusals;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;

/**
 * A switched-on form without a captcha that robots have found.
 *
 * Spam is two things here: submissions somebody put in a spam status, which got past the free
 * layers, and submissions those layers turned away, which are not written anywhere and are
 * counted per day by {@see Refusals}. Together they cross `inbox_spam_min` over the last
 * `inbox_spam_days` days — a minimum, so one test of the honeypot is not a finding.
 */
final class SpamWithoutCaptcha extends ModuleCheck
{
    protected const ID = 'inbox.spam_without_captcha';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-inbox';

    public function __construct(
        private readonly Captcha $captcha,
        private readonly Refusals $refusals,
    ) {}

    public function run(AuditContext $context): iterable
    {
        $days = max(1, $context->threshold('inbox_spam_days', 30));
        $minimum = max(1, $context->threshold('inbox_spam_min', 3));
        $since = Carbon::now()->subDays($days);

        foreach (Form::query()->enabled()->orderBy('position')->orderBy('id')->get() as $form) {
            if ($this->captcha->provider($form) !== null) {
                continue;
            }

            $spam = Submission::query()
                ->where('form_id', $form->getKey())
                ->where('created_at', '>=', $since)
                ->whereHas('status', static fn ($status) => $status->where('is_spam', true))
                ->count();

            $refused = $this->refusals->since($form, $days);

            if ($spam + $refused < $minimum) {
                continue;
            }

            $title = FormTitle::of($form);

            yield $this->found('spam-without-captcha', [
                'form' => $title,
                'slug' => $form->slug,
                'days' => $days,
                'spam' => $spam,
                'refused' => $refused,
            ], key: (string) $form->id, table: [
                'columns' => [Finding::column('title'), Finding::column('value'), Finding::column('edit', 'edit')],
                'rows' => [['title' => $title, 'value' => $spam.' + '.$refused, 'edit' => '/inbox/forms/'.$form->id.'?tab=antispam']],
            ]);
        }
    }
}
