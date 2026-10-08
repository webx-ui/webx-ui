<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'Ficheiros da biblioteca em falta no disco',
            'found' => 'A biblioteca de multimédia lista um ficheiro que o disco não tem.',
            'why' => 'Cada página e campo que o usa mostra uma imagem quebrada ou um link de transferência morto.',
            'fix' => 'Carregue o ficheiro de novo na biblioteca de multimédia, ou copie a pasta storage do sítio de onde o site veio.',
        ],
        'heavy' => [
            'title' => 'Imagens demasiado pesadas para uma página',
            'found' => 'Há imagens na biblioteca de multimédia que pesam mais do que o limite.',
            'why' => 'Uma página que mostra uma carrega devagar no telemóvel, e os motores de busca classificam pior as páginas lentas.',
            'fix' => 'Substitua-as por versões mais pequenas: uma foto para uma página raramente precisa de mais de 2000 píxeis de largura nem de pesar mais do que algumas centenas de quilobytes.',
        ],
        'orphan_thumbs' => [
            'title' => 'Pré-visualizações de ficheiros eliminados',
            'found' => 'O disco guarda pastas de pré-visualizações de ficheiros que a biblioteca de multimédia já não tem.',
            'why' => 'Ocupam espaço e nada as mostrará.',
            'fix' => 'Elimine-as com o botão aqui ou com php artisan webx:media:prune-thumbs.',
        ],
    ],
];
