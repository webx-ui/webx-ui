<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Problema di pagamento',
    'vars' => [
        'payment_url' => 'Link per pagare l’ordine',
        'payment_instructions' => 'Coordinate bancarie per il pagamento',
        'payment_method' => 'Metodo di pagamento',
        'number' => 'Numero d’ordine',
        'order_url' => 'Ordine nel pannello',
        'problem' => 'Cosa è successo',
        'amount' => 'Importo',
        'method' => 'Metodo di pagamento',
        'gateway_ref' => 'ID del pagamento presso il gateway',
        'message' => 'Dettagli',
    ],
    'sample' => [
        'subject' => 'Problema di pagamento: ordine {{number}}',
        'template' => "{{problem}} — ordine {{number}}, {{amount}}, {{method}}.\n{{message}}\nID del pagamento: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Pagamenti',
        'card' => 'Notifiche',
        'help' => 'Chi avvisare quando il denaro arriva dove non dovrebbe: pagato due volte, importo errato, denaro per un ordine annullato, una preautorizzazione in scadenza, un addebito rifiutato.',
    ],
];
