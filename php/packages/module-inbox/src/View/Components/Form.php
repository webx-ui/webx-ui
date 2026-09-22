<?php

declare(strict_types=1);

namespace WebxUi\Inbox\View\Components;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;
use WebxUi\Inbox\Antispam\Captcha;
use WebxUi\Inbox\Antispam\Guard;
use WebxUi\Inbox\Models\Field;
// The component and the model are both called a form, and here the component wins the name:
// `componentNamespace` looks up the class by what is written after `::`, so `<x-webx-inbox::form>`
// can only be `View\Components\Form`.
use WebxUi\Inbox\Models\Form as FormModel;
use WebxUi\Inbox\Rendering\Assets;
use WebxUi\Inbox\Storage\FileStore;
use WebxUi\Inbox\Support\Forms;

/**
 * `<x-webx-inbox::form slug="contact" />` — a form of the panel, printed on the site (§10).
 *
 * Everything visible is a view of the package, and every one of them is publishable: the markup
 * here is the minimum that works, not a design, and the first thing a real site does is publish
 * the lot and rewrite it. That is the arrangement the reference implementation arrived at too —
 * it kept the intake and replaced the controls.
 *
 * The form works with JavaScript switched off. That is not a courtesy to anybody's principles:
 * the script is one file served from this package, and a form that only submits once that file
 * has arrived is a form that loses the enquiry somebody sent on a train.
 */
final class Form extends Component
{
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
        private readonly ViewFactory $views,
        private readonly Request $request,
        private readonly ?string $slug = null,
        private readonly ?FormModel $form = null,
        private readonly array $values = [],
        private readonly ?string $action = null,
        private readonly ?string $view = null,
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

        $fields = $form->liveFields;
        $mine = $this->isMine($form);

        return $this->views->make($this->view ?? 'webx-inbox::form', [
            'form' => $form,
            'fields' => $fields,
            'action' => $this->action($form),
            'multipart' => $fields->contains(static fn (Field $field): bool => $field->type->value === 'file'),
            'page' => $this->request->fullUrl(),
            // What language this page came out in, for the intake to answer in the same one.
            'locale' => app()->getLocale(),
            // Not `errors`: that name belongs to Laravel's own bag, and a published view has
            // every right to expect it to be the usual thing.
            'invalid' => $mine ? $this->errors() : [],
            // What a file field may be handed, which is the module's list and not the field's
            // alone — the view has no business assembling that.
            'files' => $this->files,
            'message' => $this->message($form),
            'honeypot' => $this->guard->honeypotField($form),
            'timestampField' => $this->guard->timestampField(),
            'timestamp' => $this->guard->timestamp(),
            'captcha' => $this->captcha->widget($form),
            'assets' => $this->assets,
            'submitText' => $this->submitText($form),
            'values' => $this->prefill($fields, $mine),
        ]);
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

    /**
     * What every control starts with: the old input of a refused submission, and otherwise
     * whatever the site handed in.
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
     * Whether what is in the session belongs to this form.
     *
     * A page may carry two forms, and without the marker both would light up red over one
     * refusal. Every form prints `webx_form`, so what came back names the form it came from —
     * in the old input for the errors, and in the flash for the thank-you.
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
     * The thank-you of a submission that went through without JavaScript, put in the session
     * by the intake and taken out here by the form it was sent from.
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
