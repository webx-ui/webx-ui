<?php

declare(strict_types=1);

return [
    'refused' => 'Non è stato possibile inviare il modulo. Riprova tra un momento.',
    'enclosure' => 'Ciò che è allegato a questo modulo non può essere inviato con esso. Ricarica la pagina e riprova.',
    'captcha' => 'Conferma di non essere un robot e invia di nuovo il modulo.',
    'captcha-script' => 'Questo modulo verifica con JavaScript che tu non sia un robot. Attivalo e invia di nuovo il modulo.',
    'captcha-unavailable' => 'Non siamo riusciti a verificare che tu non sia un robot. Ricarica la pagina o prova un altro browser.',
    'too-many' => 'Troppi invii da questo indirizzo. Riprova tra un minuto.',
    'form-has-submissions' => 'Il modulo «:form» ha richieste, quindi può essere disattivato ma non eliminato.',
    'status-in-use' => 'Ci sono ancora richieste nello stato «:status», quindi non può essere eliminato.',
    'no-statuses' => 'Non c’è nessuno stato da dare a una nuova richiesta. Esegui le migrazioni.',
    'file-missing' => 'Questo file non c’è più.',

    'slug-shape' => 'Un indirizzo si scrive con minuscole, cifre e trattini: «contact-us».',
    'field-name-shape' => 'Un nome inizia con una lettera; poi lettere, cifre, trattini e trattini bassi.',
    'recipient-shape' => 'Il destinatario :entry non è né un amministratore né un indirizzo e-mail.',
    'recipient-unknown' => 'Il destinatario :entry indica un amministratore che non esiste.',
    'choices-required' => 'Un campo elenco ha bisogno di almeno una scelta con un valore.',
    'email-field' => 'Il campo per la risposta deve essere un campo e-mail di questo modulo.',
    'no-such-answer' => 'Questo invio non ha una risposta con quel nome.',
    'not-correctable' => 'Un file o un consenso non si possono correggere.',
    'nobody-to-notify' => 'Il modulo «:form» non indica a chi scrivere.',
    'condition-unknown-field' => 'La condizione «:key» di «:field» nomina «:name», un campo che questo modulo non ha (o che questa modifica rimuove).',
    'condition-not-choice' => 'La condizione «:key» di «:field» nomina «:name», che non è una scelta: solo un elenco, pulsanti di opzione, caselle o un consenso sopra possono essere una condizione.',
    'condition-not-before' => 'La condizione «:key» di «:field» nomina «:name», che non sta sopra. Una condizione nomina solo un campo sopra.',
    'condition-unknown-value' => 'La condizione «:key» di «:field» si aspetta il valore «:value» di «:name», che quel campo non offre.',
    'field-has-dependents' => 'Il campo «:field» è nominato nelle condizioni di :fields. Cambia prima quelle condizioni.',
    'step-empty' => 'Il passo :field non ha alcun campo dopo di sé. Metti un campo sotto o eliminalo.',
    'step-too-many' => 'Un modulo ha al massimo :max passi.',
    'step-unknown' => 'Il modulo è cambiato da quando la pagina è stata aperta. Ricarica la pagina.',
];
