<?php

declare(strict_types=1);

return [
    'empty' => 'La dirección, la pregunta y la respuesta son obligatorias.',
    'question-long' => 'La pregunta tiene más de 1000 caracteres.',
    'foreign-host' => 'La dirección está en otro sitio.',
    'duplicate' => 'Esta pregunta ya está en la página.',
    'redirected' => ':from redirige; se usa su destino :to en su lugar.',
    'unreadable' => 'No se pudo leer el archivo como CSV o XLSX.',
    'has-faq' => 'Esta regla tiene FAQ, y solo una regla para una dirección exacta puede conservarlo. Elimine primero las preguntas.',
    'question-missing' => 'La respuesta no tiene pregunta.',
    'answer-missing' => 'La pregunta no tiene respuesta.',

    // The panel's view of it (§18.5).
    'tab' => 'FAQ',
    'meta' => 'Metaetiquetas',
    'help' => 'Las preguntas de esta página. Van a su marcado FAQPage y a la página allí donde la plantilla muestra el FAQ.',
    'exact-only' => 'Solo una regla para una dirección exacta tiene FAQ.',
    'question' => 'Pregunta',
    'answer' => 'Respuesta',
    'add' => 'Añadir una pregunta',
    'remove' => 'Quitar la pregunta',
    'drag' => 'Mover',
    'none' => 'Aún no hay preguntas.',
    'column' => 'FAQ',
    'with-faq' => 'Solo con FAQ',
    'import' => 'Importar FAQ',
    'export-csv' => 'Exportar FAQ como CSV',
    'export-xlsx' => 'Exportar FAQ como XLSX',
    'import-title' => 'Importar FAQ',
    'import-help' => 'Un archivo CSV o XLSX con las columnas dirección, pregunta y respuesta (HTML o texto), en el idioma de la dirección. Una dirección sin regla exacta recibe una con las metaetiquetas vacías.',
    'mode-replace' => 'Reemplazar el FAQ de las direcciones del archivo',
    'mode-append' => 'Añadir a las preguntas existentes',
    'imported' => 'Importado. Direcciones: :count',
    'result-addresses' => 'Direcciones',
    'result-questions' => 'Preguntas',
    'result-created' => 'Reglas nuevas',
    'result-replaced' => 'Reemplazadas',
    'result-appended' => 'Ampliadas',
    'result-errors' => 'Errores',
];
