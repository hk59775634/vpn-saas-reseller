const API_BASE = (window.RESELLER_API_BASE || "http://localhost:8080").replace(/\/+$/, "");

const loginView = document.getElementById("login-view");
const dashboardView = document.getElementById("dashboard-view");
const loginBtn = document.getElementById("login-btn");
const logoutBtn = document.getElementById("logout-btn");
const loginError = document.getElementById("login-error");
const welcomeText = document.getElementById("welcome-text");
const resellerInfo = document.getElementById("reseller-info");
const apiKeysBody = document.getElementById("api-keys-body");

function getToken() {
  return localStorage.getItem("reseller_token") || "";
}
function setToken(t) {
  localStorage.setItem("reseller_token", t);
}
function clearAuth() {
  localStorage.removeItem("reseller_token");
}

async function api(path, options = {}) {
  const headers = options.headers || {};
  headers["Content-Type"] = "application/json";
  const token = getToken();
  if (token) headers["Authorization"] = `Bearer ${token}`;
  const res = await fetch(`${API_BASE}${path}`, { ...options, headers });
  if (!res.ok) throw new Error(await res.text().catch(() => res.statusText));
  if (res.status === 204) return null;
  const body = await res.json();
  return unwrapEnvelope(body);
}

function unwrapEnvelope(body) {
  if (
    body &&
    typeof body === "object" &&
    Object.prototype.hasOwnProperty.call(body, "success") &&
    Object.prototype.hasOwnProperty.call(body, "data")
  ) {
    return body.data;
  }
  return body;
}

async function handleLogin() {
  loginError.textContent = "";
  const apiKey = document.getElementById("api_key").value.trim();
  if (!apiKey) {
    loginError.textContent = "请输入 API Key";
    return;
  }
  try {
    const resp = await fetch(`${API_BASE}/api/v1/reseller/auth`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ api_key: apiKey }),
    });
    if (!resp.ok) {
      loginError.textContent = "API Key 无效";
      return;
    }
    const data = unwrapEnvelope(await resp.json());
    setToken(data.token);
    welcomeText.textContent = "已登录 · 分销商 ID: " + data.reseller_id;
    loginView.classList.add("hidden");
    dashboardView.classList.remove("hidden");
    loadMe();
    loadApiKeys();
  } catch (e) {
    loginError.textContent = "登录失败";
  }
}

async function loadMe() {
  if (!resellerInfo) return;
  try {
    const r = await api("/api/v1/reseller/me");
    resellerInfo.textContent = JSON.stringify(r, null, 2);
  } catch (e) {
    resellerInfo.textContent = "加载失败";
  }
}

async function loadApiKeys() {
  if (!apiKeysBody) return;
  try {
    const list = await api("/api/v1/reseller/me/api_keys");
    apiKeysBody.innerHTML = (list || []).map((k) => `
      <tr>
        <td>${k.id}</td>
        <td>${escapeHtml(k.name || "")}</td>
        <td>${escapeHtml(k.key_masked || k.keyMasked || "")}</td>
        <td>${k.created_at ? new Date(k.created_at).toLocaleString() : "-"}</td>
      </tr>
    `).join("");
  } catch (e) {
    apiKeysBody.innerHTML = "<tr><td colspan='4'>加载失败</td></tr>";
  }
}

function escapeHtml(s) {
  const div = document.createElement("div");
  div.textContent = s;
  return div.innerHTML;
}

loginBtn.addEventListener("click", handleLogin);
logoutBtn.addEventListener("click", () => {
  clearAuth();
  dashboardView.classList.add("hidden");
  loginView.classList.remove("hidden");
});

document.addEventListener("DOMContentLoaded", () => {
  if (getToken()) {
    loginView.classList.add("hidden");
    dashboardView.classList.remove("hidden");
    welcomeText.textContent = "已登录";
    loadMe();
    loadApiKeys();
  }
  const apiKeysRefresh = document.getElementById("api-keys-refresh-btn");
  if (apiKeysRefresh) apiKeysRefresh.addEventListener("click", loadApiKeys);
});
