<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Chiavi',
    'issue' => 'Emetti una chiave',
    'issue-short' => 'Emetti',
    'lead' => 'Una chiave permette a un programma — un guardiano della coda, un\'importazione notturna — di lavorare in questo pannello senza browser. Agisce come l\'amministratore che scegli e non può mai fare più di lui. Una chiave è un programma, non una persona: ciò che un modulo riserva a una persona, come una fase verificata a mano, non lo può fare.',
    'name' => 'Nome',
    'name-hint' => 'Che cos\'è il programma, perché l\'elenco lo dica: «guardiano della coda», «importazione notturna».',
    'who' => 'Agisce come',
    'who-hint' => 'Tutto ciò che la chiave modifica è fatto a nome di questo amministratore, entro i suoi permessi.',
    'allowed' => 'Cosa è consentito',
    'everything' => 'Tutto ciò che può l\'amministratore',
    'chosen' => 'Solo gli scope scelti qui sotto',
    'scopes' => 'Scope',
    'scopes-hint' => 'Un modulo installato in seguito resta fuori portata finché una nuova chiave non lo nomina.',
    'scopes-required' => 'Scegli almeno uno scope.',
    'lifetime' => 'Durata',
    'lifetime-hint' => 'Una chiave non si può prolungare: emettine una nuova prima che questa scada, poi revoca la vecchia.',
    'days-30' => '30 giorni',
    'days-90' => '90 giorni',
    'days-365' => '1 anno',
    'days-1095' => '3 anni',
    'days-3650' => '10 anni',
    'issued-title' => 'Copia la chiave adesso',
    'issued-text' => 'È l\'unica volta che la chiave viene mostrata. Nessuno potrà rivederla, né qui né sul server. Se va persa, revocala ed emettine un\'altra.',
    'secret' => 'Chiave',
    'copy' => 'Copia',
    'copied' => 'Copiata',
    'copy-failed' => 'Impossibile accedere agli appunti. Seleziona la chiave e copiala a mano.',
    'address' => 'Indirizzo del server',
    'header-hint' => 'Il programma invia la chiave in un\'intestazione: Authorization: Bearer <chiave>.',
    'done' => 'Fatto',
    'cancel' => 'Annulla',
    'expires' => 'Scade',
    'until' => 'fino al',
    'last-used' => 'Ultimo uso',
    'never-used' => 'Mai',
    'issued-by' => 'Emessa da :name',
    'issued-console' => 'Emessa da terminale',
    'full' => 'Tutto ciò che può :name',
    'deleted' => 'Amministratore n. :id',
    'revoke' => 'Revoca',
    'revoked' => 'Revocata',
    'expired' => 'Scaduta',
    'confirm-title' => 'Revocare «:name»?',
    'confirm-text' => 'Il programma che la usa viene rifiutato alla chiamata successiva. Una chiave revocata non si può riattivare.',
    'confirm' => 'Revoca',
    'revoked-done' => '«:name» è revocata',
    'empty' => 'Nessuna chiave emessa',
];
