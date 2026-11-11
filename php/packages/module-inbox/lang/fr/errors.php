<?php

declare(strict_types=1);

return [
    'refused' => 'Le formulaire n\'a pas pu être envoyé. Merci de réessayer dans un instant.',
    'enclosure' => 'Ce qui est joint à ce formulaire ne peut pas être envoyé avec lui. Rechargez la page et réessayez.',
    'captcha' => 'Confirmez que vous n’êtes pas un robot et envoyez le formulaire à nouveau.',
    'captcha-script' => 'Ce formulaire vérifie avec JavaScript que vous n’êtes pas un robot. Activez-le et envoyez le formulaire à nouveau.',
    'captcha-unavailable' => 'Nous n’avons pas pu vérifier que vous n’êtes pas un robot. Rechargez la page ou essayez un autre navigateur.',
    'too-many' => 'Trop d\'envois depuis cette adresse. Merci de réessayer dans une minute.',
    'form-has-submissions' => 'Le formulaire « :form » a des soumissions : il peut être désactivé, pas supprimé.',
    'status-in-use' => 'Des soumissions sont encore au statut « :status » : il ne peut pas être supprimé.',
    'no-statuses' => 'Aucun statut à donner à une nouvelle soumission. Lancez les migrations.',
    'file-missing' => 'Ce fichier n\'est plus là.',

    'slug-empty' => 'Une adresse a besoin de lettres ou de chiffres, par exemple « contact-us ».',
    'field-name-empty' => 'Un nom a besoin de lettres ou de chiffres, par exemple « phone ».',
    'status-key-empty' => 'Une clé a besoin de lettres ou de chiffres, par exemple « in-progress ».',
    'recipient-shape' => 'Le destinataire :entry n\'est ni un administrateur ni une adresse e-mail.',
    'recipient-unknown' => 'Le destinataire :entry désigne un administrateur qui n\'existe pas.',
    'choices-required' => 'Un champ de liste a besoin d\'au moins un choix avec une valeur.',
    'email-field' => 'Le champ de réponse doit être un champ e-mail de ce formulaire.',
    'no-such-answer' => 'Cet envoi n\'a pas de réponse sous ce nom.',
    'not-correctable' => 'Un fichier ou un consentement ne peut pas être corrigé.',
    'nobody-to-notify' => 'Le formulaire « :form » ne désigne personne à qui écrire.',
    'condition-unknown-field' => 'La condition « :key » de « :field » nomme « :name », un champ que ce formulaire n’a pas (ou que cette modification supprime).',
    'condition-not-choice' => 'La condition « :key » de « :field » nomme « :name », qui n’est pas un choix : seule une liste, des boutons radio, des cases ou un consentement au-dessus peuvent servir de condition.',
    'condition-not-before' => 'La condition « :key » de « :field » nomme « :name », qui n’est pas au-dessus. Une condition ne nomme qu’un champ au-dessus.',
    'condition-unknown-value' => 'La condition « :key » de « :field » attend la valeur « :value » de « :name », que ce champ ne propose pas.',
    'field-has-dependents' => 'Le champ « :field » est nommé dans les conditions de :fields. Modifiez d’abord ces conditions.',
    'step-empty' => 'L’étape :field n’a aucun champ après elle. Placez un champ dessous ou supprimez-la.',
    'step-too-many' => 'Un formulaire a au plus :max étapes.',
    'step-unknown' => 'Le formulaire a changé depuis l’ouverture de la page. Rechargez la page.',
    'datetime-range' => 'La journée n’a la place pour aucun horaire : « De » et « À » au format HH:MM, et « De » au moins un pas avant « À ».',
    'datetime-step' => 'Le pas des horaires est de 15, 20, 30, 60, 90 ou 120 minutes.',
    'datetime-weekdays' => 'Chaque jour de la semaine une seule fois, de 1 (lundi) à 7 (dimanche).',
];
