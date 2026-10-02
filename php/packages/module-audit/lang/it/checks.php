<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Modalità debug su un dominio in produzione',
            'found' => 'APP_DEBUG=true su un dominio che non è un ambiente di sviluppo.',
            'why' => 'Ogni pagina di errore mostra il codice, le query e l’ambiente, password comprese, a chiunque ne trovi una.',
            'fix' => 'Imposta APP_DEBUG=false in .env ed esegui php artisan config:cache.',
        ],
        'env' => [
            'title' => 'L’ambiente non è production',
            'found' => 'APP_ENV non è production su un dominio in produzione.',
            'why' => 'Fuori da production i pacchetti si comportano diversamente: cache, pagine di errore, posta e strumenti di debug.',
            'fix' => 'Imposta APP_ENV=production in .env ed esegui php artisan config:cache.',
        ],
        'app_url' => [
            'title' => 'APP_URL non corrisponde al sito',
            'found' => 'APP_URL differisce dallo schema e dall’host su cui risponde il sito.',
            'why' => 'Ogni indirizzo assoluto che il sito stampa — la sitemap, i link canonical, le e-mail, i link ai file — punta altrove.',
            'fix' => 'Imposta APP_URL sull’indirizzo usato dai visitatori, con https se il sito lo ha, ed esegui php artisan config:cache.',
        ],
        'queue' => [
            'title' => 'La coda viene eseguita all’interno della richiesta',
            'found' => 'Il driver della coda è sync.',
            'why' => 'Le e-mail e gli invii vengono gestiti mentre il visitatore aspetta, un server di posta lento rallenta i moduli e i job lunghi, come l’audit, non possono essere avviati dal pannello.',
            'fix' => 'Usa la coda database o redis e tieni in esecuzione un worker (php artisan queue:work sotto un supervisor).',
        ],
        'mail' => [
            'title' => 'La posta non va da nessuna parte',
            'found' => 'Il mailer scrive le e-mail nel log o in memoria.',
            'why' => 'Ogni modulo dice «inviato» e nessuno riceve mai un’e-mail.',
            'fix' => 'Configura un vero mailer (SMTP o un’API) in .env: MAIL_MAILER e le sue impostazioni.',
        ],
        'schedule' => [
            'title' => 'Lo scheduler non è in esecuzione',
            'found' => 'Lo scheduler non viene eseguito da più di un’ora.',
            'why' => 'I backup, la pulizia del registro e tutto il resto pianificato si fermano in silenzio.',
            'fix' => 'Aggiungi «* * * * * php artisan schedule:run» al crontab dell’utente del sito.',
        ],
        'storage_link' => [
            'title' => 'Nessun link public/storage',
            'found' => 'public/storage non esiste.',
            'why' => 'Ogni immagine e file caricato sul sito risponde 404.',
            'fix' => 'Esegui php artisan storage:link sul server.',
        ],
        'site_gate' => [
            'title' => 'Il sito è chiuso con una password',
            'found' => 'Il blocco del sito è attivo.',
            'why' => 'I motori di ricerca non vedono nulla dietro la password — giusto mentre il sito è in test, sbagliato dopo il lancio.',
            'fix' => 'Imposta WEBX_SITE_GATE=false quando il sito viene aperto.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'Rispondono due mirror',
            'found' => 'Sia www sia il nome senza rispondono 200.',
            'why' => 'Ogni pagina esiste due volte e i motori di ricerca dividono il suo peso tra le copie.',
            'fix' => 'Reindirizza il secondo nome a quello principale con un unico 301 nel web server.',
        ],
        'https' => [
            'title' => 'http non porta a https in un solo passaggio',
            'found' => 'http:// risponde da solo, porta altrove o raggiunge https attraverso una catena.',
            'why' => 'I visitatori finiscono su una copia non sicura e ogni passaggio in più costa tempo e peso dei link.',
            'fix' => 'Un solo 301 da http:// a https:// dell’host principale, nel web server.',
        ],
        'tls' => [
            'title' => 'Problema con il certificato',
            'found' => 'Il certificato scade presto, indica un altro host o non è attendibile.',
            'why' => 'I browser mostrano un avviso a pagina intera e la maggior parte dei visitatori se ne va.',
            'fix' => 'Rinnova il certificato (verifica che il rinnovo automatico funzioni) e servi la catena completa per questo host.',
        ],
        'hsts' => [
            'title' => 'Nessun HSTS',
            'found' => 'Nessun header Strict-Transport-Security.',
            'why' => 'La prima visita può ancora passare su http semplice ed essere intercettata.',
            'fix' => 'Aggiungi Strict-Transport-Security: max-age=31536000 nel web server quando https è stabile.',
        ],
        'index_files' => [
            'title' => 'I file index rispondono',
            'found' => '/index.php o un altro file index risponde 200.',
            'why' => 'La pagina è disponibile a un secondo indirizzo — un duplicato per i motori di ricerca.',
            'fix' => 'Reindirizza i file index all’indirizzo senza di essi con un 301.',
        ],
        'slashes' => [
            'title' => 'Le doppie barre non vengono compattate',
            'found' => 'Un indirizzo con // risponde 200.',
            'why' => 'Qualsiasi link digitato male crea un’altra copia della pagina.',
            'fix' => 'Reindirizza gli indirizzi con barre ripetute a quello compattato con un 301.',
        ],
        'trailing_slash' => [
            'title' => 'Con e senza barra finale',
            'found' => 'La stessa pagina risponde con e senza barra finale.',
            'why' => 'Due indirizzi per una pagina dividono il suo peso.',
            'fix' => 'Scegli una forma e reindirizza l’altra con un 301.',
        ],
        'case' => [
            'title' => 'Le maiuscole non vengono normalizzate',
            'found' => 'Un indirizzo con lettere maiuscole risponde 200.',
            'why' => 'Un link digitato con maiuscole diverse crea un duplicato.',
            'fix' => 'Reindirizza gli indirizzi con maiuscole a quello in minuscolo con un 301.',
        ],
        'soft_404' => [
            'title' => 'Le pagine mancanti non rispondono 404',
            'found' => 'Un indirizzo che non può esistere risponde 200 o reindirizza.',
            'why' => 'I motori di ricerca indicizzano i refusi e le pagine eliminate come pagine vere.',
            'fix' => 'Rispondi 404 per gli indirizzi sconosciuti; non reindirizzarli alla home page.',
        ],
        '404_page' => [
            'title' => 'La pagina 404 non porta da nessuna parte',
            'found' => 'La pagina 404 non ha un link alla home page.',
            'why' => 'Un visitatore che ha seguito un link rotto non sa dove andare.',
            'fix' => 'Aggiungi al template 404 un link alla home page, alla ricerca o alle sezioni principali.',
        ],
        'compression' => [
            'title' => 'HTML senza compressione',
            'found' => 'Le pagine vengono inviate senza gzip o brotli.',
            'why' => 'Le pagine pesano molte volte di più e si aprono più lentamente, soprattutto da mobile.',
            'fix' => 'Attiva gzip o brotli per text/html nel web server.',
        ],
        'security_headers' => [
            'title' => 'Mancano gli header di sicurezza',
            'found' => 'Mancano alcuni tra X-Content-Type-Options, Referrer-Policy e la protezione dall’inserimento in frame.',
            'why' => 'Chiudono attacchi facili: MIME sniffing, fuga di indirizzi, clickjacking.',
            'fix' => 'Aggiungi gli header nel web server: nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'Il server rivela le sue versioni',
            'found' => 'X-Powered-By, oppure Server con un numero di versione.',
            'why' => 'Una mappa pronta per chi cerca una falla nota in quella versione.',
            'fix' => 'Disattiva expose_php e server_tokens (o i loro equivalenti).',
        ],
        'static_cache' => [
            'title' => 'I file statici non sono in cache',
            'found' => 'CSS, JS o immagini senza Cache-Control o con cache inferiore a una settimana.',
            'why' => 'Ogni pagina li scarica di nuovo.',
            'fix' => 'Assegna ai file statici versionati un Cache-Control lungo (un anno, immutable) nel web server.',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'Link a un ambiente di sviluppo nei contenuti',
            'found' => 'Un indirizzo di un ambiente di sviluppo in un record — pubblicato, in bozza o in un campo che il template non stampa.',
            'why' => 'I contenuti inseriti su un ambiente di sviluppo vanno online con link e immagini che puntano di nuovo a quell’ambiente; i visitatori ricevono errori e l’ambiente viene indicizzato.',
            'fix' => 'Apri il record e sostituisci l’indirizzo dell’ambiente con quello del sito stesso o con un link relativo. Elenca gli ambienti nelle impostazioni dell’audit così da trovarli tutti.',
        ],
    ],
];
