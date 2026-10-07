<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Inbox\Mail\Recipients;
use WebxUi\Inbox\Models\Form;

/**
 * A switched-on form that would write to nobody: no recipients at all, or only administrators
 * deleted or switched off since, or addresses that are not addresses.
 *
 * Every submission is still saved, so nothing breaks where anybody looks — the site thanks the
 * visitor, the panel keeps the enquiry — and nobody hears of it until somebody opens the
 * section. A warning and not an error, because a form read only in the panel is a choice; the
 * audit's ignore is how a site says so.
 */
final class NoRecipients extends ModuleCheck
{
    protected const ID = 'inbox.no_recipients';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-inbox';

    public function run(AuditContext $context): iterable
    {
        $forms = Form::query()->enabled()->orderBy('position')->orderBy('id')->get();
        Recipients::load($forms);

        foreach ($forms as $form) {
            if (Recipients::notifies($form)) {
                continue;
            }

            $title = FormTitle::of($form);

            yield $this->found('no-recipients', ['form' => $title, 'slug' => $form->slug], key: (string) $form->id, table: [
                'columns' => [Finding::column('title'), Finding::column('value'), Finding::column('edit', 'edit')],
                // The editor of the form opens on the tab where the recipients are chosen.
                'rows' => [['title' => $title, 'value' => $form->slug, 'edit' => '/inbox/forms/'.$form->id.'?tab=notifications']],
            ]);
        }
    }
}
