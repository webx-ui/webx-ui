<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Keys',
    'issue' => 'Issue a key',
    'issue-short' => 'Issue',
    'lead' => 'A key lets a program — a queue watcher, a nightly import — work in this panel without a browser. It acts as the administrator you choose and can never do more than they may. A key is a program, not a person: what a module keeps for a person, such as a stage checked by hand, it cannot do.',
    'name' => 'Name',
    'name-hint' => 'What the program is, so that the list says it: "queue watcher", "nightly import".',
    'who' => 'Acts as',
    'who-hint' => 'Everything the key changes is done in this administrator\'s name, within their permissions.',
    'allowed' => 'What is allowed',
    'everything' => 'Everything the administrator may do',
    'chosen' => 'Only the scopes chosen below',
    'scopes' => 'Scopes',
    'scopes-hint' => 'A module installed later is out of reach until a new key names it.',
    'scopes-required' => 'Choose at least one scope.',
    'lifetime' => 'Lives for',
    'lifetime-hint' => 'A key cannot be extended: issue a new one before this one runs out, then revoke the old one.',
    'days-30' => '30 days',
    'days-90' => '90 days',
    'days-365' => '1 year',
    'days-1095' => '3 years',
    'days-3650' => '10 years',
    'issued-title' => 'Copy the key now',
    'issued-text' => 'This is the only time the key is shown. Nobody can see it again — not here, not on the server. If it is lost, revoke it and issue a new one.',
    'secret' => 'Key',
    'copy' => 'Copy',
    'copied' => 'Copied',
    'copy-failed' => 'Could not reach the clipboard. Select the key and copy it by hand.',
    'address' => 'Server address',
    'header-hint' => 'The program sends the key in a header: Authorization: Bearer <key>.',
    'done' => 'Done',
    'cancel' => 'Cancel',
    'expires' => 'Expires',
    'until' => 'until',
    'last-used' => 'Last used',
    'never-used' => 'Never',
    'issued-by' => 'Issued by :name',
    'issued-console' => 'Issued in a terminal',
    'full' => 'Everything :name can do',
    'deleted' => 'Administrator #:id',
    'revoke' => 'Revoke',
    'revoked' => 'Revoked',
    'expired' => 'Expired',
    'confirm-title' => 'Revoke :name?',
    'confirm-text' => 'The program using it is refused from its next call. A revoked key cannot be switched back on.',
    'confirm' => 'Revoke',
    'revoked-done' => ':name is revoked',
    'empty' => 'No keys have been issued',
    'lead-mine' => 'A key lets a program — a queue watcher, a nightly import — work in this panel as you, without a browser. It can never do more than you may, and only within the scopes you choose.',
    'empty-mine' => 'You have no keys yet',

];
