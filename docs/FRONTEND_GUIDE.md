# Guida integrazione frontend — Prometheus2

Destinatario: `nicosiagiuseppe85` (responsabile frontend).
Autore: `nicosiaf77` (backend).
Aggiornato: 2026-06-03.

Questo documento è il riferimento pratico per integrare il frontend Bootstrap/JavaScript
con il backend API Prometheus2. Per la descrizione completa degli endpoint leggere
`docs/FROM_BACKEND_TO_FRONTEND_RESOURCES.md`. Per la specifica OpenAPI leggere `docs/openapi.yaml`.

---

## 1. Setup iniziale

### Configurazione ambiente

Il backend in sviluppo gira su `http://localhost:8080`.
La porta del frontend dev server va dichiarata nel `.env` del backend:

```env
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3000
CORS_ALLOW_CREDENTIALS=true
```

Comunicarlo a `nicosiaf77` prima di avviare il frontend in modo che il backend sia
configurato con la porta corretta.

### Client JavaScript base

Crea il file `frontend/src/api/prometheusApi.js` con questa struttura:

```javascript
const API_BASE = import.meta.env.VITE_API_BASE ?? 'http://localhost:8080';

// ── Stato interno ────────────────────────────────────────────────────────────

let _csrfToken = null;

// ── Helper fetch ─────────────────────────────────────────────────────────────

async function apiFetch(method, path, body = null, isBlob = false) {
  const headers = { 'Accept': 'application/json' };
  let bodyContent = null;

  if (body !== null) {
    headers['Content-Type'] = 'application/json';
    bodyContent = JSON.stringify(body);
  }

  const res = await fetch(API_BASE + path, {
    method,
    headers,
    body: bodyContent,
    credentials: 'include',   // OBBLIGATORIO per i cookie di sessione
  });

  if (isBlob) {
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.blob();
  }

  const data = await res.json();

  if (!data.ok) {
    const err = new Error(data.error ?? 'Errore sconosciuto');
    err.code    = data.code ?? res.status;
    err.errors  = data.errors ?? {};
    throw err;
  }

  return data;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

async function getCsrfToken() {
  const data = await apiFetch('GET', '/csrf-token');
  _csrfToken = data.csrf_token;
  return _csrfToken;
}

async function csrf() {
  if (!_csrfToken) await getCsrfToken();
  return _csrfToken;
}

// ── Auth ─────────────────────────────────────────────────────────────────────

async function login(identifier, password) {
  const token = await getCsrfToken();   // sempre fresco al login
  const data  = await apiFetch('POST', '/login', { _csrf_token: token, identifier, password });
  _csrfToken  = null;                   // forza refresh token dopo login
  return data.user;
}

async function logout() {
  const data = await apiFetch('POST', '/logout', { _csrf_token: await csrf() });
  _csrfToken = null;
  return data;
}

async function me() {
  return apiFetch('GET', '/me');
}

// ── Dashboard ─────────────────────────────────────────────────────────────────

async function dashboard() {
  return apiFetch('GET', '/dashboard');
}

// ── Controlli ─────────────────────────────────────────────────────────────────

async function listControls(filters = {}) {
  const params = new URLSearchParams(filters).toString();
  return apiFetch('GET', `/controls${params ? '?' + params : ''}`);
}

async function getControlMeta() {
  return apiFetch('GET', '/controls/create');
}

async function getControl(id) {
  return apiFetch('GET', `/controls/${id}`);
}

async function getControlEdit(id) {
  return apiFetch('GET', `/controls/${id}/edit`);
}

async function getControlVersions(id) {
  return apiFetch('GET', `/controls/${id}/versions`);
}

async function createControl(payload) {
  return apiFetch('POST', '/controls', { _csrf_token: await csrf(), ...payload });
}

async function updateControl(id, payload) {
  return apiFetch('PUT', `/controls/${id}`, { _csrf_token: await csrf(), ...payload });
}

async function validateControl(id) {
  return apiFetch('POST', `/controls/${id}/validate`, { _csrf_token: await csrf() });
}

async function annulControl(id, reason) {
  return apiFetch('POST', `/controls/${id}/annul`, {
    _csrf_token: await csrf(),
    annulment_reason: reason,
  });
}

// ── Export file (Blob) ────────────────────────────────────────────────────────

async function downloadControlPdf(id) {
  return apiFetch('GET', `/controls/${id}/pdf`, null, true);
}

async function downloadControlsCsv(filters = {}) {
  const params = new URLSearchParams(filters).toString();
  return apiFetch('GET', `/reports/controls.csv${params ? '?' + params : ''}`, null, true);
}

async function downloadControlsXlsx(filters = {}) {
  const params = new URLSearchParams(filters).toString();
  return apiFetch('GET', `/reports/controls.xls${params ? '?' + params : ''}`, null, true);
}

async function downloadControlsPdf(filters = {}) {
  const params = new URLSearchParams(filters).toString();
  return apiFetch('GET', `/reports/controls.pdf${params ? '?' + params : ''}`, null, true);
}

async function downloadStatisticsPdf(filters = {}) {
  const params = new URLSearchParams(filters).toString();
  return apiFetch('GET', `/reports/statistics.pdf${params ? '?' + params : ''}`, null, true);
}

// ── Tabelle di supporto ───────────────────────────────────────────────────────

async function listEvents() {
  return apiFetch('GET', '/events');
}

async function createEvent(name) {
  return apiFetch('POST', '/events', { _csrf_token: await csrf(), name });
}

async function listAgents() {
  return apiFetch('GET', '/agents');
}

async function createAgent(payload) {
  return apiFetch('POST', '/agents', { _csrf_token: await csrf(), ...payload });
}

async function listCategories() {
  return apiFetch('GET', '/activity-categories');
}

// ── Statistiche ───────────────────────────────────────────────────────────────

async function statistics(filters = {}) {
  const params = new URLSearchParams(filters).toString();
  return apiFetch('GET', `/statistics${params ? '?' + params : ''}`);
}

// ── Utenti ────────────────────────────────────────────────────────────────────

async function listUsers() {
  return apiFetch('GET', '/users');
}

async function createUser(payload) {
  return apiFetch('POST', '/users', { _csrf_token: await csrf(), ...payload });
}

// ── Audit log ─────────────────────────────────────────────────────────────────

async function auditLogs(filters = {}) {
  const params = new URLSearchParams(filters).toString();
  return apiFetch('GET', `/audit-logs${params ? '?' + params : ''}`);
}

// ── Profilo ───────────────────────────────────────────────────────────────────

async function profileChangePassword(currentPassword, newPassword) {
  return apiFetch('POST', '/profile/change-password', {
    _csrf_token: await csrf(),
    current_password: currentPassword,
    password: newPassword,
  });
}

export {
  login, logout, me, dashboard,
  listControls, getControlMeta, getControl, getControlEdit, getControlVersions,
  createControl, updateControl, validateControl, annulControl,
  downloadControlPdf, downloadControlsCsv, downloadControlsXlsx,
  downloadControlsPdf, downloadStatisticsPdf,
  listEvents, createEvent, listAgents, createAgent, listCategories,
  statistics, listUsers, createUser, auditLogs, profileChangePassword,
};
```

---

## 2. Flusso autenticazione

```javascript
import * as api from './prometheusApi.js';

// 1. All'avvio dell'app: verifica se la sessione è ancora valida
async function checkSession() {
  try {
    const data = await api.me();
    return data.user;      // { id, username, role, name, surname }
  } catch (err) {
    if (err.code === 401) return null;   // non autenticato
    throw err;
  }
}

// 2. Login
async function doLogin(identifier, password) {
  try {
    const user = await api.login(identifier, password);
    // user.role è: 'amministratore' | 'responsabile_ufficio' | 'operatore' | 'lettore'
    return user;
  } catch (err) {
    if (err.code === 401) alert('Credenziali non valide.');
    if (err.code === 419) alert('Sessione scaduta. Ricarica la pagina.');
  }
}

// 3. Logout
async function doLogout() {
  await api.logout();
  window.location.href = '/login.html';
}
```

---

## 3. Gestione errori

Tutti gli errori del backend hanno questa struttura:

```json
{ "ok": false, "error": "messaggio", "code": 422, "errors": { "campo": ["msg"] } }
```

Pattern consigliato per gestire errori nei form:

```javascript
async function submitForm(payload) {
  clearErrors();

  try {
    await api.createControl(payload);
    showSuccess('Controllo salvato.');
  } catch (err) {
    if (err.code === 401) { window.location.href = '/login.html'; return; }
    if (err.code === 403) { showError('Permessi insufficienti.'); return; }
    if (err.code === 419) { showError('Sessione scaduta. Ricarica la pagina.'); return; }

    if (err.code === 422 && err.errors) {
      // Mostra errori per campo
      for (const [field, messages] of Object.entries(err.errors)) {
        const el = document.getElementById(`error-${field}`);
        if (el) el.textContent = messages[0];
      }
      return;
    }

    showError(err.message ?? 'Errore sconosciuto.');
  }
}
```

---

## 4. Download file (CSV, Excel, PDF)

I file vengono restituiti come binari. Usare `Blob` e `URL.createObjectURL`:

```javascript
async function downloadFile(blobPromise, fileName) {
  try {
    const blob = await blobPromise;
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = fileName;
    a.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    alert('Download non riuscito: ' + err.message);
  }
}

// Esempi di utilizzo
downloadFile(api.downloadControlsCsv({ registry_year: 2026 }), 'controlli_2026.csv');
downloadFile(api.downloadControlsXlsx(), 'controlli.xls');
downloadFile(api.downloadStatisticsPdf({ year: 2026 }), 'statistiche_2026.pdf');
downloadFile(api.downloadControlPdf(5), 'scheda_5.pdf');
```

---

## 5. Visibilità per ruolo

Usare il ruolo dell'utente per mostrare/nascondere elementi dell'interfaccia.

```javascript
const ROLE = {
  ADMIN:    'amministratore',
  MANAGER:  'responsabile_ufficio',
  OPERATOR: 'operatore',
  READER:   'lettore',
};

function can(user, action) {
  const r = user?.role;
  switch (action) {
    // Inserimento e modifica bozze
    case 'create_control':
      return [ROLE.ADMIN, ROLE.MANAGER, ROLE.OPERATOR].includes(r);
    // Modifica validati
    case 'update_validated':
      return [ROLE.ADMIN, ROLE.MANAGER].includes(r);
    // Validazione e annullamento
    case 'validate_annul':
      return [ROLE.ADMIN, ROLE.MANAGER].includes(r);
    // Export report
    case 'export':
      return [ROLE.ADMIN, ROLE.MANAGER].includes(r);
    // Audit log
    case 'audit':
      return [ROLE.ADMIN, ROLE.MANAGER].includes(r);
    // Gestione utenti
    case 'manage_users':
      return r === ROLE.ADMIN;
    // Backup
    case 'backup':
      return r === ROLE.ADMIN;
    // Gestione categorie
    case 'manage_categories':
      return r === ROLE.ADMIN;
    // Gestione agenti
    case 'manage_agents':
      return [ROLE.ADMIN, ROLE.MANAGER].includes(r);
    // Tutto il resto (lettura) — tutti i ruoli autenticati
    default:
      return !!r;
  }
}

// Uso nel template Bootstrap
// Aggiungere/rimuovere la classe 'd-none' in base al ruolo
function applyRoleVisibility(user) {
  document.querySelectorAll('[data-requires]').forEach(el => {
    const action = el.dataset.requires;
    el.classList.toggle('d-none', !can(user, action));
  });
}
```

Negli HTML Bootstrap usare `data-requires`:

```html
<!-- Visibile solo a admin e responsabile -->
<button data-requires="export" class="btn btn-sm btn-outline-secondary">Esporta CSV</button>

<!-- Visibile solo all'amministratore -->
<li data-requires="manage_users" class="nav-item"><a href="/users.html">Utenti</a></li>

<!-- Visibile solo a chi può creare controlli -->
<a data-requires="create_control" href="/controls/new.html" class="btn btn-primary btn-sm">
  Nuovo controllo
</a>
```

---

## 6. Struttura schermate e API utilizzate

### Login (`/login.html`)

| Operazione | Endpoint |
|---|---|
| Caricamento | `GET /csrf-token` (auto nella funzione login) |
| Submit form | `POST /login` |
| Redirect post-login | `GET /me` per recuperare ruolo |

### Dashboard (`/dashboard.html`)

| Operazione | Endpoint |
|---|---|
| Riepilogo numeri | `GET /dashboard` |
| Ultimi controlli | incluso nella risposta dashboard `summary.latest_controls` |

### Lista controlli (`/controls/index.html`)

| Operazione | Endpoint |
|---|---|
| Caricamento | `GET /controls?page=1&per_page=25&sort=control_date&direction=desc` |
| Filtro | `GET /controls?date_from=...&outcome=...&status=...` |
| Ordinamento colonna | `GET /controls?sort=business_name&direction=asc` |
| Link "Dettaglio" | `GET /controls/{id}` |
| Link "Modifica" | `GET /controls/{id}/edit` |
| Bottone "Valida" | `POST /controls/{id}/validate` |
| Bottone "Annulla" | `POST /controls/{id}/annul` |

### Nuovo controllo (`/controls/new.html`)

| Operazione | Endpoint |
|---|---|
| Caricamento form | `GET /controls/create` (categorie, agenti, eventi, default) |
| Submit | `POST /controls` |
| Crea evento al volo | `POST /events` |

### Modifica controllo (`/controls/edit.html?id=N`)

| Operazione | Endpoint |
|---|---|
| Caricamento dati + form | `GET /controls/{id}/edit` |
| Submit | `PUT /controls/{id}` (con `change_reason` obbligatorio) |

### Dettaglio controllo (`/controls/show.html?id=N`)

| Operazione | Endpoint |
|---|---|
| Dati controllo | `GET /controls/{id}` |
| Cronologia versioni | `GET /controls/{id}/versions` |
| Download PDF | `GET /controls/{id}/pdf` |
| Valida | `POST /controls/{id}/validate` |
| Annulla | `POST /controls/{id}/annul` |

### Statistiche (`/statistics.html`)

| Operazione | Endpoint |
|---|---|
| Caricamento | `GET /statistics?year=2026` |
| Filtro evento | `GET /statistics?year=2026&event_id=3` |
| Filtro categoria | `GET /statistics?year=2026&category_id=2` |
| Download PDF | `GET /reports/statistics.pdf?year=2026` |

**Grafici suggeriti (Chart.js):**

```javascript
// Torta esiti
const summaryData = stats.statistics.summary;
new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ['Positivi', 'Negativi', 'Accertamenti'],
    datasets: [{ data: [
      summaryData.positive_controls,
      summaryData.negative_controls,
      summaryData.investigation_controls,
    ]}],
  },
});

// Barre per categoria
const cats = stats.statistics.by_category;
new Chart(ctx2, {
  type: 'bar',
  data: {
    labels: cats.map(c => c.name),
    datasets: [
      { label: 'Positivi',     data: cats.map(c => c.positive) },
      { label: 'Negativi',     data: cats.map(c => c.negative) },
      { label: 'Accertamento', data: cats.map(c => c.investigation) },
    ],
  },
  options: { scales: { x: { stacked: true }, y: { stacked: true } } },
});
```

### Report (`/reports.html`)

| Operazione | Endpoint |
|---|---|
| Catalogo export | `GET /reports` |
| Download CSV | `GET /reports/controls.csv` |
| Download Excel | `GET /reports/controls.xls` |
| Download PDF elenco | `GET /reports/controls.pdf` |
| Download PDF stats | `GET /reports/statistics.pdf` |

### Utenti (`/users.html`) — solo amministratore

| Operazione | Endpoint |
|---|---|
| Lista | `GET /users` |
| Crea | `POST /users` |
| Dettaglio | `GET /users/{id}` |
| Modifica | `PUT /users/{id}` |
| Disattiva | `POST /users/{id}/deactivate` |
| Riattiva | `POST /users/{id}/activate` |
| Cambia password | `POST /users/{id}/change-password` |

### Audit log (`/audit.html`) — admin e responsabile

| Operazione | Endpoint |
|---|---|
| Lista (ultimi 50) | `GET /audit-logs?per_page=50` |
| Filtro per azione | `GET /audit-logs?action=CONTROL_CREATED` |
| Filtro per entità | `GET /audit-logs?entity_type=controls` |
| Filtro per data | `GET /audit-logs?date_from=2026-06-01&date_to=2026-06-30` |

### Profilo (`/profile.html`) — tutti i ruoli

| Operazione | Endpoint |
|---|---|
| Cambio password | `POST /profile/change-password` |

---

## 7. Menu principale con Bootstrap

Struttura navbar consigliata (compatta, senza pulsanti enormi):

```html
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand">Registro Controlli</span>

    <!-- Barra stato servizi: utente connesso + ruolo -->
    <div class="d-flex align-items-center gap-3 ms-auto">
      <small class="text-white-50" id="nav-username"></small>
      <span class="badge bg-secondary" id="nav-role"></span>
    </div>

    <!-- Menu a tendina principale -->
    <div class="navbar-nav ms-3">
      <a class="nav-link" href="/dashboard.html">Dashboard</a>

      <div class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Controlli</a>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="/controls/index.html">Ricerca controlli</a></li>
          <li data-requires="create_control">
            <a class="dropdown-item" href="/controls/new.html">Nuovo controllo</a>
          </li>
        </ul>
      </div>

      <div class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Archivi</a>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="/events.html">Eventi</a></li>
          <li><a class="dropdown-item" href="/agents.html">Agenti</a></li>
          <li data-requires="manage_categories">
            <a class="dropdown-item" href="/categories.html">Categorie</a>
          </li>
        </ul>
      </div>

      <a class="nav-link" href="/statistics.html">Statistiche</a>

      <a class="nav-link" href="/reports.html" data-requires="export">Report</a>

      <div class="nav-item dropdown" data-requires="manage_users">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Amministrazione</a>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="/users.html">Utenti</a></li>
          <li data-requires="audit"><a class="dropdown-item" href="/audit.html">Audit log</a></li>
          <li data-requires="backup"><a class="dropdown-item" href="/backup.html">Backup</a></li>
        </ul>
      </div>

      <div class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" id="nav-user-menu"></a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="/profile.html">Profilo</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="#" id="nav-logout">Esci</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
```

Script inizializzazione navbar:

```javascript
import * as api from './prometheusApi.js';

async function initNav() {
  let user;
  try {
    const data = await api.me();
    user = data.user;
  } catch {
    window.location.href = '/login.html';
    return;
  }

  // Barra stato in alto
  document.getElementById('nav-username').textContent = user.name + ' ' + user.surname;
  document.getElementById('nav-role').textContent = user.role.replace('_', ' ');
  document.getElementById('nav-user-menu').textContent = user.username;

  // Visibilità per ruolo
  applyRoleVisibility(user);

  // Logout
  document.getElementById('nav-logout')?.addEventListener('click', async e => {
    e.preventDefault();
    await api.logout();
    window.location.href = '/login.html';
  });
}
```

---

## 8. Note importanti

### `credentials: 'include'` è obbligatorio
Senza questa opzione in ogni `fetch`, i cookie di sessione non vengono inviati
e tutte le richieste riceveranno `401`.

### CSRF token
Il token viene generato lato backend alla creazione della sessione e cambia dopo
ogni login/logout. La funzione `csrf()` nel client lo gestisce automaticamente:
al primo POST lo chiede al backend; poi lo riusa finché la sessione è valida.

### Boolean JSON
I campi flag (`has_event`, `administrative_seizure`, `criminal_seizure`,
`weapon_precautionary_withdrawal`) accettano `true`/`false` JavaScript nativo.
Non è necessario convertirli in `1`/`0`.

### Campi cifrati
`business_owner`, `offender`, `cnr_number`, `notes` vengono cifrati dal backend
in fase di salvataggio e decifrati automaticamente nella risposta `GET /controls/{id}`.
Il frontend li tratta come normali stringhe.

### Paginazione
Usare sempre `page` e `per_page`. La risposta include `meta.last_page` per
costruire la navigazione pagine. Esempio:

```javascript
const { data, meta } = await api.listControls({ page: 2, per_page: 25 });
// meta = { page: 2, per_page: 25, total: 87, last_page: 4, sort: 'control_date', direction: 'desc' }
```

### Ordinamento colonne tabella
Passare `sort` e `direction` come query parameter. I valori accettati per `sort`
sono: `registry_number`, `registry_year`, `control_date`, `business_name`,
`business_location`, `outcome`, `status`, `total_sanction_amount`, `created_at`.

### available_actions nel dettaglio controllo
La risposta `GET /controls/{id}` include `available_actions`: un array che descrive
le azioni disponibili per l'utente corrente (validate, annul) con metodo e endpoint.
Il frontend può costruire i bottoni dinamicamente da questo array, evitando di
replicare la logica dei permessi.

```javascript
const { control, available_actions } = await api.getControl(id);

available_actions.forEach(action => {
  const btn = document.createElement('button');
  btn.textContent = action.name === 'validate' ? 'Valida' : 'Annulla';
  btn.className = 'btn btn-sm ' + (action.name === 'validate' ? 'btn-success' : 'btn-danger');
  btn.addEventListener('click', () => executeAction(action));
  actionsContainer.appendChild(btn);
});
```
