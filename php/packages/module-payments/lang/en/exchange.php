<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Gateway exchange',
    'directions' => [
        'out' => 'To the gateway',
        'in' => 'From the gateway',
        'browser' => 'Through the buyer\'s browser',
        'internal' => 'Decided by the site',
    ],
    'kinds' => [
        'start' => 'Payment opened',
        'status' => 'Status request',
        'cancel' => 'Cancellation',
        'complete' => 'Approval completed',
        'capture' => 'Capture request',
        'void' => 'Release request',
        'connection' => 'Connection check',
        'key' => 'Signing key',
        'other' => 'Other request',
        'notify' => 'Notification',
        'handoff' => 'Sent to the gateway',
        'return' => 'Back from the gateway',
        'expire' => 'Expired',
        'manual' => 'Marked by hand',
        'captured' => 'Captured',
        'released' => 'Hold released',
        'capture_refused' => 'Capture refused',
        'release_refused' => 'Release refused',
    ],
    'signature' => [
        'valid' => 'Signature valid',
        'invalid' => 'Signature invalid',
        'untrusted' => 'Not signed — status asked from the gateway',
        'none' => 'Not checked',
    ],
    'outcomes' => [
        'applied' => 'Applied',
        'same' => 'Nothing new',
        'ignored' => 'Ignored',
        'duplicate' => 'Repeat',
        'rejected' => 'Rejected',
        'unmatched' => 'No such payment',
        'error' => 'Error',
    ],
    'truncated' => 'Cut at the limit',
    'download' => 'Download',
    'size' => 'Size',
    'masked' => 'Secrets hidden',
];
