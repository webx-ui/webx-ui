<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Waybills;

use WebxUi\Delivery\NovaPoshta\Api\ApiError;

/**
 * The carrier's refusals of a waybill in the manager's words (§8.7): the frequent ones — a name
 * in other letters, a phone of another shape, a weight over the point's — get a line of the
 * dictionary and the field of the dialog to fix; every refusal keeps the carrier's own text and
 * its codes in brackets, for the carrier's support.
 *
 * The texts are matched after the documentation and the errors people publish about the API: the
 * writing methods are never called on a client's key (decision 19), so none of this is confirmed
 * on a key. A text that matches nothing is shown as the carrier said it.
 */
final class CarrierErrors
{
    /**
     * @param  bool  $recipient  the refusal of `Counterparty.save` or `ContactPerson.save`: a name
     *                           it complains about is the recipient's
     * @return array{word: string, field: string}|null
     */
    public static function known(ApiError $error, bool $recipient = false): ?array
    {
        foreach ($error->errors as $text) {
            if (preg_match('/phone/i', $text) === 1) {
                return ['word' => 'phone', 'field' => 'recipient_phone'];
            }

            if ($recipient && preg_match('/last\s*name/i', $text) === 1) {
                return ['word' => 'name_letters', 'field' => 'recipient_last_name'];
            }

            if ($recipient && preg_match('/first\s*name|name/i', $text) === 1) {
                return ['word' => 'name_letters', 'field' => 'recipient_first_name'];
            }

            if (preg_match('/weight/i', $text) === 1 && preg_match('/max|exceed|more|greater|allow|limit/i', $text) === 1) {
                return ['word' => 'weight_too_much', 'field' => 'extra.weight'];
            }
        }

        return null;
    }

    /** The carrier's own words and codes: «Text; text (20000100001)». */
    public static function said(ApiError $error): string
    {
        $text = $error->errors === [] ? '—' : implode('; ', $error->errors);

        return $error->codes === [] ? $text : $text.' ('.implode(', ', $error->codes).')';
    }
}
