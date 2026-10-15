<?php

declare(strict_types=1);

return [
    'agent-name' => 'Dai un nome all’agente: il nome della sessione è ciò che registrano la fase e il registro.',
    'project-archived' => 'Il progetto :project è archiviato: nessuna nuova attività e niente viene assegnato.',
    'task-closed' => 'L’attività è :status e non accetta altre modifiche.',
    'task-started' => 'Il lavoro sull’attività è iniziato; possono cambiare solo la priorità e le fasi che nessuno ha preso. Non: :fields.',
    'task-not-draft' => 'Solo una bozza può essere messa in lavorazione; questa attività è :status.',
    'task-no-stages' => 'Un’attività senza fasi non ha nulla da assegnare. Aggiungi almeno una fase.',
    'task-deleted' => 'L’attività è eliminata.',
    'person-only' => 'Solo una persona può rispondere alla domanda di un agente.',
    'stage-elsewhere' => 'Una fase indicata qui appartiene a un’altra attività o è indicata due volte.',
    'last-stage' => 'È l’ultima fase rimasta. Annulla invece l’attività.',
    'stage-transition' => 'Una fase non può passare da :from a :to.',
    'stage-not' => 'Per questo la fase deve essere :status; è :actual.',
    'not-holder' => 'La fase è di :assignee: solo quell’agente ci lavora.',
    'human-review' => 'Questa fase la controlla una persona: un agente non può né accettarla né rimandarla indietro.',
    'own-work' => 'Un agente non controlla il proprio lavoro.',
    'text-required' => 'Il campo :what non può essere vuoto.',
    'stage-missing' => 'Una volta iniziato il lavoro, tutte le fasi restano nell’elenco; una fase che non serve si annulla. Mancano: :stages.',
    'stage-new-at-end' => 'Le nuove fasi vanno in fondo.',
    'stage-started-order' => 'Una fase iniziata o annullata resta al suo posto.',
    'stage-started' => 'La fase :stage è :status e non può più essere modificata.',
    'notify-not-configured' => 'Le notifiche non sono configurate: aggiungi il token del bot e almeno una chat, poi salva.',
];
