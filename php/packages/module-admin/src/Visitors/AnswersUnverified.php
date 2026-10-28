<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a visitors module shows a visitor whose e-mail is not confirmed yet, on a page behind
 * `webx.visitor:verified`: «Confirm your e-mail» and a link to send the letter again.
 *
 * Kept apart from {@see Visitors}: only a module that sends the letter has such a page, and
 * without it the middleware answers a bare 403.
 */
interface AnswersUnverified
{
    public function unverifiedResponse(Request $request): Response;
}
