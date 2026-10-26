<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Contracts;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Submissions\Enclosed;
use WebxUi\Inbox\Submissions\Enclosures;

/**
 * Something a module puts into a submission beside the answers (§2.20): a configuration and its
 * price, a basket — data the module counted itself, which no field of the form could carry
 * without trusting the browser with it.
 *
 * Registered with {@see Enclosures}, asked for by name: the form sends
 * a hidden `webx_enclosure[]=<name>`, and the enclosure reads what else it needs from the request.
 * A name the request does not send is not asked, so a form without the module's widget on its
 * page is a form like any other.
 */
interface Enclosure
{
    /** What the request names it by — also the name of its row in the submission. */
    public function name(): string;

    /**
     * Whether this form may carry it. False is a refusal under `<name>`: a configuration sent to a
     * form nobody named for it is a configuration somebody fastened to a stranger's form.
     */
    public function accepts(Form $form, Request $request): bool;

    /**
     * The enclosure, counted from the request. Throws a {@see ValidationException} with keys
     * `<name>.*` for what it cannot take — it is answered with the errors of the fields.
     */
    public function make(Form $form, Request $request): Enclosed;
}
