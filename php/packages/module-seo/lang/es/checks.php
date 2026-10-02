<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Redirecciones que llevan a redirecciones',
            'found' => 'Una redirección de la tabla SEO apunta a una dirección que a su vez se redirige.',
            'why' => 'Cada salto es un viaje de ida y vuelta más para el visitante, y los buscadores dejan de seguirlos tras unos pocos.',
            'fix' => 'Apunta la primera redirección directamente a la última dirección: el botón de corrección lo hace con cada redirección exacta de la cadena.',
        ],
        'title_duplicate' => [
            'title' => 'El mismo título en varias fichas SEO',
            'found' => 'Varias entidades tienen escrito el mismo título en su ficha SEO, en el mismo idioma.',
            'why' => 'Dos páginas que se llaman igual compiten entre sí en la búsqueda, y ninguna parece la respuesta.',
            'fix' => 'Da a cada página un título que diga qué hay en ella y en ningún otro sitio.',
        ],
        'redirect_broken' => [
            'title' => 'Redirecciones a una página rota',
            'found' => 'Una redirección exacta envía a los visitantes a una dirección que respondió con un error durante el rastreo.',
            'why' => 'El visitante que siguió un enlace antiguo acaba en una página de error, y se pierde el peso de la dirección antigua.',
            'fix' => 'Apunta la redirección a una página que exista, o restaura la página.',
        ],
        'rule_dead' => [
            'title' => 'Reglas SEO de direcciones que ya no existen',
            'found' => 'Hay una regla SEO exacta escrita para una dirección que respondió 404 o 410 durante el rastreo.',
            'why' => 'Nada dañino, pero la regla no es para nadie y oculta que la página para la que se escribió ha desaparecido.',
            'fix' => 'Elimina la regla, o añade una redirección desde esa dirección si la página se ha movido.',
        ],
    ],
];
