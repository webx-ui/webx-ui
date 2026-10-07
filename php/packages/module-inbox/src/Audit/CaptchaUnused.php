<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Inbox\Antispam\Captcha;
use WebxUi\Inbox\Models\Form;

/**
 * A switched-on form without a captcha, on a site that has the keys for one.
 *
 * Only where the keys are there: somebody set them up for a reason, and a form left out may
 * be an oversight. A site without keys is not told to get some — the hidden field, the
 * timestamp and the limit per address are the default protection, and most sites never need
 * more — which is why this is a notice and why there is no check for a form without a
 * captcha as such. {@see SpamWithoutCaptcha} is the one that says a form needs it.
 */
final class CaptchaUnused extends ModuleCheck
{
    protected const ID = 'inbox.captcha_unused';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::NOTICE;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-inbox';

    public function __construct(private readonly Captcha $captcha) {}

    public function run(AuditContext $context): iterable
    {
        $available = $this->captcha->available();

        if ($available === []) {
            return;
        }

        $providers = implode(', ', array_map(static fn (string $provider): string => (string) __('webx-inbox::panel.captcha-'.$provider), $available));

        foreach (Form::query()->enabled()->orderBy('position')->orderBy('id')->get() as $form) {
            if ($this->captcha->provider($form) !== null) {
                continue;
            }

            $title = FormTitle::of($form);

            yield $this->found('captcha-unused', ['form' => $title, 'slug' => $form->slug, 'providers' => $providers], key: (string) $form->id, table: [
                'columns' => [Finding::column('title'), Finding::column('value'), Finding::column('edit', 'edit')],
                'rows' => [['title' => $title, 'value' => $providers, 'edit' => '/inbox/forms/'.$form->id.'?tab=antispam']],
            ]);
        }
    }
}
