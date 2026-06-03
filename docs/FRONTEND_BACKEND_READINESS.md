# Readiness backend per frontend — Prometheus2

Documento operativo per coordinare `nicosiaf77` (backend) e `nicosiagiuseppe85` (frontend).
Aggiornato al 2026-06-03 — backend completato, tutte le API pronte.

---

## Stato sintetico

Il backend è un'API pura JSON/file. Non contiene GUI operativa in `backend/app`.
HTML/PHP di test è confinato in `backend/tests`.

Tutte le API sono pronte per il frontend. Vedi `docs/FRONTEND_GUIDE.md` per
la guida di integrazione JavaScript e `docs/openapi.yaml` per la specifica completa.

---

## Pronto per uso frontend

| Area | Endpoint | Note frontend |
|---|---|---|
| CSRF | `GET /csrf-token` | Necessario prima di ogni POST/PUT. |
| Auth info | `GET /login` | Endpoint diagnostico, non HTML. |
| Utente corrente | `GET /me` | Recupera ruolo e sessione al refresh. |
| Login | `POST /login` | Campi: `identifier`, `password`, `_csrf_token`. Cookie sessione. |
| Logout | `POST /logout` | Richiede `_csrf_token`. |
| Cambio password (self) | `POST /profile/change-password` | Verifica password attuale. |
| Dashboard | `GET /dashboard` | Utente + riepilogo anno corrente. |
| Lista controlli | `GET /controls` | Filtri, paginazione, ordinamento. Risposta include `control_time`, `has_event`, `primary_category_name`, `agents_names`. |
| Metadati form nuovo | `GET /controls/create` | Categorie, agenti, eventi, default. |
| Crea controllo | `POST /controls` | Bozza, numero registro, hash chain v1. |
| Dettaglio controllo | `GET /controls/{id}` | Dati decifrati, `primary_category`, `secondary_categories`, `agents`, `versions`, `available_actions`. |
| Form modifica | `GET /controls/{id}/edit` | Controllo + metadati per il form. |
| Modifica controllo | `PUT /controls/{id}` | `change_reason` obbligatorio. |
| Valida controllo | `POST /controls/{id}/validate` | Admin e responsabile. |
| Annulla controllo | `POST /controls/{id}/annul` | `annulment_reason` obbligatorio. |
| Versioni controllo | `GET /controls/{id}/versions` | Hash chain completa. |
| PDF scheda | `GET /controls/{id}/pdf` | Admin e responsabile. |
| Lista eventi | `GET /events` | — |
| Crea evento | `POST /events` | Nome in maiuscolo, upsert automatico. |
| Dettaglio evento | `GET /events/{id}` | — |
| Rinomina evento | `PUT /events/{id}` | Admin e responsabile. |
| Lista agenti | `GET /agents` | Attivi e disattivati. |
| Crea agente | `POST /agents` | Admin e responsabile. |
| Dettaglio agente | `GET /agents/{id}` | — |
| Aggiorna agente | `PUT /agents/{id}` | Admin e responsabile. |
| Disattiva agente | `POST /agents/{id}/deactivate` | — |
| Lista categorie | `GET /activity-categories` | Tutte. `/controls/create` restituisce solo attive. |
| Crea categoria | `POST /activity-categories` | Solo admin. |
| Aggiorna categoria | `PUT /activity-categories/{id}` | Solo admin. |
| Disattiva categoria | `POST /activity-categories/{id}/deactivate` | Solo admin. |
| Statistiche | `GET /statistics` | Filtri: year, month, date_from/to, outcome, event_id, category_id. |
| Catalogo report | `GET /reports` | Lista export disponibili. |
| Export CSV | `GET /reports/controls.csv` | BOM UTF-8, compatibile Excel Windows. |
| Export Excel | `GET /reports/controls.xls` | SpreadsheetML, compatibile Excel e LibreOffice. |
| Export PDF elenco | `GET /reports/controls.pdf` | Max 500 record. |
| Export PDF statistiche | `GET /reports/statistics.pdf` | Stessi filtri di `/statistics`. |
| Lista utenti | `GET /users` | Solo admin. |
| Crea utente | `POST /users` | Solo admin. |
| Dettaglio utente | `GET /users/{id}` | Solo admin. |
| Aggiorna utente | `PUT /users/{id}` | Solo admin. |
| Disattiva utente | `POST /users/{id}/deactivate` | Solo admin. |
| Riattiva utente | `POST /users/{id}/activate` | Solo admin. |
| Cambia password (admin) | `POST /users/{id}/change-password` | Solo admin, nessuna verifica password attuale. |
| Audit log | `GET /audit-logs` | Paginato, filtri: action, entity_type, date_from/to. |
| Backup lista | `GET /backup` | Solo admin. |
| Crea backup | `POST /backup` | Solo admin, SHA-256 file. |
| Integrità info | `GET /integrity-check` | Admin e responsabile. |
| Verifica integrità | `POST /integrity-check` | Admin e responsabile. |

---

## Requisiti di configurazione per il frontend

**CORS:** comunicare la porta del dev server a `nicosiaf77` per aggiornare `.env`:

```env
CORS_ALLOWED_ORIGINS=http://localhost:5173
CORS_ALLOW_CREDENTIALS=true
```

**Credenziali fetch:** ogni richiesta deve includere `credentials: 'include'` per
inviare i cookie di sessione.

**CSRF:** ogni POST e PUT deve includere `_csrf_token` ottenuto da `GET /csrf-token`.

**Export file:** gli endpoint di export restituiscono file binari. Usare `Blob` e
`URL.createObjectURL` in JavaScript. Vedere `docs/FRONTEND_GUIDE.md`.

---

## Da sviluppare — solo in produzione (non codice)

| Voce | Nota |
|---|---|
| HTTPS con certificato TLS | Da configurare su Apache/Nginx |
| Cookie `Secure` e `SameSite` | Da configurare nel web server |
| `APP_DEBUG=false` | Da impostare nel `.env` produzione |
| `storage/` fuori dalla web root | Da configurare sul server |

---

## Schermate realizzabili subito

Tutte le schermate previste dalla spec sono realizzabili:

1. Login
2. Dashboard riepilogo
3. Lista controlli con filtri e paginazione
4. Dettaglio controllo con versioni
5. Nuovo controllo
6. Modifica controllo
7. Validazione e annullamento per ruoli autorizzati
8. Tabelle eventi, agenti, categorie
9. Statistiche con grafici
10. Export CSV, Excel, PDF
11. Audit log con filtri
12. Gestione utenti (solo admin)
13. Backup (solo admin)
14. Profilo con cambio password

---

## Riferimenti

- `docs/FRONTEND_GUIDE.md` — client JavaScript completo, esempi pratici, mapping schermata→endpoint
- `docs/openapi.yaml` — specifica OpenAPI 3.0 completa di tutti i 45 endpoint
- `docs/FROM_BACKEND_TO_FRONTEND_RESOURCES.md` — documentazione dettagliata ogni endpoint
- `docs/API_CONTRACT.md` — convenzioni generali e contratto condiviso
