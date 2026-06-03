# Urgenze backend — Prometheus2

Documento di tracciamento delle criticità emerse durante l'analisi del codice.
Aggiornato al 2026-06-03.

---

## Stato riepilogativo

Tutte le urgenze originali sono state risolte. Di seguito lo storico con stato aggiornato.

---

## Urgenza 1 — Modifica controlli ✅ RISOLTA

**Endpoint implementato:** `PUT /controls/{control}`

Funzionalità coperte:
- permessi: amministratore e responsabile possono modificare qualsiasi controllo non annullato;
  operatore può modificare solo controlli in stato `bozza`;
- `change_reason` obbligatorio;
- nuova versione in `control_versions` con hash chain aggiornata;
- audit log `CONTROL_UPDATED`;
- validazione strutturata con errori per campo.

Aggiunto anche `GET /controls/{control}/edit` che restituisce il controllo
pre-caricato + metadati per il form (categorie, agenti, eventi).

---

## Urgenza 2 — Errori API strutturati ✅ RISOLTA

Tutte le POST e PUT restituiscono errori con shape standard:

```json
{ "ok": false, "error": "...", "code": 422, "errors": { "campo": ["msg"] } }
```

Coperto su: `/controls`, `/controls/{id}` (update/validate/annul), `/users`,
`/agents`, `/events`, `/activity-categories`, `/profile/change-password`.

---

## Urgenza 3 — OpenAPI completo ✅ RISOLTA

`docs/openapi.yaml` riscritto completamente (600+ righe):
- tutti i 45 endpoint documentati;
- schema request/response dettagliati;
- codici HTTP per ogni risposta;
- permessi indicati nelle descrizioni;
- parametri query, path e body.

Aggiunto anche `docs/FRONTEND_GUIDE.md` con client JavaScript completo,
esempi fetch, gestione errori, download Blob, helper ruoli.

---

## Urgenza 4 — Model reali o placeholder ✅ RISOLTA

Decisione architetturale presa e implementata:

- I file `app/Models/` contengono costanti tipizzate usate in tutto il codice.
- `Control::STATUS_DRAFT`, `STATUS_VALIDATED`, `STATUS_ANNULLED`
- `Control::OUTCOME_POSITIVE`, `OUTCOME_NEGATIVE`, `OUTCOME_INVESTIGATION`, `OUTCOMES`
- `User::ROLE_ADMIN`, `ROLE_MANAGER`, `ROLE_OPERATOR`, `ROLE_READER`, `ROLES`
- Tutti i Controller e le FormRequest usano queste costanti. Nessuna stringa letterale di stato/ruolo rimasta nel codice applicativo.
- La logica dati vive nei Service: scelta esplicita, documentata.

---

## Urgenza 5 — Script database ✅ PARZIALMENTE RISOLTA

Disponibili e funzionanti:
- `composer migrate` — applica migrazione SQL unica.
- `composer seed` — inserisce categorie iniziali.
- `composer serve` — avvia server di sviluppo.
- `composer test:smoke` — esegue smoke test.
- `composer test:roles` — esegue test permessi per ruolo.
- `php scripts/create_admin.php` — crea utente amministratore.

Ancora mancante (non bloccante per produzione):
- rollback migrazioni;
- stato migrazioni leggibile via CLI;
- comando `fresh` per ambienti di test.

---

## Urgenza 6 — Test accessi per ruolo ✅ RISOLTA

`backend/tests/roles.php`: 100+ test su 4 ruoli × endpoint GET e POST/PUT sensibili.

Copertura:
- GET su tutti gli endpoint principali;
- POST sensibili: `/controls`, `/controls/{id}/validate`, `/controls/{id}/annul`,
  `/agents`, `/activity-categories`, `/events`, `/users`, `/backup`, `/integrity-check`;
- PUT sensibili: `/controls/{id}`, `/agents/{id}`;
- self-service: `/profile/change-password`.

Risultato attuale: **100% OK** (zero fallimenti).

---

## Urgenza 7 — Sicurezza sessioni e deploy ✅ PARZIALMENTE RISOLTA

Implementato nel codice:
- cookie di sessione con `session_regenerate_id()` dopo login;
- CORS configurabile via `CORS_ALLOWED_ORIGINS` nel `.env`;
- `APP_DEBUG` configurabile nel `.env`;
- `APP_KEY` sicura (64 char hex AES-256) nel `.env`;
- backup opzionalmente cifrato AES-256-CBC.

Da completare **solo in produzione** (infrastruttura, non codice):
- HTTPS obbligatorio con certificato TLS;
- cookie `Secure` e `SameSite` da configurare nel web server;
- `APP_DEBUG=false` in `.env` produzione;
- directory `storage/` fuori dalla web root.

Vedi `docs/GDPR_CHECKLIST.md` sezione 8 per la checklist completa.

---

## Urgenza 8 — Export Excel e PDF ✅ RISOLTA

Endpoint implementati e funzionanti:

| Endpoint | Formato | Note |
|---|---|---|
| `GET /reports/controls.csv` | CSV con BOM UTF-8 | Compatibile Excel Windows |
| `GET /reports/controls.xls` | SpreadsheetML (Excel 97-2003) | Nessuna dipendenza esterna |
| `GET /reports/controls.pdf` | PDF puro PHP | Max 500 record, A4 |
| `GET /reports/statistics.pdf` | PDF puro PHP | Tabelle per categoria/agente/evento |
| `GET /controls/{id}/pdf` | PDF puro PHP | Scheda singolo controllo |

Nota: il formato Excel usa SpreadsheetML (`.xls`), compatibile con Microsoft Excel
e LibreOffice Calc senza plugin aggiuntivi. Il formato OOXML (`.xlsx`) non è
implementato per mantenere zero dipendenze esterne.

---

## Criticità post-audit (2026-06-03)

Quattro punti rilevati dall'audit mirato e risolti nello stesso giorno:

1. **roles.php incompleto** → matrice estesa con POST/PUT sensibili; `apiRequest()`
   aggiornata per inviare CSRF e body; regola "autorizzato = 2xx/422". ✅
2. **Naming XLSX** → rotta, metodo e service rinominati da `.xlsx`/`controlsXlsx`
   a `.xls`/`controlsXls`. Allineati route, controller, service, docs. ✅
3. **README credenziali** → `tests/README.md` riscritto con variabili d'ambiente
   corrette, tabella riepilogativa, esempi per tutti e quattro i ruoli. ✅
4. **MD non sincronizzati** → `BACKEND_URGENZE.md` e `FRONTEND_BACKEND_READINESS.md`
   aggiornati allo stato reale. ✅
