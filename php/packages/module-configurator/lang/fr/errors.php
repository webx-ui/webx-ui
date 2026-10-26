<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'Le schéma n’est pas un objet JSON.',
        'version' => 'Version de schéma inconnue : ce module lit la version 1.',
    ],
    'currency' => [
        'invalid' => 'La devise est un code ISO 4217 de trois lettres majuscules, comme UAH ou EUR.',
    ],
    'pricing' => [
        'round' => 'Le pas d’arrondi est un nombre supérieur à 0.',
    ],
    'discount' => [
        'percent' => 'La remise est un pourcentage supérieur à 0 et inférieur à 100.',
        'until' => 'Le dernier jour de la remise est une date AAAA-MM-JJ.',
    ],
    'steps' => [
        'empty' => 'Un configurateur a besoin d’au moins une étape.',
    ],
    'key' => [
        'invalid' => 'La clé « :key » n’est pas faite de a-z, 0-9 et _ commençant par une lettre.',
        'duplicate' => 'La clé « :key » est prise : étapes et champs de dimensions partagent leurs clés.',
    ],
    'type' => [
        'unknown' => 'Type d’étape inconnu : cards, swatches, buttons ou dimensions.',
    ],
    'options' => [
        'empty' => 'Une étape de choix a besoin d’au moins une option.',
    ],
    'fields' => [
        'empty' => 'Une étape de dimensions a besoin d’au moins un champ.',
    ],
    'value' => [
        'invalid' => 'La valeur « :value » n’est pas un slug : a-z, 0-9, - et _.',
        'duplicate' => 'La valeur « :value » apparaît deux fois dans cette étape.',
    ],
    'label' => [
        'missing' => 'Un nom dans la langue par défaut (:locale) est nécessaire.',
    ],
    'text' => [
        'invalid' => 'Un texte est une chaîne ou un ensemble de langues.',
    ],
    'image' => [
        'missing' => 'Choisissez une image de la médiathèque.',
        'unknown' => 'La médiathèque n’a pas de fichier « :key ».',
    ],
    'product' => [
        'invalid' => 'Un produit est désigné par sa référence (SKU), 64 caractères au plus.',
        'unknown' => 'Le catalogue n’a aucun produit de référence «:sku».',
    ],
    'swatch' => [
        'invalid' => 'Un échantillon est #rgb, #rrggbb ou un dégradé de couleurs hex.',
    ],
    'price' => [
        'invalid' => 'Un prix est un nombre.',
        'per_m2' => 'Un prix au m² a besoin d’une surface : donnez aux options d’une étape une formule de surface.',
    ],
    'area' => [
        'main_count' => 'Seules les options d’une étape peuvent avoir une formule de surface.',
        'missing' => 'Chaque option de l’étape principale a besoin d’une formule de surface.',
        'syntax' => 'La formule est fausse au caractère :position : :reason.',
        'unknown_key' => 'La formule utilise « :key », qui n’est pas un champ de dimensions.',
    ],
    'range' => [
        'invalid' => 'La plage est fausse : min au-dessus de max, ou un pas non supérieur à 0.',
    ],
    'limits' => [
        'unknown_field' => 'limits nomme « :key », qui n’est pas un champ de dimensions.',
    ],
    'defaults' => [
        'unknown_field' => 'defaults nomme « :key », qui n’est pas un champ de dimensions.',
    ],
    'condition' => [
        'invalid' => 'Une condition est une étape et les valeurs avec lesquelles elle est choisie.',
        'unknown_step' => 'Il n’y a pas d’étape « :step ».',
        'forward' => 'L’étape « :step » n’est pas au-dessus de celle-ci : une condition ne regarde que vers le haut.',
        'not_choice' => 'L’étape « :step » est une étape de dimensions : une condition nomme une étape à options.',
        'unknown_value' => 'L’étape « :step » n’a pas d’option « :value ».',
    ],
    'initial' => [
        'unknown' => 'Le choix initial « :key » n’est ni une étape, ni un champ, ni l’une de ses valeurs.',
    ],
    'previews' => [
        'condition' => 'Une règle d’image nomme une étape ou une valeur qui n’existe pas.',
    ],
    'values' => [
        'range' => 'La dimension « :key » va de :min à :max.',
        'number' => 'La dimension « :key » est un nombre.',
    ],
];
