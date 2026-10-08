<?php

declare(strict_types=1);

return [
    'no-marker' => 'Nessun data-wx-block sulla radice: lo script non partirà e il pannello non potrà evidenziare il blocco nell’anteprima.',
    'stray-selectors' => 'Selettori fuori dal prefisso del blocco .b-:slug: :selectors',
    'bare-selectors' => 'I selettori di elemento raggiungono tutto il sito: :selectors',
    'media-query' => '@media misura la finestra. Un blocco si dimensiona sul suo contenitore: usa @container.',
    'string-on-text' => 'Con uno shortcode dentro, :field è HTML: una funzione di stringa o un cast passa quell’HTML a {{ }}, che lo escapa una seconda volta. Modificalo con wx_text(): {{ wx_text(:field)->trimEnd(".") }} — trim, trimStart, stripPrefix, stripSuffix e map() lo mantengono HTML.',
    'variables-missing' => 'Il template usa :variables, che lo schema non dichiara. La pubblicazione sarà rifiutata.',
    'ok-marker' => 'La radice porta data-wx-block.',
    'ok-prefix' => 'Ogni selettore inizia con .b-:slug.',
    'ok-bare' => 'Nessun selettore di elemento nudo.',
    'ok-container' => 'La larghezza è decisa dalle container query.',
    'ok-variables' => 'Ogni variabile del template è un campo dello schema.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Valori di blocco per campi che il tipo non ha',
            'found' => 'Alcuni blocchi hanno valori di campi che il loro tipo non definisce, rimasti da un’importazione o da un campo tolto dal tipo.',
            'why' => 'Il visitatore non li vede, ma stanno nei dati dell’editor e in ciò che legge un agente, e riemergono come etichetta sbagliata del blocco.',
            'fix' => 'Toglieteli con la correzione o per tutto il sito con php artisan webx:blocks:prune. Un blocco di un tipo che non esiste più non viene toccato. Gli elementi di un ripetitore sono confrontati con i suoi campi.',
        ],
        'unknown_shortcodes' => [
            'title' => 'Shortcode scritti male',
            'found' => 'Un testo contiene una parentesi che è quasi uno shortcode del sito, o che ha argomenti, ma non lo è.',
            'why' => 'Si sostituiscono solo gli shortcode registrati. Il resto viene stampato così com’è, parentesi comprese, sotto gli occhi di ogni visitatore.',
            'fix' => 'Correggete il nome con quello suggerito, o scrivete [[nome]] se la pagina deve mostrare le parentesi. L’elenco è nell’aiuto shortcode del campo e in «Impostazioni» → «Shortcode».',
        ],
        'hardcoded_values' => [
            'title' => 'Valori al posto di uno shortcode',
            'found' => 'Un testo contiene un telefono, un’e-mail o un altro valore che uno shortcode di «Impostazioni» → «Shortcode» contiene già.',
            'why' => 'Oggi è giusto, sbagliato il giorno in cui il valore cambia: lo shortcode cambia ovunque, un valore scritto a mano solo dove qualcuno se ne ricorda.',
            'fix' => 'Sostituite il valore con lo shortcode suggerito, ad es. [phone]. Il link lo crea lo shortcode stesso.',
        ],
    ],
    'syntax' => 'Il modello non si compila: :reason. La pubblicazione sarà rifiutata.',
    'unknown-field-type' => 'Campi di un tipo che il sito non conosce: :fields. Il modulo mostra un avviso al loro posto e nessuno ne controlla i valori.',
    'field-id' => 'L\'id di un campo è fatto di lettere, cifre, _ e -, e inizia con una lettera: :ids.',
    'marker-slug' => 'La radice è marcata data-wx-block=":marker", ma l’identificatore è «:slug»: lo script e il pannello trovano il blocco con l’identificatore esatto. La pubblicazione sarà rifiutata.',
];
