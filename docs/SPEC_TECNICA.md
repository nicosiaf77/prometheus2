# Specifica tecnica sintetica

## Backend

Il backend usa un framework personale chiamato Prometheus:

- `app/Controllers` per controller;
- `app/Models` per model;
- `app/Services` per logica applicativa;
- `app/Middleware` per controlli trasversali;
- `app/Requests` per validazione input;
- `routes/web.php` per rotte;
- `database/migrations` per schema;
- `database/seeders` per dati iniziali.

Il backend deve restare backend puro: controller e servizi restituiscono JSON o file dati dichiarati, come CSV. Non devono contenere GUI, template HTML o form operativi.

## Test backend

`backend/tests` contiene strumenti PHP/HTML di verifica manuale e smoke test CLI. Questa cartella non e parte del frontend di prodotto.

## Frontend

Interfaccia Bootstrap con:

- barra servizi in alto;
- utente connesso;
- menu laterale compatto;
- area di lavoro centrale;
- pulsanti piccoli e contestuali;
- preferenza per menu a tendina, select e tabelle.

## Sicurezza

- password solo con hash sicuro;
- sessioni protette;
- ruoli applicativi;
- audit log;
- cifratura selettiva dati personali;
- nessuna cancellazione fisica dei controlli;
- versionamento con hash chain.

## Ruoli

- amministratore;
- responsabile ufficio;
- operatore;
- lettore.

Non e' previsto il ruolo di revisore.
