<?php

declare(strict_types=1);

return [
    'refused' => 'Não foi possível enviar o formulário. Tente novamente daqui a pouco.',
    'too-many' => 'Demasiados envios a partir deste endereço. Tente novamente dentro de um minuto.',
    'form-has-submissions' => 'O formulário «:form» tem envios, por isso pode ser desativado mas não eliminado.',
    'status-in-use' => 'Ainda há envios no estado «:status», por isso não pode ser eliminado.',
    'no-statuses' => 'Não há nenhum estado para dar a um envio novo. Execute as migrações.',
    'file-missing' => 'Este ficheiro já não está aqui.',

    'slug-shape' => 'Um endereço leva minúsculas, algarismos e hífenes: «contact-us».',
    'field-name-shape' => 'Um nome começa por uma letra; depois letras, algarismos, hífenes e sublinhados.',
    'recipient-shape' => 'O destinatário :entry não é um administrador nem um endereço de e-mail.',
    'recipient-unknown' => 'O destinatário :entry indica um administrador que não existe.',
    'choices-required' => 'Um campo de lista precisa de pelo menos uma opção com um valor.',
    'email-field' => 'O campo de resposta tem de ser um campo de e-mail deste formulário.',
    'nobody-to-notify' => 'O formulário “:form” não indica a quem escrever.',
];
