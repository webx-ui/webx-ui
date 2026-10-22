<?php

declare(strict_types=1);

return [
    'type' => 'Una propiedad es de uno de estos tipos: :types.',
    'type-fixed' => 'El tipo de una propiedad no cambia una vez guardada: crea una propiedad nueva y mueve los valores.',
    'code' => 'Letras latinas, dígitos y guiones sueltos, como mucho :max caracteres.',
    'code-taken' => 'Este código ya lo usa en este idioma: :holder.',
    'slug' => 'Letras latinas, dígitos y guiones sueltos.',
    'slug-taken' => 'Otro valor de esta propiedad tiene este slug en este idioma.',
    'color' => 'Un color es #rrggbb.',
    'group' => 'No existe ese grupo.',
    'group-in-use' => 'El grupo contiene propiedades. Propiedades: :count',
    'unknown-property' => 'No existe esa propiedad.',
    'inherited' => '«:property» ya está en el conjunto de «:category», más arriba; una categoría lo hereda.',
    'outside-set' => 'Esta propiedad no está en el conjunto de la categoría principal del producto.',
    'number' => 'Se espera un número.',
    'text' => 'Se espera un texto, o un mapa de idiomas.',
    'one-value' => 'Esta propiedad admite un solo valor.',
    'unknown-value' => 'No existe ese valor de esta propiedad.',
    'unknown-slug' => '«:property» no tiene el valor «:slug». Créalo antes con catalog_property_values_create, o indica el id.',
    'leaves-only' => 'Aquí solo se puede elegir un valor que no tenga nada debajo.',
    'value-in-use' => 'Hay productos con este valor — fusiónalo con otro en su lugar. Productos: :count',
    'values-select' => 'Solo un diccionario tiene valores.',
    'not-tree' => 'Los valores de esta propiedad no son un árbol.',
    'move-into-itself' => 'Un valor no puede ir debajo de sí mismo.',
    'merge-other' => 'Un valor se fusiona con otro valor de la misma propiedad.',
    'merge-descendant' => 'Un valor no puede fusionarse con uno que esté debajo de él.',
    'intervals-number' => 'Solo un número tiene intervalos.',
    'interval-ends' => 'El extremo superior debe estar por encima del inferior.',
];
