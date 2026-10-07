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
        'notification' => [
            'title' => 'Notifiche di invii non partite',
            'found' => 'Le lettere sugli invii non sono riuscite o attendono a lungo in coda.',
            'why' => 'Le persone indicate dal modulo non sanno di richieste che il pannello ha già, e il sito non lo dice.',
            'fix' => 'Non riuscite: correggi le impostazioni della posta, riavvia il worker della coda (php artisan queue:restart) perché le rilegga e invia di nuovo la notifica dall’invio. In attesa: avvia un worker della coda.',
        ],
        'captcha_keys' => [
            'title' => 'Moduli che chiedono un captcha senza chiavi sul sito',
            'found' => 'Un modulo attivo chiede reCAPTCHA o Turnstile, e nel .env del sito manca la chiave del sito, il segreto o entrambi.',
            'why' => 'Senza chiave del sito il widget non viene mostrato; senza segreto nessuna risposta può essere verificata. In entrambi i casi il modulo rifiuta ogni invio e i visitatori non possono contattarti.',
            'fix' => 'Aggiungi WEBX_INBOX_RECAPTCHA_KEY e WEBX_INBOX_RECAPTCHA_SECRET (o la coppia TURNSTILE) al .env del sito, con WEBX_INBOX_RECAPTCHA_TYPE secondo il tipo di chiave, e svuota la cache di configurazione (php artisan config:clear). Oppure disattiva il captcha nella scheda Antispam del modulo.',
        ],
        'captcha_unused' => [
            'title' => 'Moduli senza captcha su un sito che ha le chiavi',
            'found' => 'Un modulo attivo non chiede il captcha, anche se il sito ha le chiavi per reCAPTCHA o Turnstile.',
            'why' => 'Il campo nascosto, il timestamp e il limite per indirizzo fermano la maggior parte dei robot, quindi è solo una nota: il captcha è pronto per il giorno in cui il modulo riceverà spam.',
            'fix' => 'Se il modulo riceve spam, attiva il captcha nella sua scheda Antispam. Altrimenti ignora questo problema.',
        ],
        'spam_without_captcha' => [
            'title' => 'Moduli senza captcha che ricevono spam',
            'found' => 'Un modulo attivo senza captcha ha ricevuto negli ultimi giorni invii segnati come spam, oppure l’antispam ha rifiutato degli invii.',
            'why' => 'I robot hanno trovato il modulo. Ciò che passa finisce nella posta e nelle notifiche, e gli strati gratuiti sono proprio ciò che cercano di aggirare.',
            'fix' => 'Attiva un captcha nella scheda Antispam del modulo — il sito ha bisogno delle sue chiavi nel .env — e lascia attivo il campo nascosto. I rifiuti si contano per giorno nella cache, quindi dopo averla svuotata ripartono da zero.',
        ],
    ],
];
