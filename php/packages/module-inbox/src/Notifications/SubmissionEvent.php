<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Notifications;

use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionValue;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;

/**
 * `inbox.submission` — what a channel of the notifications module is told about a submission
 * (NOTIFICATIONS §15.1).
 *
 * Only ever touched when `webx-ui/module-notifications` is installed: the provider declares the
 * event under `class_exists`, and nothing else in this module names the classes it imports.
 *
 * The values are a snapshot taken when the submission is stored — the job renders the template
 * later, but from what the visitor sent then. `ip` and the browser are left out on purpose: a
 * messenger needs what the form asked, not more about the person than that.
 */
final class SubmissionEvent
{
    public const KEY = 'inbox.submission';

    public static function definition(): EventDefinition
    {
        $vars = 'webx-inbox::notifications.vars.';

        return new EventDefinition(
            key: self::KEY,
            label: 'webx-inbox::notifications.event',
            module: 'inbox',
            variables: [
                new Variable('form', $vars.'form', example: 'Contact us'),
                new Variable('form_slug', $vars.'form_slug', example: 'contact'),
                new Variable('submission_id', $vars.'submission_id', example: '128'),
                new Variable('submission_url', $vars.'submission_url', example: 'https://site.test/cms/inbox/submissions/128'),
                new Variable('created_at', $vars.'created_at', example: '10/16/2026 2:05 PM'),
                new Variable('fields', $vars.'fields', example: "Name: Alex Doe\nPhone: +0 000 000-00-00\nMessage: Call me after six, please."),
                // The same answers as rows, for a template that lays them out itself.
                new Variable('answers', $vars.'answers', example: [
                    ['name' => 'name', 'label' => 'Name', 'value' => 'Alex Doe'],
                    ['name' => 'phone', 'label' => 'Phone', 'value' => '+0 000 000-00-00'],
                    ['name' => 'message', 'label' => 'Message', 'value' => 'Call me after six, please.'],
                ], fields: [
                    'name' => $vars.'answer_name',
                    'label' => $vars.'answer_label',
                    'value' => $vars.'answer_value',
                ]),
                new Variable('field.*', $vars.'field'),
                new Variable('files', $vars.'files', example: 'brief.pdf'),
                new Variable('page', $vars.'page', example: 'https://site.test/contacts'),
                new Variable('locale', $vars.'locale', example: 'en'),
                new Variable('utm', $vars.'utm', example: 'source=newsletter'),
                // An addressee (decision 12): a channel may write back to whoever filled the form.
                new Variable('reply_to', $vars.'reply_to', example: 'visitor@site.test', address: 'email'),
            ],
            // The default templates (decision 1): a starting point in every language there are words in.
            sample: [
                'email' => SubmissionTemplates::email(...),
                'telegram' => SubmissionTemplates::telegram(...),
                '*' => SubmissionTemplates::text(...),
            ],
            url: static fn (array $values): ?string => is_string($values['submission_url'] ?? null) && $values['submission_url'] !== ''
                ? $values['submission_url']
                : null,
        );
    }

    /**
     * What the templates get for one submission — values, files and form loaded or not.
     *
     * @return array<string, string|list<array<string, string>>>
     */
    public static function values(Submission $submission): array
    {
        $submission->loadMissing(['form', 'values', 'files']);

        $meta = is_array($submission->meta) ? $submission->meta : [];
        $locale = is_string($meta['locale'] ?? null) && $meta['locale'] !== '' ? $meta['locale'] : app()->getLocale();
        $form = $submission->form;
        $title = $form === null ? '' : app(Shortcodes::class)->plain((string) $form->getTranslation('title', $locale));

        $values = [
            'form' => $title !== '' ? $title : (string) $form?->slug,
            'form_slug' => (string) $form?->slug,
            'submission_id' => (string) $submission->getKey(),
            'submission_url' => self::url($submission),
            'created_at' => (string) $submission->created_at?->copy()->locale($locale)->isoFormat('L LT'),
            'files' => $submission->files->pluck('name')->implode(', '),
            'page' => is_string($meta['page'] ?? null) ? $meta['page'] : '',
            'locale' => $locale,
            'utm' => self::utm($meta['utm'] ?? null),
            'reply_to' => self::replyTo($submission),
        ];

        $lines = [];
        $answers = [];

        foreach ($submission->values as $answer) {
            $value = $answer->type === 'consent' && $answer->value === SubmissionValue::CONSENTED
                ? (string) trans('webx-inbox::values.consented', [], $locale)
                : (string) $answer->value;

            if (trim($value) === '') {
                continue;
            }

            $label = trim((string) $answer->label) !== '' ? (string) $answer->label : $answer->name;
            $lines[] = $label.': '.$value;
            $answers[] = ['name' => $answer->name, 'label' => $label, 'value' => $value];

            // `{{field.phone}}`: a name that would not pass as a template name is only in the lists.
            if (preg_match('/^[a-z0-9_]+$/', $answer->name) === 1) {
                $values['field.'.$answer->name] = $value;
            }
        }

        $values['fields'] = implode("\n", $lines);
        $values['answers'] = $answers;

        return $values;
    }

    /**
     * The submission in the panel, from `app.url` and not from the request: the job that sends it
     * runs in a worker, and the panel's own letter link is the one place that may differ.
     */
    public static function url(Submission $submission): string
    {
        $path = trim((string) config('webx-admin.path', 'cms'), '/');

        return rtrim((string) config('app.url'), '/')."/{$path}/inbox/submissions/{$submission->getKey()}";
    }

    private static function utm(mixed $utm): string
    {
        if (! is_array($utm)) {
            return '';
        }

        $parts = [];

        foreach ($utm as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $parts[] = preg_replace('/^utm_/', '', (string) $key).'='.$value;
            }
        }

        return implode(', ', $parts);
    }

    /** The answer of the form's e-mail field, when it is an address. */
    private static function replyTo(Submission $submission): string
    {
        $name = $submission->form?->option('email_field');

        if (! is_string($name) || $name === '') {
            return '';
        }

        $address = trim((string) $submission->value($name)?->value);

        return filter_var($address, FILTER_VALIDATE_EMAIL) === false ? '' : $address;
    }
}
