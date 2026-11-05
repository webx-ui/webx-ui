<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Metodo di pagamento',
        'save' => 'Salva',
        'delivery_first' => 'Scegli prima la consegna: da essa dipendono i metodi di pagamento',
        'method_dropped' => 'Il metodo di pagamento scelto non è disponibile per questa consegna. Scegline un altro.',
    ],
    'errors' => [
        'choose_method' => 'Scegli un metodo di pagamento',
        'method_gone' => 'Il metodo di pagamento non è più disponibile, scegline un altro',
    ],
    'instructions' => [
        'title' => 'Coordinate bancarie per il pagamento',
        'recipient' => 'Beneficiario',
        'account' => 'Conto',
        'purpose' => 'Causale',
        'note' => 'Termine',
    ],
    'status' => [
        'paid' => 'Pagato',
        'awaiting' => 'In attesa di pagamento',
        'pay' => 'Paga',
    ],
    'pay' => [
        'title' => 'Pagamento dell’ordine :number',
        'description' => 'Ordine :number',
        'due' => 'Da pagare: :amount',
        'paid' => 'L’ordine :number è pagato',
        'cancelled' => 'L’ordine :number è annullato',
        'checking' => 'Stiamo verificando il pagamento. Di solito ci vuole fino a un minuto.',
        'checking_long' => 'La banca non ha ancora confermato il pagamento. Se il denaro è stato addebitato, l’ordine risulterà pagato da solo e ti invieremo un’e-mail. Puoi chiudere questa pagina.',
        'check_again' => 'Verifica di nuovo',
        'held' => 'Pagamento accettato. L’importo è bloccato sulla carta e verrà addebitato dal negozio.',
        'received' => 'Pagamento ricevuto',
        'accepted' => 'Pagamento accettato',
        'failed' => 'Il pagamento non è andato a buon fine',
        'unavailable' => 'Impossibile aprire il pagamento. Riprova o scegli un altro metodo.',
        'on_receipt' => 'L’ordine si paga alla consegna',
        'order_link' => 'Vai all’ordine',
        'redirecting' => 'Ti portiamo alla pagina di pagamento…',
        'go' => 'Vai al pagamento',
        'top_up' => 'Da integrare: :amount',
        'top_up_button' => 'Paga la differenza',
        'online_now' => 'Paga online ora',
        'wait' => 'Se hai già pagato in un’altra scheda, attendi un minuto prima di pagare di nuovo.',
        'declined' => 'Il pagamento non è andato a buon fine: la banca ha rifiutato la carta',
        'unfinished' => 'Pagamento non completato',
        'pay_again' => 'Paga di nuovo',
        'other_method' => 'Scegli un altro metodo',
        'continue' => 'Continua',
    ],
];
