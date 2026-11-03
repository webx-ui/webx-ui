<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Nova Poshta Filiale',
            'description' => 'Abholung in einer Filiale des Versanddienstes; die Lieferung zahlen Sie bei Abholung.',
        ],
        'locker' => [
            'title' => 'Nova Poshta Paketautomat',
            'description' => 'Abholung an einem Paketautomaten des Versanddienstes; die Lieferung zahlen Sie bei Abholung.',
        ],
        'courier' => [
            'title' => 'Nova Poshta Kurier',
            'description' => 'Der Versanddienst bringt das Paket an Ihre Tür; die Lieferung zahlen Sie bei Empfang.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Zahlung der Lieferung',
        'payment_methods' => [
            'Cash' => 'Bar',
            'NonCash' => 'Unbar (mit Vertrag beim Versanddienst)',
        ],
        'cargo_type' => 'Standard-Frachtart',
        'cargo_types' => [
            'Parcel' => 'Paket',
            'Cargo' => 'Fracht',
            'Documents' => 'Dokumente',
        ],
        'weight' => 'Standardgewicht, kg',
        'description' => 'Standardbeschreibung der Sendung',
        'description_default' => 'Waren',
        'sender' => 'Absender',
        'contact' => 'Kontaktperson',
        'sender_city' => 'Versandort',
        'sender_point' => 'Versandfiliale',
        'locker_width' => 'Paketautomat: Standardbreite, cm',
        'locker_height' => 'Paketautomat: Standardhöhe, cm',
        'locker_length' => 'Paketautomat: Standardlänge, cm',
    ],
    'credentials' => [
        'api_key' => 'API-Schlüssel',
    ],
    'connection' => [
        'ok' => 'Absender im Konto: :count',
        'key_refused' => 'Der Versanddienst hat den API-Schlüssel nicht akzeptiert.',
        'no_answer' => 'Der Versanddienst hat nicht geantwortet.',
        'no_sender' => 'Im Konto des Versanddienstes gibt es keinen Absender.',
        'no_key' => 'Geben Sie zuerst den API-Schlüssel ein.',
    ],
    'waybill' => [
        'weight' => 'Gewicht, kg',
        'seats' => 'Packstücke',
        'cargo_type' => 'Frachtart',
        'description' => 'Beschreibung der Sendung',
        'date' => 'Versanddatum',
        'width' => 'Breite, cm',
        'height' => 'Höhe, cm',
        'length' => 'Länge, cm',
    ],
    'errors' => [
        'no_cod' => 'Nachnahme wird von diesem Versanddienst noch nicht unterstützt.',
        'name_empty' => 'Geben Sie Vor- und Nachnamen des Empfängers ein.',
        'name_letters' => 'Der Versanddienst nimmt den Namen nur in ukrainischen Buchstaben an.',
        'phone' => 'Der Versanddienst nimmt eine ukrainische Mobilnummer an: +380 und neun Ziffern.',
        'no_sender' => 'Wählen Sie zuerst in den Einstellungen des Versanddienstes Absender, Kontaktperson, Versandort und Versandfiliale.',
        'contact_gone' => 'Die Kontaktperson aus den Einstellungen ist nicht mehr im Konto des Versanddienstes: wählen Sie eine andere.',
        'no_town' => 'Der Versanddienst kennt den Ort der Bestellung nicht: ordnen Sie ihn in der Warteschlange der Orte zu.',
        'no_point' => 'Die Bestellung hat keine Filiale und keinen Paketautomaten des Versanddienstes.',
        'no_street' => 'Die Bestellung hat keine Straße des Versanddienstes oder keine Hausnummer.',
        'weight' => 'Das Gewicht muss größer als null sein.',
        'weight_too_much' => 'Das Gewicht ist größer, als die Stelle annimmt.',
        'seats' => 'Packstücke sind eine ganze Zahl ab eins.',
        'locker_seats' => 'Ein Paketautomat nimmt ein Packstück an.',
        'cargo_type' => 'Wählen Sie eine Frachtart aus der Liste.',
        'size' => 'Die Maße müssen größer als null sein.',
        'locker_size' => 'Ein Paketautomat nimmt bis 40 × 30 × 60 cm an.',
        'date' => 'Das Versanddatum ist heute oder ein Tag in den nächsten drei Monaten.',
        'read_only' => 'Diese Website nutzt den Schlüssel des Versanddienstes nur lesend: Frachtbriefe sind aus.',
        'no_ref' => 'Die Kennung dieses Frachtbriefs beim Versanddienst ist nicht gespeichert: stornieren Sie ihn im Konto des Versanddienstes.',
        'carrier_said' => 'Der Versanddienst hat abgelehnt: :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Übersprungen, Servicepunkte des Dienstes: :count',
        'novaposhta_deny_to_select' => 'Übersprungen, nicht auswählbar: :count',
        'novaposhta_invalid_point' => 'Übersprungen, mit unvollständigen Daten: :count',
    ],
];
