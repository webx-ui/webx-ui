<?php

declare(strict_types=1);

return [
    'template-unclosed' => 'La section {{#:section}} n\'est pas fermée : terminez-la par {{/:section}}.',
    'template-unexpected' => '{{/:section}} ferme une section qui n\'a jamais été ouverte.',
    'template-tag' => 'La balise {{:tag n\'est pas fermée : terminez-la par }}.',
    'template-name' => '{{:name}} n\'est pas un nom de variable : minuscules, chiffres et _, un point au plus.',
    'template-partial' => 'Les partiels ({{>name}}) ne sont pas disponibles dans un modèle de message.',
    'template-delimiters' => 'Le changement de délimiteurs ({{=<% %>=}}) n\'est pas disponible dans un modèle de message.',
    'template-pragma' => 'Les pragmas et les blocs ({{%…}}, {{$…}}) ne sont pas disponibles dans un modèle de message.',
    'template-cdata' => 'Une variable ne peut pas se trouver dans un bloc CDATA littéral : {{:name}} serait échappée deux fois. Retirez le CDATA qui l’entoure et utilisez la variable _cdata, par exemple {{description_cdata}}.',
];
