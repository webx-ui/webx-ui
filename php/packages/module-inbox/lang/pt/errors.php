<?php

declare(strict_types=1);

return [
    'refused' => 'Não foi possível enviar o formulário. Tente novamente daqui a pouco.',
    'enclosure' => 'O que foi anexado a este formulário não pode ser enviado com ele. Recarregue a página e tente novamente.',
    'captcha' => 'Confirme que não é um robô e envie o formulário de novo.',
    'captcha-script' => 'Este formulário verifica com JavaScript que não é um robô. Ative-o e envie o formulário de novo.',
    'captcha-unavailable' => 'Não foi possível verificar que não é um robô. Recarregue a página ou experimente outro navegador.',
    'too-many' => 'Demasiados envios a partir deste endereço. Tente novamente dentro de um minuto.',
    'form-has-submissions' => 'O formulário «:form» tem envios, por isso pode ser desativado mas não eliminado.',
    'status-in-use' => 'Ainda há envios no estado «:status», por isso não pode ser eliminado.',
    'no-statuses' => 'Não há nenhum estado para dar a um envio novo. Execute as migrações.',
    'file-missing' => 'Este ficheiro já não está aqui.',

    'slug-empty' => 'Um endereço precisa de letras ou algarismos, por exemplo «contact-us».',
    'field-name-empty' => 'Um nome precisa de letras ou algarismos, por exemplo «phone».',
    'status-key-empty' => 'Uma chave precisa de letras ou algarismos, por exemplo «in-progress».',
    'recipient-shape' => 'O destinatário :entry não é um administrador nem um endereço de e-mail.',
    'recipient-unknown' => 'O destinatário :entry indica um administrador que não existe.',
    'choices-required' => 'Um campo de lista precisa de pelo menos uma opção com um valor.',
    'email-field' => 'O campo de resposta tem de ser um campo de e-mail deste formulário.',
    'no-such-answer' => 'Este envio não tem nenhuma resposta com esse nome.',
    'not-correctable' => 'Um ficheiro ou um consentimento não podem ser corrigidos.',
    'nobody-to-notify' => 'O formulário “:form” não indica a quem escrever.',
    'condition-unknown-field' => 'A condição «:key» de «:field» nomeia «:name», um campo que este formulário não tem (ou que esta alteração remove).',
    'condition-not-choice' => 'A condição «:key» de «:field» nomeia «:name», que não é uma escolha: só uma lista, botões de opção, caixas ou um consentimento acima podem ser condição.',
    'condition-not-before' => 'A condição «:key» de «:field» nomeia «:name», que não está acima. Uma condição nomeia apenas um campo acima.',
    'condition-unknown-value' => 'A condição «:key» de «:field» espera o valor «:value» de «:name», que esse campo não oferece.',
    'field-has-dependents' => 'O campo «:field» é nomeado nas condições de :fields. Altere primeiro essas condições.',
    'step-empty' => 'A etapa :field não tem nenhum campo depois dela. Coloque um campo por baixo ou remova-a.',
    'step-too-many' => 'Um formulário tem no máximo :max etapas.',
    'step-unknown' => 'O formulário mudou desde que a página foi aberta. Recarregue a página.',
    'datetime-range' => 'O dia não tem lugar para nenhum horário: «De» e «Até» como HH:MM, e «De» pelo menos um passo antes de «Até».',
    'datetime-step' => 'O passo dos horários é de 15, 20, 30, 60, 90 ou 120 minutos.',
    'datetime-weekdays' => 'Cada dia da semana uma vez, de 1 (segunda-feira) a 7 (domingo).',
];
