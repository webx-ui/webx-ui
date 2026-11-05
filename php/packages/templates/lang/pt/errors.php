<?php

declare(strict_types=1);

return [
    'template-unclosed' => 'A secção {{#:section}} não está fechada: termine-a com {{/:section}}.',
    'template-unexpected' => '{{/:section}} fecha uma secção que nunca foi aberta.',
    'template-tag' => 'A etiqueta {{:tag não está fechada: termine-a com }}.',
    'template-name' => '{{:name}} não é um nome de variável: minúsculas, dígitos e _, no máximo um ponto.',
    'template-partial' => 'Os parciais ({{>name}}) não estão disponíveis num modelo de mensagem.',
    'template-delimiters' => 'Mudar os delimitadores ({{=<% %>=}}) não está disponível num modelo de mensagem.',
    'template-pragma' => 'Pragmas e blocos ({{%…}}, {{$…}}) não estão disponíveis num modelo de mensagem.',
    'template-cdata' => 'Uma variável não pode ficar dentro de um bloco CDATA literal: {{:name}} seria escapada duas vezes. Remova o CDATA à volta dela e use a variável _cdata, por exemplo {{description_cdata}}.',
];
