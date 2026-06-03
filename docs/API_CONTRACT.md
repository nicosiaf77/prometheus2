# Contratto API

Questo file definisce l'accordo operativo tra backend e frontend.

Prima di implementare una nuova schermata o una nuova funzione, backend e frontend devono concordare qui rotte, payload, risposte ed errori.

Lo stato operativo di cosa e gia pronto per il frontend e cosa resta da sviluppare e riepilogato in `docs/FRONTEND_BACKEND_READINESS.md`.

La specifica machine-readable di riferimento e in `docs/openapi.yaml`.

## Stato

Backend riallineato come API pura. Il backend non espone pagine operative HTML; eventuali HTML/PHP di validazione stanno solo in `backend/tests`.

## Convenzioni

- Formato dati: JSON, salvo endpoint di export file dichiarati (`CSV`, `XLS`, `PDF`).
- Date: `YYYY-MM-DD`.
- Orari: `HH:MM`.
- Importi: numeri decimali con due cifre.
- Errori: risposta JSON con `ok: false`, `error`, `code` e, se presenti, `errors` per campo.
- CSRF: per le chiamate `POST` e `PUT`, leggere prima `GET /csrf-token` e inviare `_csrf_token`.
- Login: dopo 5 tentativi falliti negli ultimi 15 minuti per stesso identificativo/IP, il backend rallenta e nega temporaneamente nuovi tentativi.

## Rotte previste

### Autenticazione

```text
GET /login
GET /csrf-token
GET /me
POST /login
POST /logout
POST /profile/change-password
```

`GET /login` restituisce informazioni API, non una pagina HTML.

`POST /login` accetta `identifier`, `password`, `_csrf_token` e restituisce utente connesso.

`GET /me` restituisce l'utente autenticato corrente; se la sessione non e valida restituisce `401`.

`POST /profile/change-password` consente il cambio password self-service; richiede `current_password`, `password`, `_csrf_token`. Permessi: tutti i ruoli autenticati.

### Dashboard

```text
GET /dashboard
```

Scopo: restituire utente connesso e riepiloghi backend.

### Controlli

```text
GET /controls
GET /controls/create
POST /controls
GET /controls/{control}
GET /controls/{control}/edit
GET /controls/{control}/versions
PUT /controls/{control}
POST /controls/{control}/validate
POST /controls/{control}/annul
GET /controls/{control}/pdf
```

`POST /controls` crea un controllo in stato `bozza`, assegna numero registro progressivo per anno, collega evento/categorie/agenti e genera hash/versione iniziale. Permessi: amministratore, responsabile ufficio, operatore.

`GET /controls/{control}` restituisce dettaglio controllo, dati cifrati decifrati, categorie, agenti, hash, versioni e azioni disponibili. Permessi: utenti autenticati.

`GET /controls/{control}/edit` restituisce controllo + metadati per il form di modifica. Blocca i controlli annullati e, per gli operatori, i controlli gia validati.

`GET /controls/{control}/versions` restituisce la cronologia versioni/hash-chain del controllo.

`PUT /controls/{control}` modifica un controllo esistente e genera sempre una nuova versione nella hash-chain. `change_reason` e obbligatorio. Operatore: solo controlli in bozza. Amministratore e responsabile: anche controlli validati. Controlli annullati: non modificabili.

`GET /controls` accetta filtri query: `registry_number`, `registry_year`, `date_from`, `date_to`, `has_event`, `event_name`, `business_name`, `business_location`, `category_id`, `agent_id`, `outcome`, `status`, `sanction_presence`.

Parametri paginazione e ordinamento:

```text
page
per_page
sort
direction
```

`sort` supporta: `registry_number`, `registry_year`, `control_date`, `business_name`, `business_location`, `outcome`, `status`, `total_sanction_amount`, `created_at`.

Risposta lista:

```json
{
  "ok": true,
  "filters": {},
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 0,
    "last_page": 1,
    "sort": "control_date",
    "direction": "desc"
  }
}
```

`POST /controls/{control}/validate` valida un controllo in bozza e genera nuova versione hash-chain. Permessi: amministratore, responsabile ufficio.

`POST /controls/{control}/annul` annulla logicamente un controllo con motivo obbligatorio e genera nuova versione hash-chain. Permessi: amministratore, responsabile ufficio.

`GET /controls/{control}/pdf` esporta la scheda singola del controllo in formato `PDF`. Permessi: amministratore, responsabile ufficio.

### Tabelle di supporto

```text
GET /events
POST /events
GET /events/{event}
PUT /events/{event}
GET /activity-categories
POST /activity-categories
GET /activity-categories/{category}
PUT /activity-categories/{category}
POST /activity-categories/{category}/deactivate
GET /agents
POST /agents
GET /agents/{agent}
PUT /agents/{agent}
POST /agents/{agent}/deactivate
```

`POST /events` crea un evento semplice con campo `name`. Permessi: amministratore, responsabile ufficio, operatore.

`PUT /events/{event}` rinomina un evento. Permessi: amministratore, responsabile ufficio.

`POST /activity-categories` crea una categoria. `PUT /activity-categories/{category}` la aggiorna. `POST /activity-categories/{category}/deactivate` la disattiva logicamente. Permessi: amministratore.

`POST /agents` crea un agente con `surname`, `name`, `rank`, `office`. `PUT /agents/{agent}` lo aggiorna. `POST /agents/{agent}/deactivate` lo disattiva logicamente. Permessi: amministratore, responsabile ufficio.

### Report e statistiche

```text
GET /statistics
GET /reports
GET /reports/controls.csv
GET /reports/controls.xls
GET /reports/controls.pdf
GET /reports/statistics.pdf
```

`GET /statistics` accetta filtri query `year`, `month`, `date_from`, `date_to`, `outcome`, `event_id`, `category_id` e restituisce aggregati JSON backend.

`GET /reports` restituisce il catalogo export disponibili.

`GET /reports/controls.csv` esporta CSV controlli con gli stessi filtri principali di `GET /controls`, registra tabella `exports` e audit log. Permessi: amministratore, responsabile ufficio.

`GET /reports/controls.xls` esporta controlli in formato `XLS` (SpreadsheetML XML), compatibile con Microsoft Excel e LibreOffice Calc. Permessi: amministratore, responsabile ufficio.

`GET /reports/controls.pdf` esporta l'elenco controlli in formato `PDF` (max 500 record). Permessi: amministratore, responsabile ufficio.

`GET /reports/statistics.pdf` esporta le statistiche correnti in formato `PDF`. Permessi: amministratore, responsabile ufficio.

### Amministrazione

```text
GET /users
POST /users
GET /users/{user}
PUT /users/{user}
POST /users/{user}/deactivate
POST /users/{user}/activate
POST /users/{user}/change-password
GET /audit-logs
GET /backup
POST /backup
GET /integrity-check
POST /integrity-check
```

`GET /users`, `POST /users`, `GET /users/{user}`, `PUT /users/{user}`, `POST /users/{user}/deactivate`, `POST /users/{user}/activate` e `POST /users/{user}/change-password` gestiscono utenti applicativi. Permessi: amministratore.

`POST /users/{user}/change-password` consente all'amministratore di impostare una nuova password senza chiedere quella attuale.

`GET /audit-logs` restituisce le ultime operazioni registrate con filtri e paginazione. Permessi: amministratore, responsabile ufficio.

`POST /backup` crea dump SQL locale in `backend/storage/backups`, registra SHA-256 in tabella `backups` e audit log. Permessi: amministratore.

`POST /integrity-check` verifica catena versioni/hash dei controlli e registra audit log. Permessi: amministratore, responsabile ufficio.

## Test backend

Gli strumenti di validazione manuale e automatica stanno in:

```text
backend/tests
```

Questa cartella puo contenere PHP, HTML, CSS e JavaScript solo per testare le API. Non e codice frontend di prodotto.

## Regola di modifica

Ogni variazione a questo file deve essere approvata da entrambi i programmatori, perche impatta sia backend sia frontend.
