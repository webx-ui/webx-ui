<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Inbox\Antispam\Guard;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Fields\Steps;
use WebxUi\Inbox\Submissions\Rules;
use WebxUi\Inbox\Support\Forms;

/**
 * `POST {prefix}/{slug}/check` — the door that checks one step and takes nothing (FORM_STEPS §3.4).
 *
 * «Next» of a form of steps asks it with the form as it stands and `webx_step` — the step being
 * left: the rules of the fields of the steps up to it, with the conditions read from what was sent,
 * as the intake will read them. 204 — the step is right; 422 — Laravel's errors, under the fields.
 * Without it a rule only the server knows (an address whose domain has no dot) would surface on the
 * last step, three screens away from its field.
 *
 * Nothing is written, nobody is told, no file is sent (the browser measured them). The intake's
 * route limit (`attempts`) is the same; the limit of accepted submissions is not counted, nor the
 * honeypot, the timestamp or the captcha: they guard the taking, and nothing is taken here.
 */
final class StepCheckController
{
    public function __construct(
        private readonly Forms $forms,
        private readonly Guard $guard,
        private readonly Rules $rules,
        private readonly ValidationFactory $validator,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $form = $this->forms->enabled($slug);

        if ($form === null) {
            throw new NotFoundHttpException;
        }

        if (! $this->guard->fromHere($request)) {
            throw ValidationException::withMessages(['form' => [(string) trans('webx-inbox::errors.refused')]]);
        }

        $step = $request->input('webx_step');
        $keys = is_string($step) ? Steps::upTo($form->liveFields, $step) : null;

        if ($keys === null) {
            // A step this form does not have — a page printed before the form was changed.
            throw ValidationException::withMessages(['webx_step' => [(string) trans('webx-inbox::errors.step-unknown')]]);
        }

        // The files stayed in the browser, which has measured them: a required one is required on
        // the submit, not on every «Next».
        $files = [];

        foreach ($form->questions() as $field) {
            if ($field->type === FieldType::File) {
                $files[] = $field->key();
            }
        }

        $keys = array_values(array_diff($keys, $files));

        $this->validator->make(
            $request->all(),
            $this->rules->for($form, Rules::sent($request), $keys),
            [],
            $this->rules->attributes($form),
        )->validate();

        return response()->noContent();
    }
}
