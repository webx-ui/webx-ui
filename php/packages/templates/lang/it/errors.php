<?php

declare(strict_types=1);

return [
    'template-unclosed' => 'La sezione {{#:section}} non è chiusa: terminala con {{/:section}}.',
    'template-unexpected' => '{{/:section}} chiude una sezione che non è mai stata aperta.',
    'template-tag' => 'Il tag {{:tag non è chiuso: terminalo con }}.',
    'template-name' => '{{:name}} non è un nome di variabile: lettere minuscole, cifre e _, al massimo un punto.',
    'template-partial' => 'I partial ({{>name}}) non sono disponibili in un modello di messaggio.',
    'template-delimiters' => 'Il cambio dei delimitatori ({{=<% %>=}}) non è disponibile in un modello di messaggio.',
    'template-pragma' => 'Pragma e blocchi ({{%…}}, {{$…}}) non sono disponibili in un modello di messaggio.',
    'template-cdata' => 'Una variabile non può stare dentro un blocco CDATA letterale: {{:name}} verrebbe codificata due volte. Togli il CDATA che la circonda e usa la variabile _cdata, ad esempio {{description_cdata}}.',
];
