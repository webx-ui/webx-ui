<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Inbox\Antispam\Captcha;
use WebxUi\Inbox\Antispam\Guard;
use WebxUi\Inbox\Antispam\Inspection;
use WebxUi\Inbox\Antispam\Refusals;
use WebxUi\Inbox\Antispam\Throttle;
use WebxUi\Inbox\Antispam\Verdict;
use WebxUi\Inbox\Mail\Notifier;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Notifications\Channels;
use WebxUi\Inbox\Panel\FormOptions;
use WebxUi\Inbox\Submissions\Enclosed;
use WebxUi\Inbox\Submissions\Enclosures;
use WebxUi\Inbox\Submissions\Intake;
use WebxUi\Inbox\Submissions\Rules;
use WebxUi\Inbox\Submissions\ThankYou;
use WebxUi\Inbox\Support\Forms;
use WebxUi\Localization\Locales;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * The public door (§6).
 *
 * It answers the way every form of the site is answered (WIDGETS §17.4), because the form works
 * with and without JavaScript: `FormResponse` — JSON when the request asks for it, otherwise a
 * redirect back to the page with the thank-you — and Laravel's own 422 or `back()->withErrors()`
 * for a refusal. Nothing else about the two paths differs.
 */
final class SubmitController
{
    public function __construct(
        private readonly Guard $guard,
        private readonly Rules $rules,
        private readonly Intake $intake,
        private readonly Notifier $notifier,
        private readonly ValidationFactory $validator,
        private readonly LoggerInterface $log,
        private readonly Forms $forms,
        private readonly Throttle $throttle,
        private readonly Refusals $refusals,
        private readonly Channels $channels,
        private readonly LinkUrls $urls,
        private readonly Locales $locales,
        private readonly Enclosures $enclosures,
    ) {}

    public function __invoke(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        // The same instance the rate limiter looked at a moment ago, so a submission costs one
        // query for the form rather than two.
        $form = $this->forms->enabled($slug);

        if ($form === null) {
            // A form that is switched off does not exist as far as the site is concerned, and
            // that is the honest answer: "disabled" would tell a stranger it is there.
            throw new NotFoundHttpException;
        }

        // Before anything else is looked at: an address that has already left as many
        // submissions as the form takes is not let in to leave one more (§7).
        $this->throttle->check($form, $request);

        $inspection = $this->guard->inspect($form, $request);

        if ($inspection->verdict === Verdict::Trap) {
            // A robot is thanked and nothing is written. Telling it which trick was seen is
            // the one thing that makes the next attempt harder to catch (§7).
            $this->log->info('webx-inbox: a submission to {form} was trapped.', [
                'form' => $form->slug,
                'ip' => $request->ip(),
                'reason' => $inspection->reason,
            ]);

            // Counted as the success it is told it was, so the robot meets the same limit a
            // person would.
            $this->throttle->hit($form, $request);
            $this->refusals->record($form);

            return $this->accepted($form, $request, ThankYou::sent($form, $request));
        }

        if ($inspection->verdict === Verdict::Reject) {
            // Which layer said no and what the provider answered — without which a captcha
            // refusing every visitor reads exactly like a robot being kept out.
            $this->log->info('webx-inbox: a submission to {form} was refused by the antispam ({reason}).', [
                'form' => $form->slug,
                'ip' => $request->ip(),
                'reason' => $inspection->reason,
                ...$inspection->details,
            ]);

            // Counted, so the audit can tell a form robots have found from one they have not.
            $this->refusals->record($form);

            throw ValidationException::withMessages($this->refusal($inspection));
        }

        [$values, $enclosed] = $this->validate($form, $request);

        $submission = $this->intake->receive($form, $values, $request, enclosures: $enclosed);

        // Only now: a refused or invalid attempt is somebody correcting a mistake, and it
        // counts towards the route's higher limit instead.
        $this->throttle->hit($form, $request);

        // Last, and on its own: everything above it is the submission, and a mail server that
        // is down must not undo any of it (§2.10). A form that tells its channels instead is
        // sent to them by the stored event (`SendToChannels`), not written a letter as well.
        if (! $this->channels->through($form)) {
            $this->notifier->send($submission);
        }

        return $this->accepted($form, $request, ThankYou::of($submission));
    }

    /**
     * What the visitor is told.
     *
     * One sentence for every layer, so a robot learns nothing from which of them it tripped —
     * except the captcha a person can answer, which is drawn on the page for anybody to see:
     * saying what to do with it gives a robot nothing new, and a person who did not tick the
     * box is told to tick it rather than to wait a moment. It is filed under `captcha`, so
     * the form shows it beside the widget.
     *
     * An Invisible or v3 reCAPTCHA without an answer is a page without JavaScript; a failed v3
     * is a score, which the visitor cannot argue with, and gets the sentence everybody gets.
     *
     * @return array<string, list<string>>
     */
    private function refusal(Inspection $inspection): array
    {
        $type = $inspection->details['type'] ?? Captcha::CHECKBOX;
        $interactive = $type !== Captcha::V3;

        $key = match (true) {
            $inspection->reason === Inspection::CAPTCHA_MISSING && $type !== Captcha::CHECKBOX => 'captcha-script',
            $inspection->reason === Inspection::CAPTCHA_MISSING,
            $inspection->reason === Inspection::CAPTCHA_FAILED && $interactive => 'captcha',
            default => null,
        };

        return $key === null
            ? ['form' => [(string) trans('webx-inbox::errors.refused')]]
            : ['captcha' => [(string) trans('webx-inbox::errors.'.$key)]];
    }

    /**
     * The answers, and what modules enclosed beside them (§2.20).
     *
     * The enclosures are asked inside the same check as the fields, so a refused size and an
     * empty phone come back in one answer — not the phone first and the size on the second try.
     *
     * @return array{0: array<string, mixed>, 1: array<string, Enclosed>}
     */
    private function validate(Form $form, Request $request): array
    {
        $enclosed = [];

        $validator = $this->validator->make(
            $request->all(),
            $this->rules->for($form),
            [],
            $this->rules->attributes($form),
        );

        $validator->after(function ($validator) use ($form, $request, &$enclosed): void {
            try {
                $enclosed = $this->enclosures->receive($form, $request);
            } catch (ValidationException $refused) {
                foreach ($refused->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });

        $validated = $validator->validate();
        $fields = $validated['fields'] ?? [];

        return [is_array($fields) ? $fields : [], $enclosed];
    }

    /**
     * The same answer whether the submission was written or quietly dropped: the answer of every
     * form of the site (`FormResponse`, WIDGETS §17.4) — JSON `{ ok, heading, message, redirect,
     * html }` to the form's script, otherwise a redirect to the form's address or back to the page
     * with the thank-you, where the form that was sent shows it.
     *
     * The visitor's answers go into it where it says `{{field.<name>}}` (`ThankYou`).
     *
     * @param  array<string, string>  $answers
     */
    private function accepted(Form $form, Request $request, array $answers): JsonResponse|RedirectResponse
    {
        $redirect = $this->redirect($form);
        $heading = ThankYou::heading($this->text($form, 'thank-you.heading'), $answers);
        $message = ThankYou::text($this->text($form, 'thank-you.text'), $answers);

        // A form whose thank-you nobody filled in still has to say something: an answer with
        // neither is the engine's "only the event", and the visitor would be told nothing.
        if ($heading === null && $message === null) {
            $heading = (string) trans('webx-inbox::form.thank-you');
        }

        $answer = $redirect !== null ? FormResponse::redirect($redirect) : FormResponse::message($heading, $message);

        // A view the site published before W6.4 knows no `_webx_form`, and reads its thank-you
        // where it always did (an alias period).
        if (! $request->expectsJson() && $redirect === null && ! $request->filled(FormResponse::FORM) && $request->hasSession()) {
            // Its heading is text and printed escaped: the values go in without their tags.
            $text = $heading instanceof Htmlable ? FormResponse::plain($heading) : $heading;

            $request->session()->flash('webx-inbox', ['ok' => true, 'form' => $form->slug, 'heading' => $text, 'message' => $message, 'redirect' => null]);
        }

        return $answer->toResponse($request);
    }

    /**
     * One of the translated settings of §5.
     *
     * The key is read literally — `thank-you.heading` is a key with a dot in it, not a path —
     * for the same reason `module-settings` reads its own that way.
     */
    private function text(Form $form, string $key): ?string
    {
        $value = $form->option($key);

        if (is_array($value)) {
            $locale = app()->getLocale();

            $value = $value[$locale]
                ?? $value[(string) config('app.fallback_locale')]
                ?? reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Where the visitor goes instead of the thank-you, in the language the form was sent in — the
     * language the page was printed in (`SpeakTheLanguageOfThePage`).
     *
     * A record of the site is its version in that language, or in the main one when it has none:
     * a visitor of the Russian page is better off on the English thank-you than on a 404. A record
     * that is not on the site in either — a draft, the bin, deleted — is no place to send anybody,
     * and the visitor gets the thank-you where the form was. An address is the editor's, and a path
     * of this site gets the visitor's language the way every hand-written link does.
     */
    private function redirect(Form $form): ?string
    {
        $link = FormOptions::redirect($form->option('redirect'));

        if ($link === null) {
            return null;
        }

        if ($link->target !== LinkTarget::Entity) {
            return $this->urls->href($link, app()->getLocale());
        }

        foreach (array_unique([app()->getLocale(), $this->locales->defaultCode()]) as $locale) {
            $candidate = $this->urls->candidate($link, $locale);

            if ($candidate !== null && $candidate->available && $candidate->url !== null) {
                return $this->urls->hrefWith($link, $candidate, $locale);
            }
        }

        return null;
    }
}
