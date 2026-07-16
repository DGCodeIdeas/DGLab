'use strict';

/**
 * Anvil Web UI — dependency-free SPA.
 *
 * Polls the front controller (index.php?route=api/...) on an interval and
 * renders: stack start/stop toggles, project cards (name, URL, SSL badge),
 * and a log tail pane. No frameworks, no build step.
 */

const POLL_MS = 5000;

/**
 * Call an API endpoint.
 * @param {string} route  e.g. "status", "projects", "start"
 * @param {string} [method]
 * @param {Object|null} [body]
 * @returns {Promise<Object|null>}
 */
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

function setStackState(text, running) {
  const node = el('stack-state');
  if (!node) return;
  node.textContent = text;
  node.classList.toggle('running', !!running);
}

function renderToggles(statusData) {
  const out = (statusData && statusData.data && statusData.data.output) || '';
  const running = /(^|\n)\s*\S+\s+Up\b/.test(out) || /\bUp\b/.test(out);
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
    list.innerHTML = '<p class="empty">No projects registered.</p>';
    return;
  }
  for (const p of projects) {
    const card = document.createElement('div');
    card.className = 'card';
    const sslBadge = p.ssl
      ? '<span class="badge ssl-on">SSL</span>'
      : '<span class="badge ssl-off">no SSL</span>';
    card.innerHTML =
      '<h3>' + escapeHtml(p.name) + '</h3>' +
      '<a href="' + escapeHtml(p.url) + '" target="_blank" rel="noopener">' +
      escapeHtml(p.url) + '</a>' +
      '<div>' + sslBadge + '</div>';
    list.appendChild(card);
  }
}

function renderLogs(logsData) {
  const pane = el('log-pane');
  if (!pane) return;
  const out = (logsData && logsData.data && logsData.data.output) || '(no logs)';
  pane.textContent = out;
}

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
  const startBtn = el('btn-start');
  const stopBtn = el('btn-stop');
  if (startBtn) startBtn.addEventListener('click', () => doAction('start'));
  if (stopBtn) stopBtn.addEventListener('click', () => doAction('stop'));
  refresh();
  setInterval(refresh, POLL_MS);
});
