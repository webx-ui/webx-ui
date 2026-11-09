<?php

declare(strict_types=1);

return [
    'refused' => 'Das Formular konnte nicht gesendet werden. Bitte versuchen Sie es gleich noch einmal.',
    'enclosure' => 'Was an dieses Formular angehängt ist, kann nicht mit ihm gesendet werden. Laden Sie die Seite neu und versuchen Sie es erneut.',
    'captcha' => 'Bitte bestätigen Sie, dass Sie kein Roboter sind, und senden Sie das Formular erneut.',
    'captcha-script' => 'Dieses Formular prüft mit JavaScript, dass Sie kein Roboter sind. Bitte schalten Sie es ein und senden Sie das Formular erneut.',
    'captcha-unavailable' => 'Wir konnten nicht prüfen, dass Sie kein Roboter sind. Bitte laden Sie die Seite neu oder versuchen Sie einen anderen Browser.',
    'too-many' => 'Zu viele Einsendungen von dieser Adresse. Bitte versuchen Sie es in einer Minute erneut.',
    'form-has-submissions' => 'Das Formular „:form“ hat Einsendungen und kann deshalb deaktiviert, aber nicht gelöscht werden.',
    'status-in-use' => 'Im Status „:status“ liegen noch Einsendungen; er kann nicht gelöscht werden.',
    'no-statuses' => 'Es gibt keinen Status für eine neue Einsendung. Führen Sie die Migrationen aus.',
    'file-missing' => 'Diese Datei ist nicht mehr da.',

    'slug-shape' => 'Eine Adresse besteht aus Kleinbuchstaben, Ziffern und Bindestrichen: „contact-us“.',
    'field-name-shape' => 'Ein Name beginnt mit einem Buchstaben; danach Buchstaben, Ziffern, Bindestrich und Unterstrich.',
    'recipient-shape' => 'Empfänger :entry ist weder ein Administrator noch eine E-Mail-Adresse.',
    'recipient-unknown' => 'Empfänger :entry nennt einen Administrator, den es nicht gibt.',
    'choices-required' => 'Ein Listenfeld braucht mindestens eine Auswahl mit einem Wert.',
    'email-field' => 'Das Antwortfeld muss ein E-Mail-Feld dieses Formulars sein.',
    'no-such-answer' => 'Diese Einsendung hat keine Antwort unter diesem Namen.',
    'not-correctable' => 'Eine Datei oder eine Einwilligung kann nicht korrigiert werden.',
    'nobody-to-notify' => 'Das Formular „:form“ nennt niemanden, dem geschrieben wird.',
    'condition-unknown-field' => 'Die Bedingung „:key“ von „:field“ nennt „:name“, ein Feld, das dieses Formular nicht hat (oder das diese Änderung entfernt).',
    'condition-not-choice' => 'Die Bedingung „:key“ von „:field“ nennt „:name“, das keine Auswahl ist: Bedingung kann nur eine Liste, Optionsfelder, Häkchen oder eine Einwilligung darüber sein.',
    'condition-not-before' => 'Die Bedingung „:key“ von „:field“ nennt „:name“, das nicht darüber steht. Eine Bedingung nennt nur ein Feld darüber.',
    'condition-unknown-value' => 'Die Bedingung „:key“ von „:field“ erwartet den Wert „:value“ von „:name“, den dieses Feld nicht anbietet.',
    'field-has-dependents' => 'Das Feld „:field“ wird in den Bedingungen von :fields genannt. Ändern Sie zuerst diese Bedingungen.',
    'step-empty' => 'Nach dem Schritt :field steht kein Feld. Setzen Sie ein Feld darunter oder entfernen Sie ihn.',
    'step-too-many' => 'Ein Formular hat höchstens :max Schritte.',
    'step-unknown' => 'Das Formular hat sich geändert, seit die Seite geöffnet wurde. Laden Sie die Seite neu.',
    'datetime-range' => 'Der Tag hat keinen Platz für eine Uhrzeit: «Von» und «Bis» als HH:MM, und «Von» mindestens einen Schritt vor «Bis».',
    'datetime-step' => 'Der Schritt der Uhrzeiten ist 15, 20, 30, 60, 90 oder 120 Minuten.',
    'datetime-weekdays' => 'Jeder Wochentag einmal, von 1 (Montag) bis 7 (Sonntag).',
];
