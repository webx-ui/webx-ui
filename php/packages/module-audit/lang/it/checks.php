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
        'dev_page' => [
            'title' => 'Link a un ambiente di sviluppo nella pagina',
            'found' => 'Un link o una risorsa della pagina — un’immagine, uno script, uno stile, og:image, il canonical — porta a un ambiente di sviluppo.',
            'why' => 'I visitatori seguono link verso un sito che non è pensato per loro, le immagini si rompono quando l’ambiente viene spento e i motori di ricerca trovano l’ambiente attraverso il sito.',
            'fix' => 'Trova l’indirizzo nel contenuto o nel template della pagina e sostituisci l’ambiente con l’host del sito stesso o con un link relativo.',
        ],
        'similar' => [
            'title' => 'Un host che somiglia a questo sito',
            'found' => 'Un link a un host con la stessa prima parola del sito in un’altra zona, come shop.local accanto a shop.com.',
            'why' => 'Molto probabilmente è un ambiente o una vecchia copia del sito che l’audit non conosce.',
            'fix' => 'Se è un ambiente o un vecchio dominio, aggiungilo a «Altri indirizzi di questo sito» nelle impostazioni dell’audit e correggi i link; se è il sito di qualcun altro, non serve fare nulla.',
        ],
        'wrong_mirror' => [
            'title' => 'Link tramite un altro mirror',
            'found' => 'Un link al sito tramite il suo altro mirror (www o il nome senza www) oppure tramite http su un sito https.',
            'why' => 'Ogni clic passa per un reindirizzamento: più lento per i visitatori, e i motori di ricerca vedono link a un indirizzo che non è quello della pagina.',
            'fix' => 'Collega al mirror principale tramite https, oppure usa link relativi.',
        ],
        'absolute_own' => [
            'title' => 'Link assoluti al sito stesso',
            'found' => 'Un link o un’immagine nel contenuto è scritto con l’host del sito invece di un percorso.',
            'why' => 'Oggi funziona e si rompe al prossimo cambio di dominio o di protocollo; copiato su un ambiente di sviluppo, riporta al sito online.',
            'fix' => 'Scrivi i link alle pagine del sito come percorsi: /about invece di https://shop.com/about.',
        ],
        'new_domain' => [
            'title' => 'Un nuovo dominio esterno',
            'found' => 'Il sito rimanda a un dominio a cui non rimandava nell’esecuzione completa precedente.',
            'why' => 'Un nuovo dominio è di solito un nuovo link aggiunto da qualcuno — e a volte un errore di battitura, o link di spam lasciati da chi è entrato nel sito.',
            'fix' => 'Apri le pagine elencate e verifica che il link debba esserci.',
        ],
        'external_many' => [
            'title' => 'Molti link esterni in una pagina',
            'found' => 'La pagina ha più link esterni della soglia.',
            'why' => 'Una pagina fatta soprattutto di link ad altri siti sembra una link farm ai motori di ricerca ed è spesso un segno di spam.',
            'fix' => 'Rimuovi i link che non aiutano il visitatore, oppure dividi la pagina.',
        ],
        'blank_opener' => [
            'title' => 'Nuova scheda senza noopener',
            'found' => 'Un link a un altro sito si apre in una nuova scheda senza rel="noopener".',
            'why' => 'Nei browser meno recenti la pagina aperta può reindirizzare la scheda del sito a una pagina a sua scelta.',
            'fix' => 'Aggiungi rel="noopener" (o noreferrer) ai link con target="_blank".',
        ],
    ],
    'indexing' => [
        'home_noindex' => [
            'title' => 'La home page è chiusa ai motori di ricerca',
            'found' => 'La home page ha noindex nel meta tag robots o nell’header X-Robots-Tag.',
            'why' => 'La pagina più importante del sito sparisce dalla ricerca, e spesso segue l’intero sito.',
            'fix' => 'Rimuovi noindex dalla home page: controlla le impostazioni SEO della pagina, il layout e gli header del web server.',
        ],
        'noindex' => [
            'title' => 'Pagine chiuse con noindex',
            'found' => 'La pagina ha noindex nel meta tag robots o nell’header X-Robots-Tag.',
            'why' => 'I motori di ricerca scartano la pagina. Giusto per i risultati di ricerca e le pagine di servizio, sbagliato per contenuti chiusi per errore.',
            'fix' => 'Scorri l’elenco; apri le pagine che devono essere trovate nelle loro impostazioni SEO e rimuovi noindex.',
        ],
    ],
    'title' => [
        'missing' => [
            'title' => 'Nessun title',
            'found' => 'La pagina non ha un <title>, oppure è vuoto.',
            'why' => 'Il title è la riga che i motori di ricerca mostrano come link alla pagina; senza, ne inventano uno.',
            'fix' => 'Dai alla pagina un title nelle sue impostazioni SEO, oppure controlla che il layout ne stampi uno.',
        ],
        'duplicate' => [
            'title' => 'Title duplicati',
            'found' => 'Più pagine indicizzabili hanno lo stesso title.',
            'why' => 'I motori di ricerca non distinguono le pagine e ne mostrano una, non necessariamente quella giusta.',
            'fix' => 'Dai a ogni pagina un title tutto suo che dica cosa contiene.',
        ],
        'length' => [
            'title' => 'Title troppo corto o troppo lungo',
            'found' => 'Il title è più corto o più lungo delle soglie, in caratteri.',
            'why' => 'Un title lungo viene tagliato nei risultati di ricerca (il limite è circa 600 pixel, più o meno 60 caratteri); uno corto dice troppo poco.',
            'fix' => 'Riscrivi il title perché rientri nell’intervallo delle soglie dell’audit.',
        ],
        'multiple' => [
            'title' => 'Più di un title',
            'found' => 'La pagina ha più di un tag <title>.',
            'why' => 'I motori di ricerca ne prendono uno, non necessariamente quello scritto per la pagina.',
            'fix' => 'Trova quale template o blocco stampa il secondo title e rimuovilo.',
        ],
    ],
    'description' => [
        'missing' => [
            'title' => 'Nessuna meta description',
            'found' => 'La pagina non ha una meta description, oppure è vuota.',
            'why' => 'I motori di ricerca compongono lo snippet sotto il link con il testo che trovano.',
            'fix' => 'Scrivi una description nelle impostazioni SEO della pagina: cosa offre la pagina, in una o due frasi.',
        ],
        'duplicate' => [
            'title' => 'Description duplicate',
            'found' => 'Più pagine indicizzabili hanno la stessa meta description.',
            'why' => 'Lo stesso snippet sotto link diversi non dice nulla a chi cerca, e i motori di ricerca lo sostituiscono con uno proprio.',
            'fix' => 'Scrivi una description propria per ogni pagina.',
        ],
        'length' => [
            'title' => 'Description troppo corta o troppo lunga',
            'found' => 'La description è più corta o più lunga delle soglie, in caratteri.',
            'why' => 'Una description lunga viene tagliata nei risultati di ricerca (circa 920 pixel, più o meno 160 caratteri); una corta viene spesso sostituita.',
            'fix' => 'Riscrivi la description perché rientri nell’intervallo delle soglie dell’audit.',
        ],
    ],
    'h1' => [
        'missing' => [
            'title' => 'Nessun H1',
            'found' => 'La pagina non ha un titolo H1.',
            'why' => 'L’H1 dice ai visitatori e ai motori di ricerca di cosa parla la pagina; gli screen reader lo usano per trovare l’inizio del contenuto.',
            'fix' => 'Dai alla pagina un H1 — di solito il suo nome — nel template o nel contenuto.',
        ],
        'multiple' => [
            'title' => 'Più di un H1',
            'found' => 'La pagina ha più di un titolo H1.',
            'why' => 'Non è un errore in sé, ma di solito è il segno che un blocco o il logo usa H1 dove era previsto un livello inferiore.',
            'fix' => 'Tieni un solo H1 per il nome della pagina e rendi gli altri H2 o inferiori.',
        ],
        'equals_title' => [
            'title' => 'H1 uguale al title',
            'found' => 'L’H1 ripete il title parola per parola.',
            'why' => 'Due posti per descrivere la pagina dicono la stessa cosa; uno dei due potrebbe aggiungere una parola che la gente cerca.',
            'fix' => 'Tieni l’H1 breve e leggibile, e lascia che il title porti le parole chiave e il nome del sito.',
        ],
    ],
    'headings' => [
        'skipped' => [
            'title' => 'Livello di titolo saltato',
            'found' => 'Un livello di titolo viene saltato scendendo, per esempio H2 seguito da H4.',
            'why' => 'Gli screen reader navigano per titoli, e un salto sembra un contenuto mancante.',
            'fix' => 'Usa i livelli in ordine; scegli l’aspetto con gli stili, non con il livello.',
        ],
    ],
    'canonical' => [
        'missing' => [
            'title' => 'Nessun canonical',
            'found' => 'Una pagina indicizzabile non ha un link canonical, né nel tag né nell’header.',
            'why' => 'Senza, ogni copia della pagina con parametri di tracciamento o di ordinamento può competere con la pagina stessa.',
            'fix' => 'Fai stampare dal layout <link rel="canonical"> con l’indirizzo della pagina stessa.',
        ],
        'relative' => [
            'title' => 'Canonical relativo',
            'found' => 'Il canonical è scritto come un percorso, non come un indirizzo completo.',
            'why' => 'I motori di ricerca lo leggono rispetto all’indirizzo da cui sono arrivati, compreso un altro mirror o protocollo.',
            'fix' => 'Stampa il canonical come indirizzo completo con lo schema e l’host principale.',
        ],
        'multiple' => [
            'title' => 'Canonical in conflitto',
            'found' => 'La pagina ha più di un canonical, oppure il tag e l’header Link non coincidono.',
            'why' => 'Con canonical in conflitto, i motori di ricerca li ignorano tutti.',
            'fix' => 'Lascia un solo canonical: trova il template, il blocco o la regola del server che aggiunge il secondo e rimuovilo.',
        ],
        'broken' => [
            'title' => 'Canonical verso una pagina rotta o chiusa',
            'found' => 'Il canonical porta a un reindirizzamento, a un errore o a una pagina con noindex.',
            'why' => 'La pagina indica un originale che non può essere indicizzato, e i motori di ricerca possono scartare entrambe.',
            'fix' => 'Fai puntare il canonical all’indirizzo funzionante della pagina stessa, oppure all’originale online.',
        ],
        'other' => [
            'title' => 'Canonical verso un’altra pagina',
            'found' => 'Il canonical punta a un indirizzo diverso da quello della pagina stessa.',
            'why' => 'La pagina chiede di non essere indicizzata a favore di un’altra — giusto per filtri e copie, sbagliato per una pagina che deve essere trovata.',
            'fix' => 'Scorri l’elenco; per le pagine che devono essere trovate, fai del canonical il loro stesso indirizzo.',
        ],
    ],
    'html' => [
        'lang' => [
            'title' => 'Nessuna lingua della pagina',
            'found' => 'Il tag <html> non ha l’attributo lang.',
            'why' => 'Gli screen reader scelgono la voce in base a esso, i browser propongono la traduzione in base a esso, e i motori di ricerca lo usano come indizio.',
            'fix' => 'Stampa nel layout <html lang="…"> con la lingua della pagina.',
        ],
        'viewport' => [
            'title' => 'Nessun meta viewport',
            'found' => 'La pagina non ha un <meta name="viewport">.',
            'why' => 'I telefoni disegnano la pagina alla larghezza del desktop, rimpicciolita; i motori di ricerca considerano una pagina così non adatta ai dispositivi mobili.',
            'fix' => 'Aggiungi <meta name="viewport" content="width=device-width, initial-scale=1"> al layout.',
        ],
        'favicon' => [
            'title' => 'Nessuna icona',
            'found' => 'La pagina non collega alcuna icona.',
            'why' => 'Le schede del browser, i segnalibri e i risultati di ricerca sui telefoni mostrano un quadrato vuoto al posto del marchio del sito.',
            'fix' => 'Aggiungi <link rel="icon"> al layout.',
        ],
    ],
    'og' => [
        'missing' => [
            'title' => 'Tag Open Graph mancanti',
            'found' => 'La pagina non ha og:title, og:image o og:url.',
            'why' => 'Un link condiviso in un messenger o in un social network appare come un semplice indirizzo, senza immagine né titolo.',
            'fix' => 'Compila l’anteprima social nelle impostazioni SEO della pagina, oppure fai stampare i tag dal layout.',
        ],
    ],
    'content' => [
        'thin' => [
            'title' => 'Poco testo',
            'found' => 'Una pagina indicizzabile ha meno parole della soglia.',
            'why' => 'I motori di ricerca posizionano più in basso le pagine con poco da leggere, e possono considerare di bassa qualità molte pagine così.',
            'fix' => 'Aggiungi testo utile al visitatore, unisci le pagine scarne, oppure chiudile con noindex.',
        ],
        'text_ratio' => [
            'title' => 'Poco testo rispetto al markup',
            'found' => 'Il testo visibile è una quota dell’HTML più piccola della soglia.',
            'why' => 'La pagina è pesante per ciò che dice: lenta su un telefono, e i motori di ricerca trovano poco contenuto in molto codice.',
            'fix' => 'Sposta script e stili inline in file, rimuovi il markup inutilizzato e aggiungi contenuto.',
        ],
        'duplicate' => [
            'title' => 'Testo duplicato',
            'found' => 'Più pagine indicizzabili hanno lo stesso testo visibile.',
            'why' => 'I motori di ricerca scelgono una copia da mostrare e ignorano le altre.',
            'fix' => 'Rendi diverse le pagine, uniscile, oppure fai puntare il canonical delle copie all’originale.',
        ],
    ],
    'url' => [
        'length' => [
            'title' => 'Indirizzo lungo',
            'found' => 'L’indirizzo è più lungo della soglia.',
            'why' => 'Gli indirizzi lunghi vengono tagliati nei risultati di ricerca e sono difficili da condividere e leggere.',
            'fix' => 'Accorcia lo slug della pagina; un reindirizzamento dal vecchio indirizzo viene aggiunto automaticamente.',
        ],
        'format' => [
            'title' => 'Formato dell’indirizzo',
            'found' => 'Il percorso contiene lettere maiuscole, trattini bassi o caratteri non ASCII.',
            'why' => 'Le maiuscole rendono /About e /about due pagine, i trattini bassi non separano le parole per i motori di ricerca, e gli altri caratteri diventano %D0%B0 quando vengono copiati.',
            'fix' => 'Usa negli slug lettere latine minuscole, cifre e trattini.',
        ],
        'params' => [
            'title' => 'Parametri senza canonical',
            'found' => 'Un indirizzo indicizzabile ha parametri di query e nessun canonical.',
            'why' => 'Ogni combinazione di filtri e ordinamento diventa una pagina a sé nei motori di ricerca, che dividono il peso di quella vera.',
            'fix' => 'Stampa un canonical verso l’indirizzo senza parametri, oppure chiudi questi indirizzi con noindex.',
        ],
    ],
    'perf' => [
        'ttfb' => [
            'title' => 'Risposta lenta',
            'found' => 'La pagina ha impiegato più della soglia per rispondere.',
            'why' => 'I visitatori aspettano prima che appaia qualcosa, e i motori di ricerca scansionano meno un sito lento.',
            'fix' => 'Attiva le cache (config, route, view, pagine), controlla le query lente e sposta il lavoro pesante nella coda.',
        ],
        'html_size' => [
            'title' => 'HTML pesante',
            'found' => 'L’HTML della pagina è più grande della soglia.',
            'why' => 'I telefoni lo scaricano e lo elaborano lentamente; i motori di ricerca possono smettere di leggere prima della fine.',
            'fix' => 'Pagina le liste lunghe, sposta dati inline e SVG in file, rimuovi le copie nascoste del contenuto.',
        ],
    ],
    'links' => [
        'broken' => [
            'title' => 'Link interni rotti',
            'found' => 'Un link a una pagina del sito risponde 4xx, 5xx o niente.',
            'why' => 'I visitatori finiscono su un errore, e i motori di ricerca sprecano la loro visita.',
            'fix' => 'Correggi o rimuovi il link, oppure aggiungi un reindirizzamento dall’indirizzo mancante alla pagina giusta.',
        ],
        'empty' => [
            'title' => 'Link senza testo',
            'found' => 'Un link non ha testo né aria-label, e un link con immagine non ha alt.',
            'why' => 'Gli screen reader leggono l’indirizzo o solo «link», e i motori di ricerca non imparano nulla sulla pagina a cui porta.',
            'fix' => 'Dai al link un testo, un aria-label, oppure un alt alla sua immagine.',
        ],
        'nofollow_internal' => [
            'title' => 'nofollow sui link interni',
            'found' => 'Un link a una pagina del sito ha rel="nofollow".',
            'why' => 'Il sito chiede ai motori di ricerca di non seguire i propri link, e la pagina riceve meno peso.',
            'fix' => 'Rimuovi nofollow dai link alle pagine del sito.',
        ],
    ],
    'mixed_content' => [
        'title' => 'Contenuto misto',
        'found' => 'Una pagina https carica una risorsa tramite http.',
        'why' => 'I browser bloccano questi script e stili e avvisano per le immagini; il lucchetto scompare.',
        'fix' => 'Carica la risorsa tramite https, oppure usa un percorso senza schema.',
    ],
    'forms' => [
        'insecure' => [
            'title' => 'Modulo inviato tramite http',
            'found' => 'Un modulo viene inviato a un indirizzo http.',
            'why' => 'Ciò che i visitatori scrivono viaggia non cifrato, e i browser avvisano prima dell’invio.',
            'fix' => 'Punta il modulo a un indirizzo https o a un percorso.',
        ],
    ],
    'images' => [
        'alt' => [
            'title' => 'Immagini senza alt',
            'found' => 'Un <img> non ha l’attributo alt.',
            'why' => 'Gli screen reader leggono il nome del file, e i motori di ricerca non sanno cosa mostri l’immagine. Un alt vuoto per un’immagine decorativa va bene.',
            'fix' => 'Descrivi l’immagine nel suo alt, oppure imposta alt="" se è decorazione.',
        ],
        'dimensions' => [
            'title' => 'Immagini senza dimensioni',
            'found' => 'Un <img> non ha width e height.',
            'why' => 'La pagina salta mentre le immagini si caricano, e i visitatori cliccano la cosa sbagliata.',
            'fix' => 'Stampa width e height delle immagini nel template; il CSS può comunque renderle adattive.',
        ],
    ],
    'a11y' => [
        'button_name' => [
            'title' => 'Pulsanti senza nome',
            'found' => 'Un pulsante non ha testo, aria-label o title.',
            'why' => 'Uno screen reader può dire solo «pulsante», e chi lo usa non sa cosa faccia.',
            'fix' => 'Dai al pulsante un testo, oppure un aria-label se è un’icona.',
        ],
        'form_label' => [
            'title' => 'Campi senza etichetta',
            'found' => 'Un campo di un modulo non ha label né aria-label.',
            'why' => 'Uno screen reader non può dire cosa scrivere; un segnaposto scompare appena si inizia a digitare.',
            'fix' => 'Aggiungi un <label for="…"> a ogni campo, oppure un aria-label.',
        ],
        'iframe_title' => [
            'title' => 'Frame senza titolo',
            'found' => 'Un iframe non ha title.',
            'why' => 'Gli screen reader annunciano un frame senza nome, e chi li usa non distingue una mappa da un video.',
            'fix' => 'Aggiungi un title che dica cosa mostra il frame.',
        ],
    ],
    'structure' => [
        'depth' => [
            'title' => 'Pagine profonde',
            'found' => 'Una pagina indicizzabile è più lontana dalla home page della soglia, in clic.',
            'why' => 'I motori di ricerca visitano meno spesso le pagine profonde e le valutano meno; i visitatori ci arrivano di rado.',
            'fix' => 'Collega la pagina da una categoria, dal menu o da pagine correlate.',
        ],
        'orphan' => [
            'title' => 'Pagine orfane',
            'found' => 'La pagina è nella sitemap o nel registro degli indirizzi, ma nessuna pagina del sito rimanda a essa.',
            'why' => 'I visitatori non possono raggiungerla, e i motori di ricerca considerano poco importante una pagina a cui nulla rimanda.',
            'fix' => 'Collega la pagina da dove deve stare, oppure depubblicala se non serve.',
        ],
        'dead_end' => [
            'title' => 'Vicoli ciechi',
            'found' => 'La pagina non rimanda a nessun’altra pagina del sito.',
            'why' => 'Un visitatore che ci arriva non ha dove andare se non indietro.',
            'fix' => 'Controlla che venga usato il layout con il suo menu, e aggiungi link a pagine correlate.',
        ],
    ],
];
