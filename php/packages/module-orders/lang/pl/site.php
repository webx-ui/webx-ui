<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Opłacone',
        'received' => 'Odebrane',
        'cancelled' => 'Anulowane',
        'refunded' => 'Zwrócono :amount',
    ],
    'blocks' => [
        'delivery' => 'Dostawa',
        'payment' => 'Płatność',
    ],
    'order' => [
        'title' => 'Zamówienie :number',
        'placed' => 'Złożone :date',
        'scale' => 'Gdzie jest zamówienie',
        'awaiting_payment' => 'Zamówienie czeka na płatność.',
        'items' => 'Produkty',
        'subtotal' => 'Produkty',
        'total' => 'Razem',
        'tenders' => 'Opłacono saldem i kartami podarunkowymi',
        'payable' => 'Do zapłaty',
        'contacts' => 'Kontakty',
        'customer' => 'Kupujący',
        'recipient' => 'Odbiorca',
        'comment' => 'Komentarz',
    ],
    'account' => [
        'menu' => 'Moje zamówienia',
        'title' => 'Moje zamówienia',
        'empty' => 'Jeszcze brak zamówień. Tu pojawią się zamówienia złożone po zalogowaniu.',
        'back' => '← Wszystkie zamówienia',
        'lines' => 'Pozycje: :count',
        'pages' => 'Strony',
        'newer' => '← Nowsze',
        'older' => 'Starsze →',
        'deletion' => 'Zamówienia w realizacji (:numbers) zostaną wykonane, ale nie zobaczysz ich już na koncie.',
    ],
];
