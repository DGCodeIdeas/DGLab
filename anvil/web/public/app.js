'use strict';

/**
 * Anvil v3 Web UI — dependency-free SPA.
 *
 * Polls the front controller (index.php?route=api/...) on an interval and
 * renders:
 *   - Stack start/stop toggles (v1)
 *   - Stack shape (slim|full) — v3
 *   - Doctor (CVE floors + port registry) — v3
 *   - Tenant cards (v1 projects, renamed)
 *   - Deploy / rollback (blue/green) — v3
 *   - Verify (staging gates) — v3
 *   - Log tail (v1)
 *
 * No frameworks, no build step.
 */

const POLL_MS = 5000;

// ---------------------------------------------------------------------------
// API helper
// ---------------------------------------------------------------------------

async function api(route, method = 'GET', body = null) {
  const opts = { method, headers: {} };
  if (body !== null) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  try {
    const res = await fetch('index.php?route=api/' + route, opts);
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      return { ok: false, data: null, error: 'Invalid JSON from server.' };
    }
  } catch (e) {
    return { ok: false, data: null, error: 'Network error: ' + e.message };
  }
}

// ---------------------------------------------------------------------------
// DOM helpers
// ---------------------------------------------------------------------------

function el(id) {
  return document.getElementById(id);
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (c) => ({
    '&': '&',
    '<': '<',
    '>': '>',
    '"': '"',
    "'": '\u0027',
  }[c]));
}

function setOutput(id, text) {
  const node = el(id);
  if (node) node.textContent = text;
}

function setVerdict(id, ok, label) {
  const node = el(id);
  if (!node) return;
  node.textContent = label || (ok ? 'PASS' : 'FAIL');
  node.classList.toggle('pass', ok);
  node.classList.toggle('fail', !ok);
}

// ---------------------------------------------------------------------------
// v1: Stack toggles + projects + logs
// ---------------------------------------------------------------------------

function setStackState(text, running) {
  const node = el('stack-state');
  if (!node) return;
  node.textContent = text;
  node.classList.toggle('running', !!running);
}

function renderToggles(statusData) {
  const out = (statusData && statusData.data && statusData.data.output) || '';
  const running = /caddy.*active|tengine.*active|frankenphp.*active/i.test(out) ||
                  /\bUp\b/.test(out);
  setStackState(running ? 'running' : 'stopped', running);
  const startBtn = el('btn-start');
  const stopBtn = el('btn-stop');
  if (startBtn) startBtn.disabled = running;
  if (stopBtn) stopBtn.disabled = !running;
}

function renderProjects(projectsData) {
  const list = el('project-list');
  if (!list) return;
  list.innerHTML = '';
  const projects = (projectsData && projectsData.data && projectsData.data.projects) || [];
  if (projects.length === 0) {
    list.innerHTML = '<p class="empty">No tenants registered.</p>';
    return;
  }
  for (const p of projects) {
    const card = document.createElement('div');
    card.className = 'card';
    const created = p.created ? `<small class="meta">created ${escapeHtml(p.created)}</small>` : '';
    card.innerHTML =
      '<h3>' + escapeHtml(p.name) + '</h3>' +
      '<a href="' + escapeHtml(p.url) + '" target="_blank" rel="noopener">' +
      escapeHtml(p.url) + '</a>' + created;
    list.appendChild(card);
  }
}

function renderLogs(logsData) {
  setOutput('log-pane', (logsData && logsData.data && logsData.data.output) || '(no logs)');
}

// ---------------------------------------------------------------------------
// v3: Stack shape panel
// ---------------------------------------------------------------------------

async function refreshStack() {
  const data = await api('stack');
  const info = el('stack-info');
  if (!info) return;
  if (!data || !data.ok) {
    info.textContent = '(stack read failed)';
    return;
  }
  const d = data.data || {};
  info.textContent = `env=${d.env || '?'} mode=${d.mode || '?'} data=${d.data_source || '?'}`;
  // Highlight the active button.
  const slimBtn = el('btn-stack-slim');
  const fullBtn = el('btn-stack-full');
  if (slimBtn) slimBtn.disabled = (d.mode === 'slim');
  if (fullBtn) fullBtn.disabled = (d.mode === 'full');
}

async function setStackMode(mode) {
  const data = await api('stack', 'POST', { mode });
  setOutput('doctor-output', (data && data.data && data.data.output) || '');
  await refreshStack();
}

// ---------------------------------------------------------------------------
// v3: Doctor panel
// ---------------------------------------------------------------------------

async function runDoctor() {
  const data = await api('doctor');
  const out = (data && data.data && data.data.output) || '(no output)';
  setOutput('doctor-output', out);
  setVerdict('doctor-verdict', !!(data && data.ok));
}

// ---------------------------------------------------------------------------
// v3: Deploy / rollback panel
// ---------------------------------------------------------------------------

async function runDeploy() {
  const env = el('deploy-env') ? el('deploy-env').value : 'staging';
  const release = el('deploy-release') ? el('deploy-release').value.trim() : '';
  setOutput('deploy-output', 'Deploying... (this can take 30+ seconds)');
  const data = await api('deploy', 'POST', { env, release });
  const out = (data && data.data && data.data.output) || '(no output)';
  setOutput('deploy-output', out);
}

async function runRollback() {
  const env = el('deploy-env') ? el('deploy-env').value : 'staging';
  setOutput('deploy-output', 'Rolling back...');
  const data = await api('rollback', 'POST', { env });
  const out = (data && data.data && data.data.output) || '(no output)';
  setOutput('deploy-output', out);
}

// ---------------------------------------------------------------------------
// v3: Verify panel
// ---------------------------------------------------------------------------

async function runVerify() {
  const gate = el('verify-gate') ? el('verify-gate').value : 'all';
  setOutput('verify-output', `Running gate: ${gate}...`);
  const data = await api('verify', 'POST', { gate });
  const out = (data && data.data && data.data.output) || '(no output)';
  setOutput('verify-output', out);
}

// ---------------------------------------------------------------------------
// Refresh + wire up
// ---------------------------------------------------------------------------

async function refresh() {
  try {
    const [status, projects, logs] = await Promise.all([
      api('status'),
      api('projects'),
      api('logs'),
    ]);
    renderToggles(status);
    renderProjects(projects);
    renderLogs(logs);
    await refreshStack();
  } catch (e) {
    setStackState('error', false);
  }
}

async function doAction(action) {
  if (action === 'start') {
    await api('start', 'POST');
  } else if (action === 'stop') {
    await api('stop', 'POST');
  }
  await refresh();
}

document.addEventListener('DOMContentLoaded', () => {
  // v1
  const startBtn = el('btn-start');
  const stopBtn = el('btn-stop');
  if (startBtn) startBtn.addEventListener('click', () => doAction('start'));
  if (stopBtn) stopBtn.addEventListener('click', () => doAction('stop'));

  // v3: stack shape
  const slimBtn = el('btn-stack-slim');
  const fullBtn = el('btn-stack-full');
  if (slimBtn) slimBtn.addEventListener('click', () => setStackMode('slim'));
  if (fullBtn) fullBtn.addEventListener('click', () => setStackMode('full'));

  // v3: doctor
  const doctorBtn = el('btn-doctor');
  if (doctorBtn) doctorBtn.addEventListener('click', runDoctor);

  // v3: deploy / rollback
  const deployBtn = el('btn-deploy');
  const rollbackBtn = el('btn-rollback');
  if (deployBtn) deployBtn.addEventListener('click', runDeploy);
  if (rollbackBtn) rollbackBtn.addEventListener('click', runRollback);

  // v3: verify
  const verifyBtn = el('btn-verify');
  if (verifyBtn) verifyBtn.addEventListener('click', runVerify);

  refresh();
  setInterval(refresh, POLL_MS);
});
