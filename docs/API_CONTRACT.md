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
GET /controls/{control}/edit
PUT /controls/{control}
POST /controls/{control}/validate
POST /controls/{control}/annul
```

### Tabelle di supporto

```text
GET /events
GET /activity-categories
GET /agents
```

### Report e statistiche

```text
GET /statistics
GET /reports
```

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
