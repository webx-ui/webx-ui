<?php

declare(strict_types=1);

return [
    'refused' => 'Nie udało się wysłać formularza. Spróbuj ponownie za chwilę.',
    'enclosure' => 'Tego, co dołączono do formularza, nie można z nim wysłać. Odśwież stronę i spróbuj ponownie.',
    'captcha' => 'Potwierdź, że nie jesteś robotem, i wyślij formularz ponownie.',
    'captcha-script' => 'Ten formularz sprawdza za pomocą JavaScriptu, że nie jesteś robotem. Włącz go i wyślij formularz ponownie.',
    'captcha-unavailable' => 'Nie udało się sprawdzić, że nie jesteś robotem. Odśwież stronę albo spróbuj innej przeglądarki.',
    'too-many' => 'Zbyt wiele zgłoszeń z tego adresu. Spróbuj ponownie za minutę.',
    'form-has-submissions' => 'Formularz „:form” ma zgłoszenia, więc można go wyłączyć, ale nie usunąć.',
    'status-in-use' => 'W statusie „:status” są jeszcze zgłoszenia, więc nie można go usunąć.',
    'no-statuses' => 'Nie ma statusu, który można nadać nowemu zgłoszeniu. Uruchom migracje.',
    'file-missing' => 'Tego pliku już nie ma.',

    'slug-shape' => 'Adres to małe litery, cyfry i myślniki: „contact-us”.',
    'field-name-shape' => 'Nazwa zaczyna się od litery, dalej litery, cyfry, myślniki i podkreślenia.',
    'recipient-shape' => 'Odbiorca :entry nie jest ani administratorem, ani adresem e-mail.',
    'recipient-unknown' => 'Odbiorca :entry wskazuje administratora, który nie istnieje.',
    'choices-required' => 'Pole listy potrzebuje co najmniej jednej opcji z wartością.',
    'email-field' => 'Pole odpowiedzi musi być polem e-mail tego formularza.',
    'no-such-answer' => 'To zgłoszenie nie ma odpowiedzi o tej nazwie.',
    'not-correctable' => 'Pliku ani zgody nie można poprawić.',
    'nobody-to-notify' => 'Formularz „:form” nie wskazuje nikogo, do kogo pisać.',
    'condition-unknown-field' => 'Warunek „:key” pola „:field” wskazuje „:name”, pole, którego ten formularz nie ma (albo które ta zmiana usuwa).',
    'condition-not-choice' => 'Warunek „:key” pola „:field” wskazuje „:name”, które nie jest polem wyboru: warunkiem może być tylko lista, przyciski opcji, pola wyboru lub zgoda powyżej.',
    'condition-not-before' => 'Warunek „:key” pola „:field” wskazuje „:name”, które nie stoi wyżej. Warunek wskazuje tylko pole powyżej.',
    'condition-unknown-value' => 'Warunek „:key” pola „:field” oczekuje wartości „:value” pola „:name”, której to pole nie oferuje.',
    'field-has-dependents' => 'Pole „:field” jest wskazane w warunkach pól :fields. Najpierw zmień te warunki.',
    'step-empty' => 'Po kroku :field nie ma żadnego pola. Dodaj pod nim pole albo usuń krok.',
    'step-too-many' => 'Formularz ma najwyżej :max kroków.',
    'step-unknown' => 'Formularz zmienił się, odkąd otwarto stronę. Odśwież stronę.',
];
