<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Chaves',
    'issue' => 'Emitir uma chave',
    'issue-short' => 'Emitir',
    'lead' => 'Uma chave permite que um programa — um vigia da fila, uma importação noturna — trabalhe neste painel sem navegador. Ela age como o administrador que escolher e nunca pode fazer mais do que ele. Uma chave é um programa, não uma pessoa: o que um módulo reserva a uma pessoa, como uma etapa verificada à mão, ela não pode fazer.',
    'name' => 'Nome',
    'name-hint' => 'O que é o programa, para que a lista o diga: «vigia da fila», «importação noturna».',
    'who' => 'Age como',
    'who-hint' => 'Tudo o que a chave altera é feito em nome deste administrador, dentro das suas permissões.',
    'allowed' => 'O que é permitido',
    'everything' => 'Tudo o que o administrador pode fazer',
    'chosen' => 'Apenas os scopes escolhidos abaixo',
    'scopes' => 'Scopes',
    'scopes-hint' => 'Um módulo instalado depois fica fora de alcance até que uma nova chave o nomeie.',
    'scopes-required' => 'Escolha pelo menos um scope.',
    'lifetime' => 'Validade',
    'lifetime-hint' => 'Uma chave não pode ser prorrogada: emita uma nova antes que esta expire e revogue a antiga.',
    'days-30' => '30 dias',
    'days-90' => '90 dias',
    'days-365' => '1 ano',
    'days-1095' => '3 anos',
    'days-3650' => '10 anos',
    'issued-title' => 'Copie a chave agora',
    'issued-text' => 'Esta é a única vez que a chave é mostrada. Ninguém a poderá ver de novo — nem aqui, nem no servidor. Se a perder, revogue-a e emita outra.',
    'secret' => 'Chave',
    'copy' => 'Copiar',
    'copied' => 'Copiada',
    'copy-failed' => 'Não foi possível aceder à área de transferência. Selecione a chave e copie-a à mão.',
    'address' => 'Endereço do servidor',
    'header-hint' => 'O programa envia a chave num cabeçalho: Authorization: Bearer <chave>.',
    'done' => 'Concluído',
    'cancel' => 'Cancelar',
    'expires' => 'Expira',
    'until' => 'até',
    'last-used' => 'Último uso',
    'never-used' => 'Nunca',
    'issued-by' => 'Emitida por :name',
    'issued-console' => 'Emitida num terminal',
    'full' => 'Tudo o que :name pode fazer',
    'deleted' => 'Administrador n.º :id',
    'revoke' => 'Revogar',
    'revoked' => 'Revogada',
    'expired' => 'Expirada',
    'confirm-title' => 'Revogar «:name»?',
    'confirm-text' => 'O programa que a usa é recusado na próxima chamada. Uma chave revogada não pode ser reativada.',
    'confirm' => 'Revogar',
    'revoked-done' => '«:name» foi revogada',
    'empty' => 'Nenhuma chave foi emitida',
    'lead-mine' => 'Uma chave permite que um programa — um vigia de fila, uma importação noturna — trabalhe neste painel como você, sem navegador. Nunca pode mais do que você, e só nos escopos escolhidos.',
    'empty-mine' => 'Você ainda não tem chaves',

];
