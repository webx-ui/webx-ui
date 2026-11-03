<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

/** How the signature of an incoming event went (spec §20.2). */
enum SignatureCheck: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    /** The type does not trust its notifications (decision 33): read as a reason to ask, never as a status. */
    case Untrusted = 'untrusted';
    /** Not checked: the method was not found, or the event is not one that carries a signature. */
    case None = 'none';
}
