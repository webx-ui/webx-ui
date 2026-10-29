<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Steps;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\CheckoutSettings;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Site\VisitorOrders;
use WebxUi\Users\Urls;

/**
 * The buyer and the recipient (spec §20.3): the first name always, the last name, the phone and the
 * e-mail as the tab «Checkout» says, and a recipient behind a checkbox. A signed-in visitor's
 * contacts fill the fields until the draft holds the buyer's own (§25); what goes into the order is
 * what the buyer typed, the profile is not changed.
 */
final readonly class ContactsStep implements NamesErrors, Step
{
    public const string KEY = 'contacts';

    /** `+` and digits with separators — what `Orders::place()` accepts; no country's mask (§20.3). */
    private const string PHONE = '/^\+?[0-9][0-9 ().\-]*$/';

    public function __construct(
        private CheckoutSettings $settings,
        private CheckoutLinks $links,
        private Validation $validation,
        private Views $views,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-orders::checkout.steps.contacts');
    }

    public function applies(CheckoutContext $context): bool
    {
        return true;
    }

    public function render(CheckoutContext $context): Htmlable
    {
        return new HtmlString($this->views->make('webx-orders::checkout.steps.contacts', [
            'action' => $this->links->step(self::KEY, $context->locale),
            'customer' => $this->customer($context),
            'recipient' => $this->recipient($context),
            'fields' => [
                'last_name' => $this->settings->field('last_name'),
                'phone' => $this->settings->field('phone'),
                'email' => $this->settings->field('email'),
            ],
            'withRecipient' => $this->settings->recipient(),
            'visitor' => $context->visitor,
            // «Sign in» and «Sign out» of the visitors' module, where the site has it (§25).
            'signIn' => $context->visitor === null && VisitorOrders::installed(),
            'signOut' => $context->visitor !== null && VisitorOrders::installed()
                ? ['action' => app(Urls::class)->to('sign-out'), 'back' => $this->links->page($context->locale)]
                : null,
        ])->render());
    }

    public function summary(CheckoutContext $context): ?string
    {
        $customer = $this->customer($context);
        $name = trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $reach = $customer['phone'] ?? $customer['email'] ?? null;

        return $reach !== null && $reach !== '' ? "{$name}, {$reach}" : $name;
    }

    public function save(CheckoutContext $context, array $input, CheckoutDraft $draft): void
    {
        $data = $this->data($input);
        $this->validation->make($data, $this->rules($data), $this->messages())->validate();

        $draft->customer = $data['customer'];
        $draft->recipient = $data['recipient'];
    }

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        $data = [
            'customer' => $this->customer($context),
            'recipient' => $this->settings->recipient() ? $this->recipient($context) : null,
        ];
        $validator = $this->validation->make($data, $this->rules($data), $this->messages());

        if (! $validator->fails()) {
            return [];
        }

        $errors = [];
        $failed = $validator->failed();

        foreach ($validator->errors()->messages() as $field => $messages) {
            $rule = Str::snake((string) array_key_first($failed[$field] ?? ['invalid' => []]));
            $code = match (true) {
                str_starts_with($rule, 'required_without') => 'contacts.phone_or_email',
                str_starts_with($rule, 'required') => 'contacts.'.self::name($field).'_required',
                default => 'contacts.'.self::name($field).'_invalid',
            };
            $errors[] = new StepError(self::KEY, $field, (string) $messages[0], $code);
        }

        return $errors;
    }

    public function errorCodes(): array
    {
        $codes = ['contacts.phone_or_email' => 'neither a phone nor an e-mail, and the settings ask for one of the two'];

        foreach (['first_name', 'last_name', 'phone', 'email', 'recipient_first_name', 'recipient_last_name', 'recipient_phone'] as $field) {
            $codes["contacts.{$field}_required"] = "{$field} is required";

            if (! str_ends_with($field, 'name')) {
                $codes["contacts.{$field}_invalid"] = "{$field} is not one";
            }
        }

        return $codes;
    }

    public function contribute(CheckoutContext $context, PlaceOrderBuilder $order): void
    {
        $customer = $this->customer($context);
        $recipient = $this->settings->recipient() ? $this->recipient($context) : null;

        $order->customer(new Contact(
            (string) ($customer['first_name'] ?? ''),
            $this->shown('last_name') ? ($customer['last_name'] ?? null) : null,
            $this->shown('phone') ? ($customer['phone'] ?? null) : null,
            $this->shown('email') ? ($customer['email'] ?? null) : null,
        ));
        $order->recipient($recipient !== null ? new Contact(
            (string) ($recipient['first_name'] ?? ''),
            $this->shown('last_name') ? ($recipient['last_name'] ?? null) : null,
            $recipient['phone'] ?? null,
        ) : null);
    }

    /**
     * The buyer as the fields show them: the draft once the buyer saved the step, before that the
     * signed-in visitor's own.
     *
     * @return array{first_name?: string|null, last_name?: string|null, phone?: string|null, email?: string|null}
     */
    public function customer(CheckoutContext $context): array
    {
        $saved = $context->saved('customer');

        if (is_array($saved)) {
            return $saved;
        }

        $visitor = $context->visitor;

        return $visitor === null ? [] : [
            'first_name' => $visitor->firstName,
            'last_name' => $visitor->lastName,
            'phone' => $visitor->phone,
            'email' => $visitor->email,
        ];
    }

    /**
     * Someone else receives the order, or null — the buyer does.
     *
     * @return array{first_name?: string|null, last_name?: string|null, phone?: string|null}|null
     */
    public function recipient(CheckoutContext $context): ?array
    {
        $saved = $context->saved('recipient');

        return is_array($saved) ? $saved : null;
    }

    /**
     * The form's fields as the draft keeps them: trimmed, empty as null, a hidden field and an
     * unticked recipient dropped.
     *
     * @param  array<string, mixed>  $input
     * @return array{customer: array<string, string|null>, recipient: array<string, string|null>|null}
     */
    private function data(array $input): array
    {
        $customer = is_array($input['customer'] ?? null) ? $input['customer'] : [];
        $recipient = is_array($input['recipient'] ?? null) ? $input['recipient'] : [];
        $text = static function (mixed $value): ?string {
            $value = is_scalar($value) ? trim((string) $value) : '';

            return $value === '' ? null : $value;
        };

        $data = [
            'customer' => [
                'first_name' => $text($customer['first_name'] ?? null),
                'last_name' => $this->shown('last_name') ? $text($customer['last_name'] ?? null) : null,
                'phone' => $this->shown('phone') ? $text($customer['phone'] ?? null) : null,
                'email' => $this->shown('email') ? $text($customer['email'] ?? null) : null,
            ],
            'recipient' => null,
        ];

        if ($this->settings->recipient() && filter_var($recipient['other'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $data['recipient'] = [
                'first_name' => $text($recipient['first_name'] ?? null),
                'last_name' => $this->shown('last_name') ? $text($recipient['last_name'] ?? null) : null,
                'phone' => $text($recipient['phone'] ?? null),
            ];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function rules(array $data): array
    {
        $rules = [
            'customer.first_name' => ['required', 'string', 'max:190'],
            'customer.last_name' => [$this->settings->field('last_name') === CheckoutSettings::REQUIRED ? 'required' : 'nullable', 'string', 'max:190'],
            'customer.phone' => [$this->settings->field('phone') === CheckoutSettings::REQUIRED ? 'required' : 'nullable', 'string', 'max:32', 'regex:'.self::PHONE],
            'customer.email' => [$this->settings->field('email') === CheckoutSettings::REQUIRED ? 'required' : 'nullable', 'string', 'max:190', 'email:filter'],
        ];

        // An order needs a phone or an e-mail (§6.1): with neither required, one of the two shown.
        if ($this->shown('phone')) {
            $rules['customer.phone'][] = $this->shown('email') ? 'required_without:customer.email' : 'required';
        }

        if ($this->shown('email') && ! $this->shown('phone')) {
            $rules['customer.email'][] = 'required';
        }

        if (($data['recipient'] ?? null) !== null) {
            $rules += [
                'recipient.first_name' => ['required', 'string', 'max:190'],
                'recipient.last_name' => [$this->settings->field('last_name') === CheckoutSettings::REQUIRED ? 'required' : 'nullable', 'string', 'max:190'],
                'recipient.phone' => ['required', 'string', 'max:32', 'regex:'.self::PHONE],
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        $attributes = [
            'customer.first_name' => 'first-name',
            'customer.last_name' => 'last-name',
            'customer.phone' => 'phone',
            'customer.email' => 'email',
            'recipient.first_name' => 'recipient-first-name',
            'recipient.last_name' => 'recipient-last-name',
            'recipient.phone' => 'recipient-phone',
        ];
        $messages = ['customer.phone.required_without' => (string) __('webx-orders::checkout.errors.phone-or-email')];

        foreach ($attributes as $field => $word) {
            $messages[$field.'.required'] = (string) __('webx-orders::checkout.errors.required', ['field' => (string) __('webx-orders::checkout.fields.'.$word)]);
        }

        $messages['customer.phone.regex'] = $messages['recipient.phone.regex'] = (string) __('webx-orders::checkout.errors.phone');
        $messages['customer.email.email'] = (string) __('webx-orders::checkout.errors.email');

        return $messages;
    }

    private function shown(string $field): bool
    {
        return $this->settings->field($field) !== CheckoutSettings::HIDDEN;
    }

    /** `customer.phone` → `phone`, `recipient.phone` → `recipient_phone`. */
    private static function name(string $field): string
    {
        return str_starts_with($field, 'customer.') ? substr($field, strlen('customer.')) : str_replace('.', '_', $field);
    }
}
