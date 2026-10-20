<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Zgłoszenie z formularza',
    // The default letter: a note that answering it writes to whoever filled the form.
    'reply' => 'Odpowiedz na ten list, aby odpisać odwiedzającemu: :address.',
    'vars' => [
        'form' => 'Nazwa formularza',
        'form_slug' => 'Slug formularza',
        'submission_id' => 'Numer zgłoszenia',
        'submission_url' => 'Zgłoszenie w panelu',
        'created_at' => 'Kiedy przyszło',
        'fields' => 'Wszystkie odpowiedzi, po wierszu na każdą: „Etykieta: wartość”',
        'answers' => 'Odpowiedzi jako lista',
        'answer_name' => 'Nazwa techniczna pola',
        'answer_label' => 'Etykieta pola',
        'answer_value' => 'Odpowiedź',
        'field' => 'Jedna odpowiedź, według nazwy technicznej jej pola',
        'files' => 'Nazwy załączonych plików',
        'page' => 'Strona, z której wysłano formularz',
        'locale' => 'Język zgłoszenia',
        'utm' => 'Tagi UTM',
        'reply_to' => 'E-mail odwiedzającego z pola e-mail formularza',
    ],
];
