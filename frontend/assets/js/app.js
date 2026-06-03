import * as api from "./api.js";

const state = {
  user: null,
  summary: null,
  controls: null,
  controlsError: "",
  controlDetail: null,
  controlDetailError: "",
  activePage: "dashboard",
  loadingPage: false,
  view: "loading",
};

const roleLabels = {
  amministratore: "Amministratore",
  responsabile_ufficio: "Responsabile ufficio",
  operatore: "Operatore",
  lettore: "Lettore",
};

const navItems = [
  { id: "dashboard", label: "Dashboard", roles: ["amministratore", "responsabile_ufficio", "operatore", "lettore"] },
  { id: "controls", label: "Controlli", roles: ["amministratore", "responsabile_ufficio", "operatore", "lettore"] },
  { id: "create-control", label: "Nuovo controllo", roles: ["amministratore", "responsabile_ufficio", "operatore"] },
  { id: "reports", label: "Export report", roles: ["amministratore", "responsabile_ufficio"] },
  { id: "support", label: "Tabelle supporto", roles: ["amministratore", "responsabile_ufficio", "operatore", "lettore"] },
  { id: "users", label: "Utenti", roles: ["amministratore"] },
  { id: "audit", label: "Audit log", roles: ["amministratore", "responsabile_ufficio"] },
];

const controlFilterDefaults = {
  registry_year: String(new Date().getFullYear()),
  registry_number: "",
  business_name: "",
  outcome: "",
  status: "",
  page: "1",
  per_page: "25",
  sort: "control_date",
  direction: "desc",
};

document.addEventListener("DOMContentLoaded", () => {
  bindGlobalEvents();
  boot();
});

function bindGlobalEvents() {
  document.body.addEventListener("submit", handleSubmit);
  document.body.addEventListener("click", handleClick);
}

async function boot() {
  render();

  try {
    const session = await api.me();
    state.user = session.user;
    state.view = "app";
    await loadDashboard();
  } catch (error) {
    if (error.code === 401) {
      state.user = null;
      state.summary = null;
      state.view = "login";
      render();
      return;
    }

    showFatalError(error);
  }
}

async function loadDashboard() {
  try {
    const payload = await api.dashboard();
    state.summary = payload.summary;
    state.view = "app";
    render();
  } catch (error) {
    if (error.code === 401) {
      state.user = null;
      state.summary = null;
      state.view = "login";
      render();
      return;
    }

    showFatalError(error);
  }
}

async function handleSubmit(event) {
  if (event.target.matches("[data-login-form]")) {
    event.preventDefault();
    await submitLogin(event.target);
    return;
  }

  if (event.target.matches("[data-controls-filter-form]")) {
    event.preventDefault();
    await submitControlsFilters(event.target);
  }
}

async function handleClick(event) {
  const action = event.target.closest("[data-action]");

  if (!action) {
    return;
  }

  if (action.dataset.action === "logout") {
    event.preventDefault();
    await submitLogout();
    return;
  }

  if (action.dataset.action === "navigate") {
    event.preventDefault();
    await navigateTo(action.dataset.target);
    return;
  }

  if (action.dataset.action === "controls-page") {
    event.preventDefault();
    await loadControls({ page: action.dataset.page });
    return;
  }

  if (action.dataset.action === "controls-reset") {
    event.preventDefault();
    const form = document.querySelector("[data-controls-filter-form]");

    if (form) {
      form.reset();
      form.elements.registry_year.value = controlFilterDefaults.registry_year;
      form.elements.per_page.value = controlFilterDefaults.per_page;
      form.elements.sort.value = controlFilterDefaults.sort;
      form.elements.direction.value = controlFilterDefaults.direction;
    }

    await loadControls(controlFilterDefaults);
    return;
  }

  if (action.dataset.action === "control-detail") {
    event.preventDefault();
    await loadControlDetail(action.dataset.controlId);
    return;
  }

  if (action.dataset.action === "back-to-controls") {
    event.preventDefault();
    state.activePage = "controls";
    state.controlDetailError = "";
    render();
    return;
  }

  if (action.dataset.action === "download-control-pdf") {
    event.preventDefault();
    await handleControlPdfDownload(action.dataset.controlId);
  }
}

async function submitLogin(form) {
  clearFormErrors(form);
  setNotice("");

  const identifier = form.elements.identifier.value.trim();
  const password = form.elements.password.value;
  const submitButton = form.querySelector('button[type="submit"]');

  submitButton.disabled = true;

  try {
    const payload = await api.login(identifier, password);
    state.user = payload.user;
    state.view = "app";
    render();
    await loadDashboard();
  } catch (error) {
    handleApiError(error, form);
  } finally {
    submitButton.disabled = false;
  }
}

async function submitLogout() {
  setNotice("");

  try {
    await api.logout();
  } catch (error) {
    if (![401, 419].includes(error.code)) {
      setNotice(error.error ?? "Logout non riuscito.", "error");
      return;
    }
  }

  state.user = null;
  state.summary = null;
  state.view = "login";
  render();
  setNotice("Sessione chiusa.", "info");
}

async function navigateTo(page) {
  if (!page || state.loadingPage) {
    return;
  }

  state.activePage = page;
  state.controlsError = "";

  if (page === "controls") {
    await loadControls();
    return;
  }

  if (page === "dashboard") {
    render();
    await loadDashboard();
    return;
  }

  render();
}

async function loadControls(overrides = {}) {
  state.loadingPage = true;
  state.activePage = "controls";
  render();

  const baseFilters = state.controls?.filters ?? controlFilterDefaults;
  const filters = { ...baseFilters, ...overrides };

  try {
    const payload = await api.listControls(filters);
    state.controls = {
      filters: normalizeControlFilters({ ...filters, ...payload.filters }),
      rows: payload.data ?? [],
      meta: payload.meta ?? null,
    };
    state.controlsError = "";
  } catch (error) {
    if (error.code === 401) {
      state.user = null;
      state.summary = null;
      state.controls = null;
      state.view = "login";
      render();
      return;
    }

    if (error.code === 403) {
      state.controlsError = "Permessi insufficienti per consultare i controlli.";
    } else {
      state.controlsError = error.error ?? "Errore nel caricamento controlli.";
    }
  } finally {
    state.loadingPage = false;
    render();
  }
}

async function loadControlDetail(controlId) {
  if (!controlId) {
    return;
  }

  state.loadingPage = true;
  state.activePage = "control-detail";
  state.controlDetail = null;
  state.controlDetailError = "";
  render();

  try {
    const payload = await api.getControl(controlId);
    state.controlDetail = {
      control: payload.control,
      availableActions: payload.available_actions ?? [],
    };
  } catch (error) {
    if (error.code === 401) {
      state.user = null;
      state.summary = null;
      state.controls = null;
      state.controlDetail = null;
      state.view = "login";
      render();
      return;
    }

    if (error.code === 404) {
      state.controlDetailError = "Controllo non trovato.";
    } else if (error.code === 403) {
      state.controlDetailError = "Permessi insufficienti per consultare questo controllo.";
    } else {
      state.controlDetailError = error.error ?? "Errore nel caricamento dettaglio controllo.";
    }
  } finally {
    state.loadingPage = false;
    render();
  }
}

async function handleControlPdfDownload(controlId) {
  if (!controlId) {
    return;
  }

  try {
    const file = await api.downloadControlPdf(controlId);
    downloadBlob(file.blob, file.fileName ?? `control-${controlId}.pdf`);
  } catch (error) {
    if (error.code === 401) {
      state.user = null;
      state.view = "login";
      render();
      return;
    }

    state.controlDetailError = error.error ?? "Download PDF non riuscito.";
    render();
  }
}

async function submitControlsFilters(form) {
  const filters = normalizeControlFilters({
    registry_year: form.elements.registry_year.value.trim(),
    registry_number: form.elements.registry_number.value.trim(),
    business_name: form.elements.business_name.value.trim(),
    outcome: form.elements.outcome.value,
    status: form.elements.status.value,
    per_page: form.elements.per_page.value,
    sort: form.elements.sort.value,
    direction: form.elements.direction.value,
    page: "1",
  });

  await loadControls(filters);
}

function handleApiError(error, form) {
  if (error.code === 419) {
    setNotice("Sessione o token CSRF scaduti. Riprova.", "error");
    return;
  }

  if (error.code === 401) {
    setNotice("Credenziali non valide.", "error");
    return;
  }

  if (error.code === 403) {
    setNotice("Permessi insufficienti per questa operazione.", "error");
    return;
  }

  if (error.code === 422 && form) {
    showFieldErrors(form, error.errors ?? {});
    setNotice("Verifica i campi evidenziati.", "error");
    return;
  }

  setNotice(error.error ?? "Errore inatteso.", "error");
}

function showFatalError(error) {
  const app = document.getElementById("app");
  app.innerHTML = `
    <section class="fatal-state">
      <h1>Frontend non inizializzato</h1>
      <p>${escapeHtml(error.error ?? "Errore inatteso.")}</p>
      <p class="subtle">API base: ${escapeHtml(api.API_BASE)}</p>
    </section>
  `;
}

function render() {
  const app = document.getElementById("app");

  if (state.view === "loading") {
    app.innerHTML = renderLoading();
    return;
  }

  if (state.view === "login") {
    app.innerHTML = renderLogin();
    return;
  }

  app.innerHTML = renderApplication();
}

function renderLoading() {
  return `
    <section class="shell shell-centered">
      <div class="panel compact-panel">
        <div class="loading-line"></div>
        <div class="loading-line short"></div>
      </div>
    </section>
  `;
}

function renderLogin() {
  return `
    <section class="shell shell-centered">
      <div class="auth-card panel">
        <div class="auth-header">
          <p class="eyebrow">Prometheus2</p>
          <h1>Accesso</h1>
          <p>Frontend minimale collegato alle API del branch backend di riferimento.</p>
        </div>
        <div id="notice"></div>
        <form data-login-form class="auth-form" novalidate>
          <label>
            <span>Identificativo</span>
            <input class="form-control" name="identifier" type="text" autocomplete="username" required>
            <small class="field-error" data-error-for="identifier"></small>
          </label>
          <label>
            <span>Password</span>
            <input class="form-control" name="password" type="password" autocomplete="current-password" required>
            <small class="field-error" data-error-for="password"></small>
          </label>
          <button class="btn btn-sm btn-primary" type="submit">Login</button>
        </form>
        <div class="auth-footer">
          <span>Backend API</span>
          <code>${escapeHtml(api.API_BASE)}</code>
        </div>
      </div>
    </section>
  `;
}

function renderApplication() {
  const userName = [state.user?.name, state.user?.surname].filter(Boolean).join(" ") || state.user?.username || "Utente";
  const role = roleLabels[state.user?.role] ?? state.user?.role ?? "-";
  const summary = state.summary ?? {};

  return `
    <section class="shell">
      <header class="topbar panel">
        <div class="brand">
          <span class="brand-mark">P2</span>
          <div>
            <strong>Prometheus2</strong>
            <small>Frontend essenziale</small>
          </div>
        </div>
        <div class="service-strip">
          ${renderServicePill("API", "online")}
          ${renderServicePill("Sessione", "online")}
          ${renderServicePill("Export", canExport() ? "online" : "idle")}
        </div>
        <div class="user-strip">
          <div>
            <strong>${escapeHtml(userName)}</strong>
            <small>${escapeHtml(role)}</small>
          </div>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-action="logout">Logout</button>
        </div>
      </header>
      <div class="workspace-grid">
        <aside class="sidebar panel">
          <div class="sidebar-title">Lavoro</div>
          <nav class="nav-list">
            ${navItems
              .filter((item) => item.roles.includes(state.user.role))
              .map((item, index) => `
                <button
                  class="nav-item${state.activePage === item.id ? " active" : ""}"
                  type="button"
                  data-action="navigate"
                  data-target="${escapeHtml(item.id)}"
                  ${isImplementedPage(item.id) ? "" : "disabled"}
                >
                  ${escapeHtml(item.label)}
                </button>
              `)
              .join("")}
          </nav>
        </aside>
        <main class="content-stack">
          ${renderActivePage(summary)}
        </main>
      </div>
    </section>
  `;
}

function renderActivePage(summary) {
  if (state.activePage === "control-detail") {
    return renderControlDetailPage();
  }

  if (state.activePage === "controls") {
    return renderControlsPage();
  }

  return `
    <section class="panel page-header">
      <div>
        <p class="eyebrow">Dashboard</p>
        <h1>Area di lavoro</h1>
        <p>Base pronta per sessione, ruoli, layout e schermate successive.</p>
      </div>
    </section>
    <section class="metrics-grid">
      ${renderMetric("Controlli anno", formatNumber(summary.year_controls))}
      ${renderMetric("Controlli mese", formatNumber(summary.month_controls))}
      ${renderMetric("Bozze", formatNumber(summary.draft_controls))}
      ${renderMetric("Sanzioni anno", formatCurrency(summary.year_sanctions))}
    </section>
    <section class="panel table-panel">
      <div class="section-head">
        <h2>Ultimi controlli</h2>
        <span class="subtle">Lista completa disponibile nella sezione Controlli.</span>
      </div>
      ${renderLatestControls(summary.latest_controls ?? [])}
    </section>
  `;
}

function renderControlsPage() {
  const filters = state.controls?.filters ?? controlFilterDefaults;
  const rows = state.controls?.rows ?? [];
  const meta = state.controls?.meta;

  return `
    <section class="panel page-header">
      <div>
        <p class="eyebrow">Controlli</p>
        <h1>Lista controlli</h1>
        <p>Ricerca essenziale con i filtri principali effettivamente supportati dal backend.</p>
      </div>
    </section>
    <section class="panel table-panel">
      <form class="filters-grid" data-controls-filter-form>
        <label>
          <span>Anno</span>
          <input class="form-control form-control-sm" name="registry_year" value="${escapeHtml(filters.registry_year ?? "")}" inputmode="numeric">
        </label>
        <label>
          <span>Registro</span>
          <input class="form-control form-control-sm" name="registry_number" value="${escapeHtml(filters.registry_number ?? "")}" inputmode="numeric">
        </label>
        <label>
          <span>Attività</span>
          <input class="form-control form-control-sm" name="business_name" value="${escapeHtml(filters.business_name ?? "")}">
        </label>
        <label>
          <span>Esito</span>
          <select class="form-select form-select-sm" name="outcome">
            ${renderSelectOptions(filters.outcome, [
              ["", "Tutti"],
              ["positivo", "Positivo"],
              ["negativo", "Negativo"],
              ["in_accertamento", "In accertamento"],
            ])}
          </select>
        </label>
        <label>
          <span>Stato</span>
          <select class="form-select form-select-sm" name="status">
            ${renderSelectOptions(filters.status, [
              ["", "Tutti"],
              ["bozza", "Bozza"],
              ["validato", "Validato"],
              ["annullato", "Annullato"],
            ])}
          </select>
        </label>
        <label>
          <span>Per pagina</span>
          <select class="form-select form-select-sm" name="per_page">
            ${renderSelectOptions(filters.per_page, [
              ["10", "10"],
              ["25", "25"],
              ["50", "50"],
            ])}
          </select>
        </label>
        <label>
          <span>Ordina per</span>
          <select class="form-select form-select-sm" name="sort">
            ${renderSelectOptions(filters.sort, [
              ["control_date", "Data controllo"],
              ["registry_number", "Numero registro"],
              ["business_name", "Attività"],
              ["status", "Stato"],
              ["outcome", "Esito"],
            ])}
          </select>
        </label>
        <label>
          <span>Direzione</span>
          <select class="form-select form-select-sm" name="direction">
            ${renderSelectOptions(filters.direction, [
              ["desc", "Discendente"],
              ["asc", "Ascendente"],
            ])}
          </select>
        </label>
        <div class="filter-actions">
          <button class="btn btn-sm btn-primary" type="submit">Applica</button>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-action="controls-reset">Reset</button>
        </div>
      </form>
      ${state.controlsError ? `<div class="notice error">${escapeHtml(state.controlsError)}</div>` : ""}
      ${state.loadingPage ? `<div class="loading-block">Caricamento controlli...</div>` : renderControlsTable(rows)}
      ${renderControlsPagination(meta)}
    </section>
  `;
}

function renderServicePill(label, tone) {
  return `<span class="service-pill ${tone}">${escapeHtml(label)}</span>`;
}

function renderMetric(label, value) {
  return `
    <article class="panel metric-card">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
    </article>
  `;
}

function renderLatestControls(rows) {
  if (!rows.length) {
    return `<div class="empty-state">Nessun controllo disponibile.</div>`;
  }

  const body = rows
    .map((row) => `
      <tr>
        <td>${escapeHtml(formatRegistry(row.registry_number, row.registry_year))}</td>
        <td>${escapeHtml(row.control_date ?? "-")}</td>
        <td>${escapeHtml(row.business_name ?? "-")}</td>
        <td>${escapeHtml(row.event_name ?? "-")}</td>
        <td>${escapeHtml(row.outcome ?? "-")}</td>
        <td>${escapeHtml(row.status ?? "-")}</td>
      </tr>
    `)
    .join("");

  return `
    <div class="table-wrap">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Registro</th>
            <th>Data</th>
            <th>Attività</th>
            <th>Evento</th>
            <th>Esito</th>
            <th>Stato</th>
          </tr>
        </thead>
        <tbody>${body}</tbody>
      </table>
    </div>
  `;
}

function renderControlsTable(rows) {
  if (!rows.length) {
    return `<div class="empty-state">Nessun controllo trovato con i filtri correnti.</div>`;
  }

  const body = rows
    .map((row) => `
      <tr>
        <td>
          <button class="link-button" type="button" data-action="control-detail" data-control-id="${escapeHtml(String(row.id ?? ""))}">
            ${escapeHtml(formatRegistry(row.registry_number, row.registry_year))}
          </button>
        </td>
        <td>${escapeHtml(row.control_date ?? "-")}<br><span class="subtle">${escapeHtml(row.control_time ?? "")}</span></td>
        <td>${escapeHtml(row.business_name ?? "-")}<br><span class="subtle">${escapeHtml(row.business_location ?? "-")}</span></td>
        <td>${escapeHtml(row.primary_category_name ?? "-")}</td>
        <td>${escapeHtml(row.event_name ?? "-")}</td>
        <td>${escapeHtml(row.agents_names ?? "-")}</td>
        <td>${escapeHtml(row.outcome ?? "-")}</td>
        <td>${escapeHtml(row.status ?? "-")}</td>
      </tr>
    `)
    .join("");

  return `
    <div class="table-wrap">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Registro</th>
            <th>Data</th>
            <th>Attività</th>
            <th>Categoria</th>
            <th>Evento</th>
            <th>Agenti</th>
            <th>Esito</th>
            <th>Stato</th>
          </tr>
        </thead>
        <tbody>${body}</tbody>
      </table>
    </div>
  `;
}

function renderControlDetailPage() {
  if (state.loadingPage) {
    return `
      <section class="panel page-header">
        <div>
          <p class="eyebrow">Controllo</p>
          <h1>Dettaglio controllo</h1>
          <p>Caricamento in corso.</p>
        </div>
      </section>
    `;
  }

  if (state.controlDetailError) {
    return `
      <section class="panel page-header">
        <div>
          <p class="eyebrow">Controllo</p>
          <h1>Dettaglio controllo</h1>
          <p>${escapeHtml(state.controlDetailError)}</p>
        </div>
      </section>
      <section class="panel table-panel">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-action="back-to-controls">Torna alla lista</button>
      </section>
    `;
  }

  const detail = state.controlDetail?.control;

  if (!detail) {
    return "";
  }

  const actions = state.controlDetail?.availableActions ?? [];

  return `
    <section class="panel page-header">
      <div class="detail-header">
        <div>
          <p class="eyebrow">Controllo</p>
          <h1>${escapeHtml(formatRegistry(detail.registry_number, detail.registry_year))}</h1>
          <p>${escapeHtml(detail.business_name ?? "-")} · ${escapeHtml(detail.control_date ?? "-")}</p>
        </div>
        <div class="detail-actions">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-action="back-to-controls">Torna alla lista</button>
          ${canDownloadControlPdf() ? `<button class="btn btn-sm btn-outline-secondary" type="button" data-action="download-control-pdf" data-control-id="${escapeHtml(String(detail.id))}">PDF</button>` : ""}
        </div>
      </div>
    </section>
    ${state.controlDetailError ? `<div class="notice error">${escapeHtml(state.controlDetailError)}</div>` : ""}
    <section class="detail-grid">
      <article class="panel detail-card">
        <h2>Dati principali</h2>
        ${renderDefinitionList([
          ["Registro", formatRegistry(detail.registry_number, detail.registry_year)],
          ["Data", detail.control_date ?? "-"],
          ["Ora", detail.control_time ?? "-"],
          ["Esito", detail.outcome ?? "-"],
          ["Stato", detail.status ?? "-"],
          ["Evento", detail.event_name ?? "-"],
          ["Attività", detail.business_name ?? "-"],
          ["Luogo", detail.business_location ?? "-"],
        ])}
      </article>
      <article class="panel detail-card">
        <h2>Soggetti e categorie</h2>
        ${renderDefinitionList([
          ["Titolare", detail.business_owner ?? "-"],
          ["Trasgressore", detail.offender ?? "-"],
          ["Categoria principale", detail.primary_category?.name ?? "-"],
          ["Categorie secondarie", joinNames(detail.secondary_categories, "name")],
          ["Agenti", joinPeople(detail.agents)],
        ])}
      </article>
      <article class="panel detail-card">
        <h2>Provvedimenti</h2>
        ${renderDefinitionList([
          ["Norme violate", detail.violated_rules ?? "-"],
          ["Norme sanzionatorie", detail.sanctioning_rules ?? "-"],
          ["Sanzione totale", formatNullableCurrency(detail.total_sanction_amount)],
          ["Reato ipotizzato", detail.alleged_crime ?? "-"],
          ["CNR", detail.cnr_number ?? "-"],
          ["Note", detail.notes ?? "-"],
        ])}
      </article>
      <article class="panel detail-card">
        <h2>Azioni e versioni</h2>
        <p class="subtle">Azioni disponibili dal backend: ${actions.length ? actions.map((item) => item.name).join(", ") : "nessuna"}</p>
        ${renderVersions(detail.versions ?? [])}
      </article>
    </section>
  `;
}

function renderControlsPagination(meta) {
  if (!meta) {
    return "";
  }

  const page = Number(meta.page ?? 1);
  const lastPage = Number(meta.last_page ?? 1);
  const total = Number(meta.total ?? 0);
  const prevDisabled = page <= 1 ? "disabled" : "";
  const nextDisabled = page >= lastPage ? "disabled" : "";

  return `
    <div class="pagination-bar">
      <span class="subtle">Pagina ${page} di ${lastPage} · Totale ${formatNumber(total)}</span>
      <div class="pagination-actions">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-action="controls-page" data-page="${page - 1}" ${prevDisabled}>Precedente</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-action="controls-page" data-page="${page + 1}" ${nextDisabled}>Successiva</button>
      </div>
    </div>
  `;
}

function renderSelectOptions(currentValue, options) {
  return options
    .map(([value, label]) => `<option value="${escapeHtml(value)}" ${String(currentValue ?? "") === value ? "selected" : ""}>${escapeHtml(label)}</option>`)
    .join("");
}

function isImplementedPage(page) {
  return ["dashboard", "controls"].includes(page);
}

function canExport() {
  return ["amministratore", "responsabile_ufficio"].includes(state.user?.role);
}

function canDownloadControlPdf() {
  return ["amministratore", "responsabile_ufficio"].includes(state.user?.role);
}

function setNotice(message, tone = "info") {
  const notice = document.getElementById("notice");

  if (!notice) {
    return;
  }

  if (!message) {
    notice.innerHTML = "";
    return;
  }

  notice.innerHTML = `<div class="notice ${tone}">${escapeHtml(message)}</div>`;
}

function clearFormErrors(form) {
  form.querySelectorAll(".field-error").forEach((node) => {
    node.textContent = "";
  });

  form.querySelectorAll(".is-invalid").forEach((node) => {
    node.classList.remove("is-invalid");
  });
}

function showFieldErrors(form, errors) {
  Object.entries(errors).forEach(([field, messages]) => {
    const input = form.elements[field];
    const errorNode = form.querySelector(`[data-error-for="${field}"]`);
    const message = Array.isArray(messages) ? messages[0] : String(messages);

    if (input) {
      input.classList.add("is-invalid");
    }

    if (errorNode) {
      errorNode.textContent = message;
    }
  });
}

function formatRegistry(number, year) {
  if (!number || !year) {
    return "-";
  }

  return `${number}/${year}`;
}

function normalizeControlFilters(filters) {
  return {
    registry_year: String(filters.registry_year ?? controlFilterDefaults.registry_year),
    registry_number: String(filters.registry_number ?? ""),
    business_name: String(filters.business_name ?? ""),
    outcome: String(filters.outcome ?? ""),
    status: String(filters.status ?? ""),
    page: String(filters.page ?? "1"),
    per_page: String(filters.per_page ?? controlFilterDefaults.per_page),
    sort: String(filters.sort ?? controlFilterDefaults.sort),
    direction: String(filters.direction ?? controlFilterDefaults.direction),
  };
}

function renderDefinitionList(entries) {
  return `
    <dl class="detail-list">
      ${entries
        .map(([label, value]) => `
          <div>
            <dt>${escapeHtml(label)}</dt>
            <dd>${escapeHtml(value || "-")}</dd>
          </div>
        `)
        .join("")}
    </dl>
  `;
}

function renderVersions(versions) {
  if (!versions.length) {
    return `<div class="empty-state">Nessuna versione disponibile.</div>`;
  }

  return `
    <div class="table-wrap">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Versione</th>
            <th>Data</th>
            <th>Utente</th>
            <th>Motivo</th>
          </tr>
        </thead>
        <tbody>
          ${versions
            .map((version) => `
              <tr>
                <td>${escapeHtml(String(version.version_number ?? "-"))}</td>
                <td>${escapeHtml(version.created_at ?? "-")}</td>
                <td>${escapeHtml(version.changed_by_username ?? "-")}</td>
                <td>${escapeHtml(version.change_reason ?? "-")}</td>
              </tr>
            `)
            .join("")}
        </tbody>
      </table>
    </div>
  `;
}

function joinNames(items, field) {
  if (!Array.isArray(items) || !items.length) {
    return "-";
  }

  return items.map((item) => item?.[field]).filter(Boolean).join(", ") || "-";
}

function joinPeople(items) {
  if (!Array.isArray(items) || !items.length) {
    return "-";
  }

  return items
    .map((item) => [item?.surname, item?.name].filter(Boolean).join(" ").trim())
    .filter(Boolean)
    .join(", ") || "-";
}

function formatNullableCurrency(value) {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  return formatCurrency(value);
}

function downloadBlob(blob, fileName) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = fileName;
  link.click();
  URL.revokeObjectURL(url);
}

function formatNumber(value) {
  return new Intl.NumberFormat("it-IT").format(Number(value ?? 0));
}

function formatCurrency(value) {
  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value ?? 0));
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}
