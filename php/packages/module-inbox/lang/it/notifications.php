<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Un invio di un modulo',
    // The default letter: a note that answering it writes to whoever filled the form.
    'reply' => 'Rispondi a questa e-mail per rispondere al visitatore: :address.',
    'vars' => [
        'form' => 'Il nome del modulo',
        'form_slug' => 'Lo slug del modulo',
        'submission_id' => 'Il numero dell’invio',
        'submission_url' => 'L’invio nel pannello',
        'created_at' => 'Quando è arrivato',
        'fields' => 'Tutte le risposte, una riga ciascuna: «Etichetta: valore»',
        'answers' => 'Le risposte, come elenco',
        'answer_name' => 'Il nome tecnico del campo',
        'answer_label' => 'L’etichetta del campo',
        'answer_value' => 'La risposta',
        'field' => 'Una risposta, per il nome tecnico del suo campo',
        'files' => 'I nomi dei file allegati',
        'page' => 'La pagina da cui è stato inviato il modulo',
        'locale' => 'La lingua dell’invio',
        'utm' => 'Tag UTM',
        'reply_to' => 'L’e-mail del visitatore, dal campo e-mail del modulo',
    ],
];
