<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What an order holds its contacts to, and the words a refusal is put in — one place for the
 * panel, MCP and the checkout.
 *
 * Laravel's own words name a field by its key: «The customer.phone field is required when
 * customer.email is not present.» reached the panel under the field and in the toast. Every rule
 * here has a sentence of the module's, in the language of whoever is reading, and every field a
 * human name for the rules that do not.
 */
final class ContactRules
{
    /** A phone as a buyer writes it: a +, digits, spaces, brackets, dots and dashes. */
    public const PHONE = '/^\+?[0-9][0-9 ().\-]*$/';

    /** Field → the word for it in the `webx-orders::checkout.fields` group. */
    private const FIELDS = [
        'customer.first_name' => 'first-name',
        'customer.last_name' => 'last-name',
        'customer.phone' => 'phone',
        'customer.email' => 'email',
        'recipient.first_name' => 'recipient-first-name',
        'recipient.last_name' => 'recipient-last-name',
        'recipient.phone' => 'recipient-phone',
        'comment' => 'comment',
    ];

    /** The longest each field may be. */
    private const MAX = [
        'customer.first_name' => 190,
        'customer.last_name' => 190,
        'customer.phone' => 32,
        'customer.email' => 190,
        'recipient.first_name' => 190,
        'recipient.last_name' => 190,
        'recipient.phone' => 32,
        'comment' => 5000,
        'manager_note' => 5000,
    ];

    /**
     * The rules of an order's contacts (§6.1): a first name, and a phone or an e-mail — either,
     * so neither is required on its own. A recipient, when there is one, needs a first name.
     *
     * @return array<string, list<string>>
     */
    public static function rules(bool $recipient): array
    {
        $phone = ['nullable', 'string', 'max:32', 'regex:'.self::PHONE];
        $rules = [
            'customer.first_name' => ['required', 'string', 'max:190'],
            'customer.last_name' => ['nullable', 'string', 'max:190'],
            'customer.phone' => [...$phone, 'required_without:customer.email'],
            'customer.email' => ['nullable', 'string', 'max:190', 'email:filter', 'required_without:customer.phone'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'manager_note' => ['nullable', 'string', 'max:5000'],
        ];

        if ($recipient) {
            $rules += [
                'recipient.first_name' => ['required', 'string', 'max:190'],
                'recipient.last_name' => ['nullable', 'string', 'max:190'],
                'recipient.phone' => $phone,
            ];
        }

        return $rules;
    }

    /**
     * The same rules as the card's screen meets them: there the recipient's fields are always
     * on view, and emptied whole they mean «the buyer takes it» — so its first name is required
     * only beside the rest of it, and is drawn without an asterisk.
     *
     * @return array<string, list<string>>
     */
    public static function screen(): array
    {
        $rules = self::rules(true);
        $rules['recipient.first_name'] = ['nullable', 'string', 'max:190', 'required_with:recipient.last_name,recipient.phone'];

        return $rules;
    }

    /**
     * A sentence for every rule of {@see rules()} that a form can break.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $either = (string) __('webx-orders::checkout.errors.phone-or-email');
        $messages = [
            'customer.phone.required_without' => $either,
            'customer.email.required_without' => $either,
            'customer.phone.regex' => (string) __('webx-orders::checkout.errors.phone'),
            'recipient.phone.regex' => (string) __('webx-orders::checkout.errors.phone'),
            'customer.email.email' => (string) __('webx-orders::checkout.errors.email'),
        ];

        foreach (self::attributes() as $field => $name) {
            $messages[$field.'.required'] = (string) __('webx-orders::checkout.errors.required', ['field' => $name]);
            $messages[$field.'.required_with'] = $messages[$field.'.required'];
            $messages[$field.'.max'] = (string) __('webx-orders::checkout.errors.too-long', ['field' => $name, 'max' => self::MAX[$field]]);
        }

        return $messages;
    }

    /**
     * The fields by the names the forms give them, for whatever rule has no sentence of its own.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        $names = [];

        foreach (self::FIELDS as $field => $word) {
            $names[$field] = (string) __('webx-orders::checkout.fields.'.$word);
        }

        $names['manager_note'] = (string) __('webx-orders::screen.manager-note');

        return $names;
    }
}
