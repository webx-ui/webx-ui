<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'Entradas nela: :count. Mova-as primeiro para outra categoria.',
    'slug-shape' => 'Letras, algarismos e hífenes isolados entre eles.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Nova categoria',
    'empty' => 'Ainda não há categorias.',
    'empty-help' => 'Uma categoria agrupa entradas. Uma entrada pode estar em várias.',
    'order' => 'A ordem aqui é a ordem no site',
    'hidden' => 'Oculta no site',
    'no-address' => 'Sem endereço neste idioma',
    'count' => 'Entradas: :count',
    'show-items' => 'Ver as suas entradas',
    'edit' => 'Editar',
    'open-on-site' => 'Abrir no site',
    'delete' => 'Eliminar',
    'delete-blocked' => 'Enquanto tiver entradas não pode ser eliminada — mova-as primeiro.',
    'delete-title' => 'Eliminar «:name»?',
    'delete-text' => 'Vai para a lixeira e sai do site, e o seu endereço fica livre.',
    'deleted' => 'A categoria está na lixeira.',
    'cancel' => 'Cancelar',
    'create' => 'Criar',
    'save' => 'Guardar',
    'saved' => 'Guardado.',
    'save-failed' => 'Não guardado — veja os campos assinalados.',
    'reorder-failed' => 'A nova ordem não foi guardada.',
    'field-title' => 'Nome',
    'field-slug' => 'Endereço',
    'address-moving' => 'O endereço vai mudar. O antigo continua a funcionar e leva ao novo.',
    'untitled' => 'Sem título',
    'trail' => 'Onde está',
    'leave-title' => 'Sair sem guardar?',
    'leave-text' => 'O que foi alterado aqui desde a última gravação perde-se.',
    'leave' => 'Sair',
    'field-main' => 'Principal',
    'field-add' => 'Adicionar uma categoria',
    'field-remove' => 'Retirar desta categoria',
    'field-empty' => 'Ainda em nenhuma categoria.',
    'field-none-left' => 'Todas as categorias já estão escolhidas.',
    'order-all' => 'Arraste para mudar a ordem no site.',
    'order-category' => 'Arraste para mudar a ordem dentro desta categoria. O resto da lista mantém a sua.',
    'order-locked' => 'Limpe a pesquisa e os filtros para mudar a ordem — só se arrasta a lista inteira ou uma categoria.',
    'unknown' => 'Uma das categorias escolhidas já não existe.',
];
