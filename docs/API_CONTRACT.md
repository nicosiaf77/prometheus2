# Contratto API

Questo file definisce l'accordo operativo tra backend e frontend.

Prima di implementare una nuova schermata o una nuova funzione, backend e frontend devono concordare qui rotte, payload, risposte ed errori.

## Stato

Fase iniziale. Le API definitive saranno definite step by step.

## Convenzioni

- Formato dati: JSON.
- Date: `YYYY-MM-DD`.
- Orari: `HH:MM`.
- Importi: numeri decimali con due cifre.
- Errori: risposta JSON con `message` e, se presenti, `errors`.

## Rotte previste

### Dashboard

```text
GET /dashboard
```

Scopo: mostrare area iniziale e riepiloghi.

### Controlli

```text
GET /controls
GET /controls/create
POST /controls
GET /controls/{control}
POST /controls/{control}/validate
POST /controls/{control}/annul
```

`POST /controls` crea un controllo in stato `bozza`, assegna numero registro progressivo per anno, collega evento/categorie/agenti e genera hash/versione iniziale. Permessi: amministratore, responsabile ufficio, operatore.

`GET /controls/{control}` mostra dettaglio controllo, dati cifrati decifrati, categorie, agenti, hash e versioni. Permessi: utenti autenticati.

`GET /controls` accetta filtri query: `registry_number`, `registry_year`, `date_from`, `date_to`, `has_event`, `event_name`, `business_name`, `business_location`, `category_id`, `agent_id`, `outcome`, `status`, `sanction_presence`.

`POST /controls/{control}/validate` valida un controllo in bozza e genera nuova versione hash-chain. Permessi: amministratore, responsabile ufficio.

`POST /controls/{control}/annul` annulla logicamente un controllo con motivo obbligatorio e genera nuova versione hash-chain. Permessi: amministratore, responsabile ufficio.

### Tabelle di supporto

```text
GET /events
GET /activity-categories
GET /agents
POST /events
POST /agents
```

`POST /events` crea un evento semplice con campo `name`. Permessi: amministratore, responsabile ufficio, operatore.

`POST /agents` crea un agente con `surname`, `name`, `rank`, `office`. Permessi: amministratore, responsabile ufficio.

### Report e statistiche

```text
GET /statistics
GET /reports
```

`GET /statistics` accetta filtri query `year`, `month`, `date_from`, `date_to`, `outcome` e restituisce dashboard HTML con aggregati backend.

### Amministrazione

```text
GET /users
GET /audit-logs
GET /backup
POST /backup
GET /integrity-check
POST /integrity-check
```

## Regola di modifica

Ogni variazione a questo file deve essere approvata da entrambi i programmatori, perche impatta sia backend sia frontend.
