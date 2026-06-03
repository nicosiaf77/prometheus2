const API_BASE = window.PROMETHEUS_CONFIG?.apiBase ?? "http://localhost:8080";

let csrfToken = null;

function buildQuery(params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === "" || value === null || value === undefined) {
      return;
    }

    query.append(key, String(value));
  });

  const serialized = query.toString();
  return serialized ? `?${serialized}` : "";
}

async function parseError(response) {
  const fallback = {
    ok: false,
    error: `HTTP ${response.status}`,
    code: response.status,
    errors: {},
  };

  const contentType = response.headers.get("content-type") ?? "";

  if (!contentType.includes("application/json")) {
    return fallback;
  }

  try {
    const payload = await response.json();
    return {
      ok: false,
      error: payload.error ?? fallback.error,
      code: payload.code ?? response.status,
      errors: payload.errors ?? {},
    };
  } catch {
    return fallback;
  }
}

async function request(method, path, options = {}) {
  const { body, expectBlob = false } = options;
  const headers = { Accept: "application/json" };
  const init = {
    method,
    headers,
    credentials: "include",
  };

  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    init.body = JSON.stringify(body);
  }

  let response;

  try {
    response = await fetch(`${API_BASE}${path}`, init);
  } catch (error) {
    throw {
      ok: false,
      code: 0,
      errors: {},
      error: `Backend non raggiungibile su ${API_BASE}. Verifica che il server API sia avviato e che CORS consenta questa origine.`,
      cause: error,
    };
  }

  if (expectBlob) {
    if (!response.ok) {
      throw await parseError(response);
    }

    return {
      blob: await response.blob(),
      fileName: parseFileName(response.headers.get("content-disposition")),
      contentType: response.headers.get("content-type") ?? "application/octet-stream",
    };
  }

  const contentType = response.headers.get("content-type") ?? "";
  const payload = contentType.includes("application/json") ? await response.json() : null;

  if (!response.ok || !payload?.ok) {
    throw payload
      ? {
          ok: false,
          error: payload.error ?? `HTTP ${response.status}`,
          code: payload.code ?? response.status,
          errors: payload.errors ?? {},
        }
      : await parseError(response);
  }

  return payload;
}

function parseFileName(contentDisposition) {
  if (!contentDisposition) {
    return null;
  }

  const match = contentDisposition.match(/filename="([^"]+)"/i);
  return match ? match[1] : null;
}

async function getCsrfToken(force = false) {
  if (csrfToken && !force) {
    return csrfToken;
  }

  const payload = await request("GET", "/csrf-token");
  csrfToken = payload.csrf_token;
  return csrfToken;
}

async function withCsrf(data = {}) {
  return {
    ...data,
    _csrf_token: await getCsrfToken(),
  };
}

async function login(identifier, password) {
  const token = await getCsrfToken(true);
  const payload = await request("POST", "/login", {
    body: { identifier, password, _csrf_token: token },
  });
  csrfToken = null;
  return payload;
}

async function logout() {
  const payload = await request("POST", "/logout", {
    body: await withCsrf(),
  });
  csrfToken = null;
  return payload;
}

function me() {
  return request("GET", "/me");
}

function dashboard() {
  return request("GET", "/dashboard");
}

function listControls(filters = {}) {
  return request("GET", `/controls${buildQuery(filters)}`);
}

function getControl(id) {
  return request("GET", `/controls/${id}`);
}

function downloadControlPdf(id) {
  return request("GET", `/controls/${id}/pdf`, { expectBlob: true });
}

function getControlMeta() {
  return request("GET", "/controls/create");
}

async function createControl(data) {
  return request("POST", "/controls", { body: await withCsrf(data) });
}

async function updateControl(id, data) {
  return request("PUT", `/controls/${id}`, { body: await withCsrf(data) });
}

function downloadControlsCsv(filters = {}) {
  return request("GET", `/reports/controls.csv${buildQuery(filters)}`, { expectBlob: true });
}

function downloadControlsXls(filters = {}) {
  return request("GET", `/reports/controls.xls${buildQuery(filters)}`, { expectBlob: true });
}

function downloadControlsPdf(filters = {}) {
  return request("GET", `/reports/controls.pdf${buildQuery(filters)}`, { expectBlob: true });
}

export {
  API_BASE,
  createControl,
  dashboard,
  downloadControlsCsv,
  downloadControlPdf,
  downloadControlsPdf,
  downloadControlsXls,
  getControl,
  getControlMeta,
  getCsrfToken,
  listControls,
  login,
  logout,
  me,
  updateControl,
};
