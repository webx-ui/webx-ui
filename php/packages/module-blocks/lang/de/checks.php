<?php

declare(strict_types=1);

return [
    'no-marker' => 'Kein data-wx-block an der Wurzel: Das Skript läuft nicht, und das Panel kann den Block in der Vorschau nicht hervorheben.',
    'stray-selectors' => 'Selektoren außerhalb des Blockpräfixes .b-:slug: :selectors',
    'bare-selectors' => 'Elementselektoren greifen auf die ganze Site: :selectors',
    'media-query' => '@media misst das Fenster. Ein Block richtet sich nach seinem Container: Verwenden Sie @container.',
    'variables-missing' => 'Das Template verwendet :variables, die das Schema nicht deklariert. Die Veröffentlichung wird abgelehnt.',
    'ok-marker' => 'Die Wurzel trägt data-wx-block.',
    'ok-prefix' => 'Jeder Selektor beginnt mit .b-:slug.',
    'ok-bare' => 'Keine nackten Elementselektoren.',
    'ok-container' => 'Die Breite wird per Container-Query bestimmt.',
    'ok-variables' => 'Jede Variable des Templates ist ein Feld des Schemas.',
];
