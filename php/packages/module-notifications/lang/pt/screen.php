<?php

declare(strict_types=1);

return [
    'name' => 'Nome',
    'name-help' => 'Apenas para administradores.',
    'provider' => 'Fornecedor',
    'provider-help' => 'Escolhe-se uma vez: um canal de outro tipo é um novo canal.',
    'event' => 'Para o evento',
    'event-help' => 'Com um evento, o editor sugere e verifica as suas variáveis, e só esse evento oferece o canal. Sem evento, o canal serve qualquer evento.',
    'any-event' => 'Qualquer evento',
    'enabled' => 'Ativo',
    'enabled-help' => 'Um canal desativado não envia nada além de testes.',
    'connection' => 'Ligação',
    'message' => 'Mensagem',
    'subject' => 'Assunto',
    'subject-help' => 'Uma linha; variáveis como {{form}} são substituídas.',
    'template' => 'Modelo',
    'template-help' => 'Variáveis entre chavetas duplas: {{form}}. Basta um idioma; uma mensagem num idioma que falta no modelo usa o idioma principal do site.',
    'history' => 'Histórico',
    'token-help' => 'Do @BotFather. Guardado encriptado e nunca mais mostrado: escreva um novo para o substituir.',
    'chats' => 'Chats',
    'chats-help' => 'Ids de chat, ou «Encontrar chats» depois de escrever ao bot. Um destinatário do evento é o endereço que o evento traz.',
    'mode-help' => 'O e-mail do site é o configurado para todo o site.',
    'from-help' => 'Vazio — o remetente do site.',
    'emails' => 'Destinatários',
    'emails-help' => 'Administradores, endereços ou o endereço que o evento traz — o visitante que preencheu o formulário.',
    'credentials' => 'Segredos',
    'options' => 'Definições',
    'recipients' => 'Destinatários',
];
