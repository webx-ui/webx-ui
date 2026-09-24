<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'Entradas en ella: :count. Muévelas primero a otra categoría.',
    'slug-shape' => 'Letras, dígitos y guiones sueltos entre ellos.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Nueva categoría',
    'empty' => 'Aún no hay categorías.',
    'empty-help' => 'Una categoría agrupa entradas. Una entrada puede estar en varias.',
    'order' => 'El orden aquí es el orden en el sitio',
    'hidden' => 'Oculta en el sitio',
    'no-address' => 'Sin dirección en este idioma',
    'count' => 'Entradas: :count',
    'show-items' => 'Ver sus entradas',
    'edit' => 'Editar',
    'open-on-site' => 'Abrir en el sitio',
    'delete' => 'Eliminar',
    'delete-blocked' => 'Mientras contenga entradas no se puede eliminar: muévalas primero.',
    'delete-title' => '¿Eliminar «:name»?',
    'delete-text' => 'Irá a la papelera y desaparecerá del sitio, y su dirección quedará libre.',
    'deleted' => 'La categoría está en la papelera.',
    'cancel' => 'Cancelar',
    'create' => 'Crear',
    'save' => 'Guardar',
    'saved' => 'Guardado.',
    'save-failed' => 'No se guardó: revise los campos marcados.',
    'reorder-failed' => 'El nuevo orden no se guardó.',
    'field-title' => 'Nombre',
    'field-slug' => 'Dirección',
    'address-moving' => 'La dirección cambia. La antigua sigue funcionando y lleva a la nueva.',
    'untitled' => 'Sin título',
    'trail' => 'Dónde está',
    'leave-title' => '¿Salir sin guardar?',
    'leave-text' => 'Lo que se cambió aquí desde el último guardado se perderá.',
    'leave' => 'Salir',
    'field-main' => 'Principal',
    'field-add' => 'Añadir una categoría',
    'field-remove' => 'Quitar de esta categoría',
    'field-empty' => 'Aún en ninguna categoría.',
    'field-none-left' => 'Ya están elegidas todas las categorías.',
    'order-all' => 'Arrastre para cambiar el orden en el sitio.',
    'order-category' => 'Arrastre para cambiar el orden dentro de esta categoría. El resto de la lista mantiene el suyo.',
    'order-locked' => 'Borre la búsqueda y los filtros para cambiar el orden: solo se arrastra la lista entera o una categoría.',
    'unknown' => 'Una de las categorías elegidas ya no existe.',
];
