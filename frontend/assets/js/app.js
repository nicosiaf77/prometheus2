import * as api from "./api.js";

const state = {
  user: null,
  summary: null,
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
                <button class="nav-item${index === 0 ? " active" : ""}" type="button" ${index === 0 ? "" : "disabled"}>
                  ${escapeHtml(item.label)}
                </button>
              `)
              .join("")}
          </nav>
        </aside>
        <main class="content-stack">
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
              <span class="subtle">Prossimo step: lista completa con filtri reali.</span>
            </div>
            ${renderLatestControls(summary.latest_controls ?? [])}
          </section>
        </main>
      </div>
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

function canExport() {
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
