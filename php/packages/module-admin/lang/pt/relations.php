<?php

declare(strict_types=1);

/*
 * A field that points at records of another section (`wx-relations`), and the same choice as a
 * block's filter ("only related to" in `wx-collection`). The keys are the panel's: the English
 * here is what `messages.ts` carries.
 */

return [
    'field-add' => 'Adicionar…',
    'field-searching' => 'A pesquisar…',
    'field-nothing' => 'Nada encontrado.',
    'field-empty' => 'Ainda nada escolhido.',
    'field-remove' => 'Remover',
    'field-drag' => 'Arraste para mudar a ordem',
    'field-hidden' => 'Não está no site',
    'field-trashed' => 'No lixo',
    'field-missing' => 'Não encontrado',
    'field-full' => 'Não é possível escolher mais de :max.',
    'field-forbidden' => 'Não pode ver estes registos, por isso a escolha não pode ser alterada aqui.',
    'collection-related' => 'Apenas relacionados com',
    'collection-related-to' => 'Apenas relacionados com «:target»',
    'collection-related-type' => 'Que secção',
    'collection-related-any' => 'Sem restrição: todos os registos, relacionados ou não.',
    'collection-related-current' => 'O registo da página onde está',
    'collection-related-current-hint' => 'Na página de um registo de «:target», o bloco mostra o que está relacionado com ele; em qualquer outra página não mostra nada.',
];
