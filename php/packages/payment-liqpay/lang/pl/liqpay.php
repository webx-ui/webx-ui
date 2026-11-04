<?php

declare(strict_types=1);

return [
    'gateway' => [
        'title' => 'LiqPay',
        'mode' => 'Tryb',
        'modes' => [
            'test' => 'Testowy',
            'live' => 'Produkcyjny',
        ],
        'test_public_key' => 'Klucz publiczny, test',
        'live_public_key' => 'Klucz publiczny, produkcyjny',
        'test_private_key' => 'Klucz prywatny, test',
        'live_private_key' => 'Klucz prywatny, produkcyjny',
        'language' => 'Język strony',
        'language_help' => 'LiqPay pokazuje stronę tylko po ukraińsku i angielsku.',
        'languages' => [
            'auto' => 'Według języka zamówienia',
            'uk' => 'Ukraiński',
            'en' => 'Angielski',
        ],
        'expires_minutes' => 'Ile minut działa strona płatności',
    ],
    'paytypes' => [
        'card' => 'Karta',
        'apay' => 'Apple Pay',
        'gpay' => 'Google Pay',
        'privat24' => 'Privat24',
        'qr' => 'Kod QR',
        'moment_part' => 'Raty natychmiastowe',
        'paypart' => 'Płatność w częściach',
        'paypart_help' => 'Najpierw włącz usługę w panelu LiqPay',
        'hold_cards_only' => 'Blokada działa tylko przy płatności kartą',
        'hold_needs_choice' => 'Przy blokadzie wybierz, co oferuje strona: bez wyboru pokaże też raty',
    ],
    'connection' => [
        'ok' => 'LiqPay zaakceptował klucze',
        'no_keys' => 'Wpisz oba klucze tego trybu',
        'wrong_mode' => 'Te klucze nie pasują do wybranego trybu',
        'private_key' => 'Klucz prywatny nie pasuje',
        'public_key' => 'Nie znaleziono klucza publicznego',
        'no_answer' => 'LiqPay nie odpowiedział',
    ],
    'status' => [
        'wait_accept' => 'LiqPay nie sprawdził jeszcze sklepu: pieniądze wpłyną po weryfikacji',
        'wait_reserve' => 'Płatność przeszła; pieniądze są zarezerwowane na wcześniejszy zwrot',
        'subscribed' => 'LiqPay zgłasza subskrypcję: strona ich nie ma',
        'unsubscribed' => 'LiqPay zgłasza anulowanie subskrypcji: strona ich nie ma',
        'unknown' => 'Nieznany status LiqPay: :status',
    ],
    'errors' => [
        '9859' => 'Na karcie brakuje środków',
        'limit' => 'Bank odmówił: osiągnięto limit karty',
        'expired' => 'Czas na płatność minął',
        'wait_info' => 'LiqPay czeka na dodatkowe dane od kupującego',
        'err_amount_hold' => 'Kwota przekracza zablokowaną',
        'payment_err_status' => 'LiqPay nie pozwala na to przy obecnym statusie płatności',
        'order_id_duplicate' => 'LiqPay ma już opłaconą płatność o tym numerze',
        'invalid_signature' => 'Podpis się nie zgadza: sprawdź klucz prywatny',
        'public_key_not_found' => 'LiqPay nie zna tego klucza publicznego',
        'payment_not_found' => 'LiqPay nie ma takiej płatności: kupujący jej nie rozpoczął',
        'err_wrong_currency' => 'LiqPay nie przyjmuje tej waluty',
        'wrong_amount_currency' => 'Blokady nie można zwolnić częściowo',
        'refused' => 'LiqPay odrzucił operację',
    ],
];
