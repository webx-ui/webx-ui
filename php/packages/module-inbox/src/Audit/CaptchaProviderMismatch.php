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
 * A switched-on form that keeps a provider of its own which is not the site's captcha.
 *
 * What forms said before the captcha was the site's (ORDERS §28.1): such a form goes on working
 * with its own provider, so nothing is broken — a notice, because the site has one captcha for
 * every form and this one needs a second pair of keys nobody may remember to keep.
 */
final class CaptchaProviderMismatch extends ModuleCheck
{
    protected const ID = 'inbox.captcha_provider_mismatch';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::NOTICE;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-inbox';

    public function __construct(private readonly Captcha $captcha) {}

    public function run(AuditContext $context): iterable
    {
        $site = $this->captcha->siteProvider();

        if ($site === null) {
            return;
        }

        foreach (Form::query()->enabled()->orderBy('position')->orderBy('id')->get() as $form) {
            if (! $this->captcha->mismatch($form)) {
                continue;
            }

            $title = FormTitle::of($form);
            $provider = (string) __('webx-inbox::panel.captcha-'.$this->captcha->provider($form));

            yield $this->found('captcha-provider-mismatch', [
                'form' => $title,
                'slug' => $form->slug,
                'provider' => $provider,
                'site' => (string) __('webx-inbox::panel.captcha-'.$site),
            ], key: (string) $form->id, table: [
                'columns' => [Finding::column('title'), Finding::column('value'), Finding::column('edit', 'edit')],
                'rows' => [['title' => $title, 'value' => $provider, 'edit' => '/inbox/forms/'.$form->id.'?tab=antispam']],
            ]);
        }
    }
}
