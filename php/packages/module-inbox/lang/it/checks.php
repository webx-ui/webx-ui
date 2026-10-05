<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Moduli che non avvisano nessuno',
            'found' => 'Un modulo attivo non nomina alcun destinatario raggiungibile da un’e-mail: nessuno, solo amministratori eliminati o disattivati nel frattempo, o indirizzi che non sono indirizzi.',
            'why' => 'Ogni invio viene salvato e il visitatore ringraziato, ma nessuno lo sa finché qualcuno non apre la posta in arrivo: una richiesta può aspettare giorni.',
            'fix' => 'Apri il modulo, vai alla scheda Notifiche e aggiungi un amministratore o un indirizzo. Se il modulo si legge solo nel pannello, ignora questa segnalazione.',
        ],
    ],
];
