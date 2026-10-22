<?php

declare(strict_types=1);

namespace WebxUi\Inbox\View\Components;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Inbox\Antispam\Captcha;
use WebxUi\Inbox\Antispam\Guard;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
// The component and the model are both called a form, and here the component wins the name:
// `componentNamespace` looks up the class by what is written after `::`, so `<x-webx-inbox::form>`
// can only be `View\Components\Form`.
use WebxUi\Inbox\Models\Form as FormModel;
use WebxUi\Inbox\Rendering\Assets;
use WebxUi\Inbox\Rendering\LegacyAssets;
use WebxUi\Inbox\Storage\FileStore;
use WebxUi\Inbox\Submissions\Rules;
use WebxUi\Inbox\Support\Forms;
use WebxUi\Inbox\Support\Placement;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `<x-webx-inbox::form slug="contact" />` — a form of the panel, printed on the site (§10).
 *
 * Drawn by the widgets' form engine (WIDGETS §17): `<x-webx-form :csrf="false">` around an
 * `<x-webx-field>` per field, checked in the browser by the intake's own rules in the validator's
 * words (`FormRules`), answered by `FormResponse`. What is the inbox's — the honeypot, the
 * timestamp, the captcha, the hidden marks the intake reads — is printed inside it.
 *
 * The form works with JavaScript switched off: a plain POST, and the page back with the errors
 * under the fields or the thank-you under them.
 *
 * `again` — the thank-you in place of the fields, with a button that brings them back empty
 * (`<x-webx-form again>`): a form that is filled in more than once, a request after a request.
 *
 * A view the site published before W6.4 (no `<x-webx-form>` in it) is still handed everything it
 * was handed then, and its old script: an alias period, so a site that rewrote the form keeps a
 * working one until it moves to the engine (spec WIDGETS §20, W6.4; the audit's
 * `inbox.legacy_views` names it).
 */
final class Form extends Component
{
    /** @var array<string, bool> which views were written before the engine, by path */
    private static array $legacy = [];

    /** @var array<string, mixed>|null the old input of a submission that came back with errors */
    private ?array $old = null;

    /**
     * @param  array<string, mixed>  $values  what the site fills in: hidden fields, mostly
     */
    public function __construct(
        private readonly Forms $forms,
        private readonly Guard $guard,
        private readonly Captcha $captcha,
        private readonly Assets $assets,
        private readonly FileStore $files,
        private readonly Rules $rules,
        private readonly ViewFactory $views,
        private readonly Request $request,
        private readonly ?string $slug = null,
        private readonly ?FormModel $form = null,
        private readonly array $values = [],
        private readonly ?string $action = null,
        private readonly ?string $view = null,
        // A constructor argument so that Blade takes it out of `$attributes`: otherwise it
        // would be printed on `<form>` as an attribute nobody reads.
        private readonly ?string $placement = null,
        private readonly bool $again = false,
    ) {}

    public function render(): View|string
    {
        $form = $this->form();

        if (! $form instanceof FormModel) {
            // A slug nobody has a form for, or a form that is switched off. Both print
            // nothing: a page that says "form not found" to a customer is worse than a page
            // with one section missing, and the panel is where somebody finds out.
            return '';
        }

        $view = $this->view ?? 'webx-inbox::form';
        $legacy = $this->legacy($view);
        $fields = $form->liveFields;
        $formId = $this->formId($form, $legacy ? 'wx-form-' : 'webx-inbox-');
        $placement = $this->placement($form);
        $mine = $this->isMine($form);

        return $this->views->make($view, [
            'form' => $form,
            'formId' => $formId,
            'fields' => $fields,
            'controls' => $this->controls($fields, $formId),
            'rules' => FormRules::for($this->rules->for($form), $this->rules->attributes($form)),
            'action' => $this->action($form),
            'page' => $this->request->fullUrl(),
            // What language this page came out in, for the intake to answer in the same one.
            'locale' => app()->getLocale(),
            'honeypot' => $this->guard->honeypotField($form),
            'timestampField' => $this->guard->timestampField(),
            'timestamp' => $this->guard->timestamp(),
            'captcha' => $this->captcha->widget($form),
            'captchaErrors' => $this->sent($formId) ? ($this->errors()['captcha'] ?? []) : [],
            'assets' => $legacy ? new LegacyAssets($this->assets) : $this->assets,
            'submitText' => $this->submitText($form),
            'placement' => $placement,
            // What a view of before W6.4 reads (an alias period): the engine's view needs none of it.
            'multipart' => $fields->contains(static fn (Field $field): bool => $field->type === FieldType::File),
            // Not `errors`: that name belongs to Laravel's own bag.
            'invalid' => $mine ? $this->errors() : [],
            'files' => $this->files,
            'message' => $this->message($form),
            'values' => $this->prefill($fields, $mine),
            'again' => $this->again,
        ]);
    }

    /**
     * Each field as `<x-webx-field>` takes it: the type of the panel is one of the widgets' (§4 —
     * eleven, all among them), the name `fields.<key>` — `fields[<key>]` in the HTML, which is
     * what the intake reads and what its errors are keyed by.
     *
     * The field finds its value itself — `old()` of this form, else the site's `:values` — and
     * its checks among the form's rules. A select or a group of dots with nothing to choose from
     * is a half-written field; it is left out with a line in the log rather than taking the page
     * down.
     *
     * @param  Collection<int, Field>  $fields
     * @return list<array<string, mixed>>
     */
    private function controls(Collection $fields, string $formId): array
    {
        $controls = [];

        foreach ($fields as $field) {
            $key = $field->key();
            $choices = $field->choices();

            if (in_array($field->type, [FieldType::Select, FieldType::Radio], true) && $choices === []) {
                Log::warning('webx-inbox: a field with nothing to choose from is not printed.', ['form' => $field->form_id, 'field' => $key]);

                continue;
            }

            $title = trim((string) $field->title);
            $help = trim((string) $field->help);
            $placeholder = trim((string) $field->placeholder);
            $rows = $field->option('rows');

            $controls[] = [
                'type' => $field->type->value,
                'name' => 'fields.'.$key,
                'id' => $formId.'-'.$key,
                'label' => $title !== '' ? $title : null,
                'hint' => $help !== '' ? $help : null,
                'placeholder' => $placeholder !== '' ? $placeholder : null,
                'value' => $this->values[$key] ?? $this->values[(string) $field->name] ?? null,
                'choices' => $choices,
                'multiple' => $field->type === FieldType::File && $field->isMultiple(),
                'rows' => $field->type === FieldType::Textarea ? (is_numeric($rows) && (int) $rows > 0 ? (int) $rows : 5) : null,
                // The panel offers "full width" per field; on the site it is a class, and the base
                // look puts two half ones to a row where the form has the room.
                'half' => ! $field->is_fullsize,
                // The consent's words are a sentence with a link in it, written in the panel:
                // printed as they are, the same trust as the thank-you, with the site's shortcodes.
                'consent' => $field->type === FieldType::Consent ? Container::getInstance()->make(Shortcodes::class)->htmlIn((string) $field->optionText('text', $title)) : null,
            ];
        }

        return $controls;
    }

    /**
     * Whether the view is one written before the engine — a site's own, published from this
     * package before W6.4 — and is handed the old variables and the old script.
     */
    private function legacy(string $view): bool
    {
        try {
            $path = $this->views->getFinder()->find($view);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return self::$legacy[$path] ??= ! str_contains((string) @file_get_contents($path), '<x-webx-form');
    }

    /**
     * Where the form stands, when that is a name (§11.2).
     *
     * Anything else is dropped with a line in the log rather than an exception: the form on
     * the page matters more than a typo in one of its attributes.
     */
    private function placement(FormModel $form): ?string
    {
        if ($this->placement === null || $this->placement === '') {
            return null;
        }

        $placement = Placement::of($this->placement);

        if ($placement === null) {
            Log::warning('webx-inbox: the placement of a form is not a name and is ignored.', [
                'form' => $form->slug,
                'placement' => $this->placement,
            ]);
        }

        return $placement;
    }

    /**
     * The form's `id`, unique on the page — and the same on every rendering of it, so the page
     * that comes back after a POST knows which of its forms was sent (`_webx_form`).
     *
     * The same form often stands on one page twice — a subscription in the footer and again in a
     * dialog. The first copy keeps the plain name; each further copy gets a number. Counted on the
     * request, which is exactly the life of one page.
     */
    private function formId(FormModel $form, string $prefix): string
    {
        $base = $prefix.$form->slug;
        $seen = $this->request->attributes->get('webx-inbox.form-ids', []);
        $seen = is_array($seen) ? $seen : [];

        $count = (int) ($seen[$base] ?? 0) + 1;
        $seen[$base] = $count;
        $this->request->attributes->set('webx-inbox.form-ids', $seen);

        return $count === 1 ? $base : $base.'-'.$count;
    }

    private function form(): ?FormModel
    {
        if ($this->form instanceof FormModel) {
            return $this->form->is_enabled ? $this->form->loadMissing('liveFields') : null;
        }

        return $this->slug === null ? null : $this->forms->enabled($this->slug);
    }

    private function action(FormModel $form): string
    {
        if ($this->action !== null) {
            return $this->action;
        }

        return url(trim((string) config('webx-inbox.path', 'webx/forms'), '/').'/'.$form->slug);
    }

    /** The page came back from this very form — `_webx_form` names it. */
    private function sent(string $formId): bool
    {
        return ($this->old()[FormResponse::FORM] ?? null) === $formId;
    }

    /**
     * What every control of a view of before W6.4 starts with: the old input of a refused
     * submission, and otherwise whatever the site handed in.
     *
     * @param  Collection<int, Field>  $fields
     * @return array<string, mixed>
     */
    private function prefill(Collection $fields, bool $mine): array
    {
        $values = [];
        $old = $mine ? ($this->old()['fields'] ?? []) : [];

        foreach ($fields as $field) {
            $key = $field->key();

            $values[$key] = is_array($old) && array_key_exists($key, $old)
                ? $old[$key]
                : ($this->values[$key] ?? $this->values[(string) $field->name] ?? null);
        }

        return $values;
    }

    /**
     * The errors of the last submission, as `key => [message, …]`.
     *
     * Read out of the shared bag here rather than in the view, because `$errors` exists only
     * inside the `web` group — and a page that prints a form is not obliged to be in it.
     *
     * @return array<string, list<string>>
     */
    private function errors(): array
    {
        $shared = $this->views->shared('errors');

        if (! $shared instanceof ViewErrorBag && $this->request->hasSession()) {
            $shared = $this->request->session()->get('errors');
        }

        $bag = match (true) {
            $shared instanceof ViewErrorBag => $shared->getBag('default'),
            $shared instanceof MessageBag => $shared,
            default => null,
        };

        if (! $bag instanceof MessageBag) {
            return [];
        }

        $errors = [];

        foreach ($bag->getMessages() as $key => $messages) {
            // `fields.extras.1` is the second tick of one field, and it belongs under that
            // field along with everything else said about it.
            $key = (string) preg_replace('/\.\d+$/', '', (string) $key);

            $errors[$key] = array_values(array_unique([...$errors[$key] ?? [], ...array_map('strval', $messages)]));
        }

        return $errors;
    }

    /**
     * Whether what is in the session belongs to this form, for a view of before W6.4: every form
     * prints `webx_form`, so what came back names the form it came from.
     */
    private function isMine(FormModel $form): bool
    {
        return ($this->old()['webx_form'] ?? null) === $form->slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function old(): array
    {
        if ($this->old !== null) {
            return $this->old;
        }

        if (! $this->request->hasSession()) {
            return $this->old = [];
        }

        $old = $this->request->session()->getOldInput();

        return $this->old = is_array($old) ? $old : [];
    }

    /**
     * The thank-you of a submission that went through without JavaScript from a view of before
     * W6.4, put in the session by the intake and taken out here by the form it was sent from.
     *
     * @return array<string, mixed>|null
     */
    private function message(FormModel $form): ?array
    {
        if (! $this->request->hasSession()) {
            return null;
        }

        $flash = $this->request->session()->get('webx-inbox');

        if (! is_array($flash) || ($flash['form'] ?? null) !== $form->slug) {
            return null;
        }

        return $flash;
    }

    private function submitText(FormModel $form): string
    {
        $text = $form->option('design.submit-text');

        if (is_array($text)) {
            $locale = app()->getLocale();
            $text = $text[$locale] ?? $text[(string) config('app.fallback_locale')] ?? reset($text);
        }

        return is_string($text) && trim($text) !== ''
            ? trim($text)
            : (string) trans('webx-inbox::form.submit');
    }
}
