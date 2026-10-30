<?php

declare(strict_types=1);

namespace WebxUi\Orders\Notifications;

use Illuminate\Support\Facades\Lang;

/**
 * The default templates of the order's events (spec §10.2) — what a new channel starts with, by
 * provider, in every language this package (or the site over it) has words in.
 *
 * Two kinds of letter. The buyer's: what happened, the lines as a table, the money, delivery and
 * payment, the link to the page of the order — never the internal status (decision 11). The
 * shop's: the same, plus who bought, how to reach them, the internal status and the link into
 * the panel. Neutral on purpose — greys and an ink button, no name of a site: a starting point
 * the administrator makes theirs.
 *
 * A language the words are not in is null: the channel is left without it and sends in the main
 * language, as any channel does.
 */
final class OrderTemplates
{
    public const string CUSTOMER = 'customer';

    public const string MANAGER = 'manager';

    private const string MUTED = '#6b7280';

    private const string LINE = '#e5e7eb';

    /**
     * @return array{subject: string, template: string}|null
     */
    public static function email(string $event, string $audience, string $locale): ?array
    {
        $letter = self::letter($event, $audience);

        if ($letter === null || ! self::speaks($letter, $locale)) {
            return null;
        }

        $say = static fn (string $key): string => e(self::word($key, $locale));
        $muted = self::MUTED;
        $line = self::LINE;
        $manager = $audience === self::MANAGER;

        $rows = [
            self::row($say('subtotal'), '{{subtotal}}', $muted),
            '{{#discount}}'.self::row($say('discount'), '{{discount}}', $muted).'{{/discount}}',
            '{{#tenders}}'.self::row($say('tenders'), '{{tenders}}', $muted).'{{/tenders}}',
            self::row('<strong>'.$say('payable').'</strong>', '<strong>{{payable}}</strong>', $muted),
            ...self::balanceRows($event, $say, $muted),
        ];

        $details = [
            '{{#delivery}}'.self::detail($say('delivery'), '{{delivery}}', $muted).'{{/delivery}}',
            '{{#payment}}'.self::detail($say('payment'), '{{payment}}', $muted).'{{/payment}}',
        ];

        if ($manager) {
            array_unshift(
                $details,
                self::detail($say('customer'), '{{customer_name}}{{#customer_phone}}, {{customer_phone}}{{/customer_phone}}{{#customer_email}}, {{customer_email}}{{/customer_email}}', $muted),
                '{{#recipient_name}}'.self::detail($say('recipient'), '{{recipient_name}}{{#recipient_phone}}, {{recipient_phone}}{{/recipient_phone}}', $muted).'{{/recipient_name}}',
                self::detail($say('status'), '{{public_status}} → {{status}}', $muted),
            );
            $details[] = '{{#comment}}'.self::detail($say('comment'), '{{comment}}', $muted).'{{/comment}}';
        }

        $details = [...$details, ...self::extras($event, $say, $muted)];
        $button = $manager
            ? '<a href="{{order_url}}" style="display:inline-block;padding:10px 18px;border-radius:6px;background-color:#111827;color:#ffffff;text-decoration:none;font-weight:500;">'.$say('open-panel').'</a>'
            : '{{#public_url}}<a href="{{public_url}}" style="display:inline-block;padding:10px 18px;border-radius:6px;background-color:#111827;color:#ffffff;text-decoration:none;font-weight:500;">'.$say('open-order').'</a>{{/public_url}}';

        $title = e(self::word("letters.{$letter}.subject", $locale));
        $intro = e(self::word("letters.{$letter}.intro", $locale));
        $totals = implode("\n", $rows);
        $more = implode("\n", $details);
        $lead = self::lead($event, $say, $muted, $line);

        $template = <<<HTML
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td style="padding:0 0 16px 0;">
                  <h1 style="margin:0 0 4px 0;font-size:20px;line-height:1.3;font-weight:600;">{$title}</h1>
                  <p style="margin:0;color:{$muted};font-size:13px;">{{placed_at}}</p>
                </td>
              </tr>
              <tr>
                <td style="padding:0 0 16px 0;">{$intro}</td>
              </tr>
            {$lead}
              <tr>
                <td>
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    {{#items}}
                    <tr>
                      <td style="padding:8px 12px 8px 0;vertical-align:top;border-bottom:1px solid {$line};">{{#child}}— {{/child}}{{name}}{{#options}}<br><span style="color:{$muted};font-size:13px;">{{options}}</span>{{/options}}</td>
                      <td style="padding:8px 12px 8px 0;vertical-align:top;white-space:nowrap;color:{$muted};border-bottom:1px solid {$line};">{{qty}} × {{price}}</td>
                      <td style="padding:8px 0;vertical-align:top;white-space:nowrap;text-align:right;border-bottom:1px solid {$line};">{{total}}</td>
                    </tr>
                    {{/items}}
                  </table>
                </td>
              </tr>
              <tr>
                <td style="padding:12px 0 0 0;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            {$totals}
                  </table>
                </td>
              </tr>
              <tr>
                <td style="padding:16px 0 0 0;">
            {$more}
                </td>
              </tr>
              <tr>
                <td style="padding:24px 0;">{$button}</td>
              </tr>
            </table>
            HTML;

        return [
            'subject' => self::word("letters.{$letter}.subject", $locale),
            'template' => $template."\n",
        ];
    }

    /**
     * Telegram's HTML: short, no subject — what happened, the lines a row each, the sum, the link.
     *
     * @return array{template: string}|null
     */
    public static function telegram(string $event, string $audience, string $locale): ?array
    {
        $letter = self::letter($event, $audience);

        if ($letter === null || ! self::speaks($letter, $locale)) {
            return null;
        }

        $say = static fn (string $key): string => htmlspecialchars(self::word($key, $locale), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
        $manager = $audience === self::MANAGER;

        return ['template' => implode("\n", array_filter([
            '<b>'.$say("letters.{$letter}.subject").'</b>',
            $manager ? '{{customer_name}}{{#customer_phone}}, {{customer_phone}}{{/customer_phone}}' : null,
            $manager ? $say('status').': {{public_status}} → {{status}}' : '{{public_status}}',
            '',
            '{{#items}}',
            '{{#child}}— {{/child}}{{name}} × {{qty}} — {{total}}',
            '{{/items}}',
            '<b>'.$say('payable').': {{payable}}</b>',
            ...self::plainExtras($event, $say),
            ...self::plainEdit($event, $say),
            '',
            $manager ? '<a href="{{order_url}}">'.$say('open-panel').'</a>' : '{{#public_url}}<a href="{{public_url}}">'.$say('open-order').'</a>{{/public_url}}',
            '',
        ], static fn (?string $line): bool => $line !== null))];
    }

    /**
     * Any other provider — one a site added: plain text.
     *
     * @return array{subject: string, template: string}|null
     */
    public static function text(string $event, string $audience, string $locale): ?array
    {
        $letter = self::letter($event, $audience);

        if ($letter === null || ! self::speaks($letter, $locale)) {
            return null;
        }

        $say = static fn (string $key): string => self::word($key, $locale);
        $manager = $audience === self::MANAGER;

        return [
            'subject' => self::word("letters.{$letter}.subject", $locale),
            'template' => implode("\n", array_filter([
                self::word("letters.{$letter}.intro", $locale),
                $manager ? $say('customer').': {{customer_name}}{{#customer_phone}}, {{customer_phone}}{{/customer_phone}}' : null,
                $manager ? $say('status').': {{public_status}} → {{status}}' : null,
                '',
                '{{#items}}',
                '- {{name}} × {{qty}} — {{total}}',
                '{{/items}}',
                $say('payable').': {{payable}}',
                ...self::plainExtras($event, $say),
                ...self::plainEdit($event, $say),
                '',
                $manager ? $say('open-panel').': {{order_url}}' : '{{#public_url}}'.$say('open-order').': {{public_url}}{{/public_url}}',
                '',
            ], static fn (?string $line): bool => $line !== null)),
        ];
    }

    /**
     * The words of a letter: an event in the audience its default is for, and the one more the
     * set a site starts with has — «Order accepted» to the buyer on `orders.placed` (decision 81).
     */
    private static function letter(string $event, string $audience): ?string
    {
        $short = substr($event, strlen('orders.'));

        if ((OrderEvents::AUDIENCE[$event] ?? null) === $audience) {
            return $short;
        }

        return $event === OrderEvents::PLACED && $audience === self::CUSTOMER ? 'placed_customer' : null;
    }

    /**
     * @param  callable(string): string  $say
     * @return list<string>
     */
    private static function extras(string $event, callable $say, string $muted): array
    {
        return array_map(
            static fn (string $name): string => '{{#'.$name.'}}'.self::detail($say($name), '{{'.$name.'}}', $muted).'{{/'.$name.'}}',
            self::extraNames($event),
        );
    }

    /**
     * @param  callable(string): string  $say
     * @return list<string>
     */
    private static function plainExtras(string $event, callable $say): array
    {
        return array_map(
            static fn (string $name): string => '{{#'.$name.'}}'.$say($name).': {{'.$name.'}}{{/'.$name.'}}',
            self::extraNames($event),
        );
    }

    /** @return list<string> */
    private static function extraNames(string $event): array
    {
        return match ($event) {
            OrderEvents::PAID, OrderEvents::RECEIVED => ['reference'],
            OrderEvents::PAYMENT_FAILED, OrderEvents::CANCELLED => ['reason'],
            OrderEvents::REFUNDED => ['refund_amount'],
            OrderEvents::PAYMENT_REQUESTED => ['amount_due'],
            default => [],
        };
    }

    /**
     * Above the lines of «order changed» (§40.2): the manager's words to the buyer, and what came
     * and went — the lines below are the order as it is now.
     *
     * @param  callable(string): string  $say
     */
    private static function lead(string $event, callable $say, string $muted, string $line): string
    {
        if ($event !== OrderEvents::ITEMS_CHANGED) {
            return '';
        }

        $changes = $say('changes');
        $added = $say('added');
        $removed = $say('removed');

        return <<<HTML
              {{#message}}
              <tr>
                <td style="padding:0 0 16px 0;"><p style="margin:0;padding:12px 16px;border-left:3px solid {$line};white-space:pre-line;">{{message}}</p></td>
              </tr>
              {{/message}}
              <tr>
                <td style="padding:0 0 16px 0;">
                  <p style="margin:0 0 6px 0;font-weight:600;">{$changes}</p>
                  {{#changes}}
                  <p style="margin:0 0 4px 0;">{{name}}{{#options}} <span style="color:{$muted};">({{options}})</span>{{/options}}: {{#added}}{$added}, × {{qty_to}}{{/added}}{{#removed}}{$removed}{{/removed}}{{^added}}{{^removed}}{{qty_from}} → {{qty_to}}{{/removed}}{{/added}}</p>
                  {{/changes}}
                </td>
              </tr>
            HTML;
    }

    /**
     * The total it was and the balance after an edit (§40.2): «to pay extra» or «we will give back».
     *
     * @param  callable(string): string  $say
     * @return list<string>
     */
    private static function balanceRows(string $event, callable $say, string $muted): array
    {
        if ($event !== OrderEvents::ITEMS_CHANGED) {
            return [];
        }

        return [
            self::row($say('previous_total'), '{{previous_total}}', $muted),
            '{{#amount_due}}'.self::row('<strong>'.$say('amount_due').'</strong>', '<strong>{{amount_due}}</strong>', $muted).'{{/amount_due}}',
            '{{#amount_over}}'.self::row('<strong>'.$say('amount_over').'</strong>', '<strong>{{amount_over}}</strong>', $muted).'{{/amount_over}}',
        ];
    }

    /**
     * The same in a plain message: the words, the total it was, the balance.
     *
     * @param  callable(string): string  $say
     * @return list<string>
     */
    private static function plainEdit(string $event, callable $say): array
    {
        if ($event !== OrderEvents::ITEMS_CHANGED) {
            return [];
        }

        return [
            $say('previous_total').': {{previous_total}}',
            '{{#amount_due}}'.$say('amount_due').': {{amount_due}}{{/amount_due}}',
            '{{#amount_over}}'.$say('amount_over').': {{amount_over}}{{/amount_over}}',
            '{{#message}}',
            '{{message}}',
            '{{/message}}',
        ];
    }

    private static function row(string $label, string $value, string $muted): string
    {
        return "<tr><td style=\"padding:2px 12px 2px 0;color:{$muted};\">{$label}</td><td style=\"padding:2px 0;text-align:right;white-space:nowrap;\">{$value}</td></tr>";
    }

    private static function detail(string $label, string $value, string $muted): string
    {
        return "<p style=\"margin:0 0 6px 0;\"><span style=\"color:{$muted};\">{$label}:</span> {$value}</p>";
    }

    /** Whether the words of the letter are there in this language — the package's or the site's. */
    private static function speaks(string $letter, string $locale): bool
    {
        return Lang::hasForLocale("webx-orders::notifications.letters.{$letter}.subject", $locale)
            && Lang::hasForLocale('webx-orders::notifications.words.payable', $locale);
    }

    private static function word(string $key, string $locale): string
    {
        $full = str_starts_with($key, 'letters.') ? 'webx-orders::notifications.'.$key : 'webx-orders::notifications.words.'.$key;
        $line = trans($full, [], $locale);

        return is_string($line) ? $line : $key;
    }
}
