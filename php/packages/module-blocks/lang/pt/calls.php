<?php

declare(strict_types=1);

return [
    'kind' => 'Um tipo é um bloco ou um componente.',
    'kind-in-use' => 'O bloco está em páginas e não pode virar um componente. Páginas: :count.',
    'unknown-call' => '“:type” não é um tipo de bloco: a chamada não imprime nada no site.',
    'dynamic-call' => 'O tipo chamado não está escrito literalmente: publicá-lo não vai verificar este template.',
    'delete-used-by' => 'Outros tipos chamam este, e os templates deles ficariam com um buraco. Remova as chamadas primeiro.',
    'delete-used-by-one' => 'Chamado por “:title” (:slug)',
    'publish-cycle' => 'Os tipos se chamam em círculo: :path.',
    'publish-breaks-parent' => 'Quebra “:parent” no exemplo dele: :reason',
    'publish-breaks-parent-on' => 'Quebra “:parent” em “:entity”: :reason',
    'publish-breaks-declared' => 'Quebra o lugar de onde o módulo :module o chama: :reason',
    'customise-exists' => 'Já existe um tipo com este identificador.',
    'customised-from' => 'De :view',
];
