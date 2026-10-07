<?php

declare(strict_types=1);

return [
    'refused' => 'Le formulaire n\'a pas pu être envoyé. Merci de réessayer dans un instant.',
    'too-many' => 'Trop d\'envois depuis cette adresse. Merci de réessayer dans une minute.',
    'form-has-submissions' => 'Le formulaire « :form » a des soumissions : il peut être désactivé, pas supprimé.',
    'status-in-use' => 'Des soumissions sont encore au statut « :status » : il ne peut pas être supprimé.',
    'no-statuses' => 'Aucun statut à donner à une nouvelle soumission. Lancez les migrations.',
    'file-missing' => 'Ce fichier n\'est plus là.',

    'slug-shape' => 'Une adresse s’écrit en minuscules, chiffres et traits d’union : « contact-us ».',
    'field-name-shape' => 'Un nom commence par une lettre, puis lettres, chiffres, tirets et tirets bas.',
    'recipient-shape' => 'Le destinataire :entry n\'est ni un administrateur ni une adresse e-mail.',
    'recipient-unknown' => 'Le destinataire :entry désigne un administrateur qui n\'existe pas.',
    'choices-required' => 'Un champ de liste a besoin d\'au moins un choix avec une valeur.',
    'email-field' => 'Le champ de réponse doit être un champ e-mail de ce formulaire.',
    'nobody-to-notify' => 'Le formulaire « :form » ne désigne personne à qui écrire.',
];
