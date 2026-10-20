<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Notifications;

use Illuminate\Support\Facades\Lang;

/**
 * The default templates of `inbox.submission` — what a new channel of the event starts with, by
 * provider, in every language this package (or the site over it) has words in.
 *
 * The letter is the built-in one laid out again in Mustache: the form and when it came, the
 * answers as a table, the files, the button to the panel, then where it was sent from. Neutral on
 * purpose — no colours but greys and an ink button, no name of a site: it is a starting point the
 * administrator makes theirs. The words are the built-in letter's own (`mail.*`), so a site that
 * reworded that letter gets its words here as well.
 *
 * A language the words are not in is null: the channel is left without it and sends in the main
 * language, as any channel does.
 */
final class SubmissionTemplates
{
    private const MUTED = '#6b7280';

    private const LINE = '#e5e7eb';

    /**
     * @return array{subject: string, template: string}|null
     */
    public static function email(string $locale): ?array
    {
        if (! self::speaks($locale)) {
            return null;
        }

        $say = static fn (string $key, array $replace = []): string => e(self::word($key, $replace, $locale));
        $muted = self::MUTED;
        $line = self::LINE;

        $template = <<<HTML
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td style="padding:0 0 16px 0;">
                  <h1 style="margin:0 0 4px 0;font-size:20px;line-height:1.3;font-weight:600;">{{form}}</h1>
                  <p style="margin:0;color:{$muted};font-size:13px;">{$say('webx-inbox::mail.received', ['date' => '{{created_at}}'])}</p>
                </td>
              </tr>
              <tr>
                <td>
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    {{#answers}}
                    <tr>
                      <td style="padding:8px 12px 8px 0;vertical-align:top;width:38%;color:{$muted};border-bottom:1px solid {$line};">{{label}}</td>
                      <td style="padding:8px 0;vertical-align:top;border-bottom:1px solid {$line};">{{value}}</td>
                    </tr>
                    {{/answers}}
                    {{^answers}}
                    <tr>
                      <td style="padding:8px 0;color:{$muted};">{$say('webx-inbox::mail.no-values')}</td>
                    </tr>
                    {{/answers}}
                  </table>
                </td>
              </tr>
              {{#files}}
              <tr>
                <td style="padding:16px 0 0 0;">
                  <p style="margin:0 0 4px 0;font-weight:600;">{$say('webx-inbox::mail.files')}</p>
                  <p style="margin:0;">{{files}}</p>
                </td>
              </tr>
              {{/files}}
              <tr>
                <td style="padding:24px 0;">
                  <a href="{{submission_url}}" style="display:inline-block;padding:10px 18px;border-radius:6px;background-color:#111827;color:#ffffff;text-decoration:none;font-weight:500;">{$say('webx-inbox::mail.open')}</a>
                </td>
              </tr>
              {{#page}}
              <tr>
                <td style="padding:0 0 8px 0;font-size:13px;color:{$muted};">
                  {$say('webx-inbox::mail.meta-page')}: {{page}}
                </td>
              </tr>
              {{/page}}
              {{#utm}}
              <tr>
                <td style="padding:0 0 8px 0;font-size:13px;color:{$muted};">UTM: {{utm}}</td>
              </tr>
              {{/utm}}
              {{#reply_to}}
              <tr>
                <td style="padding:8px 0 0 0;font-size:13px;color:{$muted};">{$say('webx-inbox::notifications.reply', ['address' => '{{reply_to}}'])}</td>
              </tr>
              {{/reply_to}}
            </table>
            HTML;

        return [
            'subject' => self::word('webx-inbox::mail.subject', ['form' => '{{form}}'], $locale),
            'template' => $template."\n",
        ];
    }

    /**
     * Telegram's HTML: short, no subject — the form, the answers a line each, the files and the
     * link. A message longer than Telegram takes goes as plain text, cut (the provider does it).
     *
     * @return array{template: string}|null
     */
    public static function telegram(string $locale): ?array
    {
        if (! self::speaks($locale)) {
            return null;
        }

        $say = static fn (string $key): string => htmlspecialchars(self::word($key, [], $locale), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');

        return ['template' => implode("\n", [
            '<b>{{form}}</b>',
            '{{#answers}}',
            '<b>{{label}}:</b> {{value}}',
            '{{/answers}}',
            '{{#files}}',
            "\n".$say('webx-inbox::mail.files').': {{files}}',
            '{{/files}}',
            '{{#page}}',
            $say('webx-inbox::mail.meta-page').': {{page}}',
            '{{/page}}',
            '',
            '<a href="{{submission_url}}">'.$say('webx-inbox::mail.open').'</a>',
            '',
        ])];
    }

    /**
     * Any other provider — one a site added: plain text, the answers as lines.
     *
     * @return array{subject: string, template: string}|null
     */
    public static function text(string $locale): ?array
    {
        if (! self::speaks($locale)) {
            return null;
        }

        return [
            'subject' => self::word('webx-inbox::mail.subject', ['form' => '{{form}}'], $locale),
            'template' => "{{form}}\n\n{{fields}}\n\n".self::word('webx-inbox::mail.open', [], $locale).": {{submission_url}}\n",
        ];
    }

    /** Whether the words of the letter are there in this language — the package's or the site's. */
    private static function speaks(string $locale): bool
    {
        return Lang::hasForLocale('webx-inbox::mail.open', $locale)
            && Lang::hasForLocale('webx-inbox::notifications.reply', $locale);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function word(string $key, array $replace, string $locale): string
    {
        $line = trans($key, $replace, $locale);

        return is_string($line) ? $line : $key;
    }
}
