<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Inbox\Antispam\Guard;
use WebxUi\Inbox\Antispam\Verdict;
use WebxUi\Inbox\Mail\Notifier;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Submissions\Intake;
use WebxUi\Inbox\Submissions\Rules;
use WebxUi\Inbox\Support\Forms;

/**
 * The public door (§6).
 *
 * It answers in two shapes, because the form on the site works with and without JavaScript:
 * JSON when the request asks for it, and otherwise a redirect back to the page with either the
 * errors or the thank-you in the session. Nothing else about the two paths differs.
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

        $verdict = $this->guard->inspect($form, $request);

        if ($verdict === Verdict::Trap) {
            // A robot is thanked and nothing is written. Telling it which trick was seen is
            // the one thing that makes the next attempt harder to catch (§7).
            $this->log->info('webx-inbox: a submission to {form} was trapped.', [
                'form' => $form->slug,
                'ip' => $request->ip(),
            ]);

            return $this->accepted($form, $request);
        }

        if ($verdict === Verdict::Reject) {
            $this->log->info('webx-inbox: a submission to {form} was refused by the antispam.', [
                'form' => $form->slug,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'form' => [(string) trans('webx-inbox::errors.refused')],
            ]);
        }

        $values = $this->validate($form, $request);

        $submission = $this->intake->receive($form, $values, $request);

        // Last, and on its own: everything above it is the submission, and a mail server that
        // is down must not undo any of it (§2.10).
        $this->notifier->send($submission);

        return $this->accepted($form, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(Form $form, Request $request): array
    {
        $validated = $this->validator->make(
            $request->all(),
            $this->rules->for($form),
            [],
            $this->rules->attributes($form),
        )->validate();

        $fields = $validated['fields'] ?? [];

        return is_array($fields) ? $fields : [];
    }

    /**
     * The same answer whether the submission was written or quietly dropped.
     */
    private function accepted(Form $form, Request $request): JsonResponse|RedirectResponse
    {
        $payload = [
            'ok' => true,
            // Which form this is about. A page may carry three of them, and without the name
            // all three would show the thank-you belonging to whichever one was sent (§10).
            'form' => $form->slug,
            'heading' => $this->text($form, 'thank-you.heading'),
            'message' => $this->text($form, 'thank-you.text'),
            'redirect' => $this->redirect($form),
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($payload);
        }

        $redirect = $payload['redirect'];

        return $redirect !== null
            ? redirect()->away($redirect)
            : redirect()->back()->with('webx-inbox', $payload);
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

    private function redirect(Form $form): ?string
    {
        $redirect = $form->option('redirect');

        return is_string($redirect) && trim($redirect) !== '' ? trim($redirect) : null;
    }
}
