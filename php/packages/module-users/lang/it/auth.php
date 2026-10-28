<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Accedi',
        'remember' => 'Ricordami',
        'submit' => 'Accedi',
        'no_account' => 'Non hai ancora un account?',
        'register' => 'Registrati',
    ],
    'register' => [
        'title' => 'Crea un account',
        'submit' => 'Registrati',
        'password_hint' => 'Almeno :min caratteri.',
        'have_account' => 'Hai già un account?',
        'sign_in' => 'Accedi',
    ],
    'fields' => [
        'email' => 'E-mail',
        'password' => 'Password',
        'first_name' => 'Nome',
        'last_name' => 'Cognome',
        'phone' => 'Telefono',
    ],
    'failed' => 'E-mail o password errati.',
    'blocked' => 'Questo account è bloccato. Contatta il negozio.',
    'taken' => 'Questo indirizzo è già registrato: accedi o recupera la password.',
    'value_taken' => 'Questo valore è già in uso.',
    'throttled' => 'Troppi tentativi. Attendi :seconds s.',
    'forgot' => [
        'link' => 'Password dimenticata?',
        'title' => 'Recupera la password',
        'intro' => 'Inserisci l’e-mail del tuo account: ti invieremo un link per una nuova password.',
        'submit' => 'Invia il link',
        'sent' => 'Se esiste un account con questo indirizzo, gli abbiamo inviato un link.',
        'back' => 'Torna all’accesso',
    ],
    'reset' => [
        'title' => 'Nuova password',
        'password' => 'Nuova password',
        'confirmation' => 'Ripeti la password',
        'submit' => 'Salva la password',
        'expired' => 'Il link è scaduto. Richiedine uno nuovo qui sotto.',
    ],
    'verify' => [
        'banner' => 'Conferma la tua e-mail: abbiamo inviato un messaggio a',
        'resend' => 'Invia di nuovo',
        'sent_short' => 'Inviato',
        'close' => 'Chiudi',
        'sent' => 'Abbiamo inviato di nuovo il messaggio a :email.',
        'already' => 'La tua e-mail è già confermata.',
        'throttled' => 'Il messaggio è già stato inviato. Riprova tra :minutes min.',
        'sign_in' => 'Accedi',
        'continue' => 'Continua',
        'confirmed' => [
            'title' => 'E-mail confermata',
            'text' => 'Grazie: il tuo indirizzo è confermato.',
        ],
        'expired' => [
            'title' => 'Il link è scaduto',
            'text' => 'Questo link non funziona più. Accedi per ricevere un nuovo messaggio.',
        ],
        'unverified' => [
            'title' => 'Conferma la tua e-mail',
            'text' => 'Questa pagina si apre dopo la conferma dell’e-mail. Abbiamo inviato un messaggio a :email.',
        ],
    ],
    'email' => [
        'new' => 'Nuova e-mail',
        'password' => 'Password attuale',
        'wrong_password' => 'La password è errata.',
        'same' => 'Accedi già con questo indirizzo.',
        'taken' => 'Questo indirizzo appartiene a un altro account.',
        'sent' => 'Abbiamo inviato un link a :email. L’indirizzo cambierà quando lo aprirai.',
        'changed' => [
            'title' => 'Indirizzo cambiato',
            'text' => 'Ora accedi con :email.',
        ],
    ],
    'social' => [
        'or' => 'oppure',
        'continue' => 'Continua con :provider',
        'failed' => 'Accesso non riuscito. Riprova.',
        'expired' => 'Il tempo per completare è scaduto. Accedi di nuovo con il social network.',
        'closed' => 'Nessun account usa questo profilo :provider e la registrazione è chiusa.',
        'sign_in_to_link' => 'Esiste già un account con questo indirizzo. Accedi con la password e l’accesso con :provider verrà collegato.',
        'providers' => [
            'google' => 'Google',
            'facebook' => 'Facebook',
        ],
        'fields' => [
            'client_id' => 'ID client',
            'client_secret' => 'Secret client',
            'app_id' => 'ID app',
            'app_secret' => 'Secret app',
        ],
        'complete' => [
            'title' => 'Quasi fatto',
            'text' => ':provider non ci ha dato tutto ciò che serve all’account. Completa il resto.',
            'email_hint' => 'Ti invieremo un’e-mail per confermarlo.',
            'submit' => 'Crea l’account',
        ],
    ],
];
