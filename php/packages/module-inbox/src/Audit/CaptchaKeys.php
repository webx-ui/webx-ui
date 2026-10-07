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
 * A switched-on form that asks for a captcha the site has no keys for.
 *
 * Without a site key the widget is not drawn, without a secret no answer can be verified, and
 * either way every submission is refused — which on the page looks like a form that does not
 * work, and in the log like a robot being kept out. An error, because nobody gets through.
 */
final class CaptchaKeys extends ModuleCheck
{
    protected const ID = 'inbox.captcha_keys';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-inbox';

    public function __construct(private readonly Captcha $captcha) {}

    public function run(AuditContext $context): iterable
    {
        $forms = Form::query()->enabled()->orderBy('position')->orderBy('id')->get();

        foreach ($forms as $form) {
            $provider = $this->captcha->provider($form);

            if ($provider === null || $this->captcha->configured($provider)) {
                continue;
            }

            $title = FormTitle::of($form);
            $name = (string) __('webx-inbox::panel.captcha-'.$provider);
            // Named the way they are written in the `.env`, which reads the same in every language.
            $missing = implode(', ', array_map(static fn (string $half): string => 'WEBX_INBOX_'.strtoupper($provider).'_'.strtoupper($half), $this->captcha->missing($provider)));

            yield $this->found('captcha-keys', ['form' => $title, 'slug' => $form->slug, 'provider' => $name, 'missing' => $missing], key: (string) $form->id, table: [
                'columns' => [Finding::column('title'), Finding::column('value'), Finding::column('edit', 'edit')],
                // The fix is in the `.env`; the other way out is the form's own antispam tab.
                'rows' => [['title' => $title, 'value' => $missing, 'edit' => '/inbox/forms/'.$form->id.'?tab=antispam']],
            ]);
        }
    }
}
