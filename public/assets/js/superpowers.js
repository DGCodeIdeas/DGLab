/**
 * Superpowers JS Runtime - Phase 9 (DX & Observability)
 */
document.addEventListener('DOMContentLoaded', () => {
    const registry = new Map();
    let debugOverlay = null;

    async function handleAction(el, action, event) {
        const boundary = el.closest('[s-data]');
        if (!boundary) return;

        const state = boundary.getAttribute('s-data');
        const view = boundary.getAttribute('s-view');

        const loadingEls = findLoadingElements(boundary, el);
        applyLoadingStates(loadingEls, true);

        if (el.hasAttribute('s-optimistic')) {
             applyOptimistic(boundary, el.getAttribute('s-optimistic'));
        }

        // Log event to debug overlay if it exists
        if (debugOverlay) {
            debugOverlay.logEvent({
                type: 'action',
                action: action,
                view: view,
                timestamp: new Date().toLocaleTimeString()
            });
        }

        try {
            const response = await fetch('/_superpowers/action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('input[name="_token"]')?.value
                },
                body: JSON.stringify({ action, state, view })
            });

            if (response.ok) {
                const result = await response.json();
                morph(boundary, result.html);

                if (debugOverlay) {
                    debugOverlay.updateState(result.state);
                }
            }
        } catch (err) {
            console.error('Superpowers Bridge Error:', err);
        } finally {
            applyLoadingStates(loadingEls, false);
        }
    }

    function applyOptimistic(boundary, expression) {
        const parts = expression.split(':');
        const cmd = parts[0];
        const selector = parts[1];
        const val = parts[2];

        const target = boundary.querySelector(selector) || document.querySelector(selector);
        if (!target) return;

        switch (cmd) {
            case 'hide': target.style.display = 'none'; break;
            case 'show': target.style.display = 'block'; break;
            case 'toggle': target.style.display = (target.style.display === 'none' ? 'block' : 'none'); break;
            case 'text': target.textContent = val; break;
            case 'class': target.classList.add(val); break;
        }
    }

    function findLoadingElements(boundary, triggerEl) {
        const els = [];
        if (triggerEl.hasAttribute('s-loading.class') || triggerEl.hasAttribute('s-loading.attr')) {
            els.push(triggerEl);
        }
        if (triggerEl.hasAttribute('s-loading.target')) {
             const targetSelector = triggerEl.getAttribute('s-loading.target');
             document.querySelectorAll(targetSelector).forEach(el => els.push(el));
        }
        boundary.querySelectorAll('[s-loading]').forEach(el => els.push(el));
        return els;
    }

    function applyLoadingStates(elements, isLoading) {
        elements.forEach(el => {
            if (el.hasAttribute('s-loading')) {
                 el.style.display = isLoading ? 'block' : 'none';
            }
            if (el.hasAttribute('s-loading.class')) {
                const className = el.getAttribute('s-loading.class');
                isLoading ? el.classList.add(className) : el.classList.remove(className);
            }
            if (el.hasAttribute('s-loading.attr')) {
                const attr = el.getAttribute('s-loading.attr');
                isLoading ? el.setAttribute(attr, attr) : el.removeAttribute(attr);
            }
        });
    }

    function morph(oldEl, newHtml) {
        const temp = document.createElement('div');
        temp.innerHTML = newHtml;
        const newEl = temp.firstElementChild;
        if (!newEl) return;
        recursiveMorph(oldEl, newEl);
    }

    function recursiveMorph(oldNode, newNode) {
        if (oldNode.nodeType !== newNode.nodeType || oldNode.tagName !== newNode.tagName) {
            oldNode.replaceWith(newNode.cloneNode(true));
            return;
        }

        const oldAttrs = oldNode.attributes;
        const newAttrs = newNode.attributes;

        if (oldAttrs) {
            for (let i = oldAttrs.length - 1; i >= 0; i--) {
                const attr = oldAttrs[i].name;
                if (!newNode.hasAttribute(attr)) oldNode.removeAttribute(attr);
            }
        }
        if (newAttrs) {
            for (let i = 0; i < newAttrs.length; i++) {
                const attr = newAttrs[i].name;
                const val = newAttrs[i].value;
                if (oldNode.getAttribute(attr) !== val) oldNode.setAttribute(attr, val);
            }
        }

        if (oldNode.nodeType === Node.TEXT_NODE || oldNode.nodeType === Node.COMMENT_NODE) {
            if (oldNode.textContent !== newNode.textContent) oldNode.textContent = newNode.textContent;
            return;
        }

        const oldChildren = Array.from(oldNode.childNodes);
        const newChildren = Array.from(newNode.childNodes);

        const max = Math.max(oldChildren.length, newChildren.length);
        for (let i = 0; i < max; i++) {
            if (!oldChildren[i]) {
                oldNode.appendChild(newChildren[i].cloneNode(true));
            } else if (!newChildren[i]) {
                oldNode.removeChild(oldChildren[i]);
            } else {
                recursiveMorph(oldChildren[i], newChildren[i]);
            }
        }

        initReactiveElements(oldNode);
    }

    function initReactiveElements(root = document) {
        root.querySelectorAll('[s-on\\:click]').forEach(el => {
            if (registry.has(el)) return;
            el.addEventListener('click', e => {
                e.preventDefault();
                handleAction(el, el.getAttribute('s-on:click'), e);
            });
            registry.set(el, true);
        });
    }

    /**
     * Debug Overlay Component
     */
    class SuperpowersDebugOverlay {
        constructor() {
            this.container = document.getElementById('superpowers-debug-overlay');
            if (!this.container) return;

            this.meta = JSON.parse(this.container.getAttribute('data-meta'));
            this.history = [];
            this.isOpen = false;
            this.activeTab = 'components';

            this.initUI();
            debugOverlay = this;
        }

        initUI() {
            const styles = \`
                #sp-debug-trigger { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; background: #6366f1; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 99999; font-weight: bold; font-family: sans-serif; transition: transform 0.2s; }
                #sp-debug-trigger:hover { transform: scale(1.1); }
                #sp-debug-panel { position: fixed; bottom: 80px; right: 20px; width: 600px; height: 700px; background: #1e293b; border-radius: 12px; border: 1px solid #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 99998; display: none; flex-direction: column; overflow: hidden; font-family: 'SF Mono', monospace; font-size: 12px; color: #f1f5f9; }
                #sp-debug-panel.is-open { display: flex; }
                .sp-debug-header { padding: 12px 16px; background: #0f172a; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
                .sp-debug-tabs { display: flex; gap: 8px; padding: 8px 16px; background: #1e293b; border-bottom: 1px solid #334155; }
                .sp-debug-tab { padding: 4px 8px; cursor: pointer; border-radius: 4px; color: #94a3b8; }
                .sp-debug-tab.active { background: #334155; color: #f1f5f9; }
                .sp-debug-content { flex: 1; overflow-y: auto; padding: 16px; }
                .sp-debug-item { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #334155; }
                .sp-debug-label { color: #6366f1; font-weight: bold; }
                .sp-debug-value { color: #94a3b8; word-break: break-all; white-space: pre-wrap; background: #0f172a; padding: 8px; border-radius: 4px; margin-top: 4px; }
                .sp-debug-pill { display: inline-block; padding: 2px 6px; background: #334155; border-radius: 4px; font-size: 10px; margin-right: 4px; }
                .sp-debug-code { font-size: 11px; background: #000; padding: 10px; border-radius: 4px; overflow-x: auto; color: #10b981; }
            \`;

            const styleEl = document.createElement('style');
            styleEl.innerHTML = styles;
            document.head.appendChild(styleEl);

            const trigger = document.createElement('div');
            trigger.id = 'sp-debug-trigger';
            trigger.innerHTML = 'SP';
            trigger.onclick = () => this.toggle();
            document.body.appendChild(trigger);

            const panel = document.createElement('div');
            panel.id = 'sp-debug-panel';
            panel.innerHTML = \`
                <div class="sp-debug-header">
                    <strong>Superpowers Debug</strong>
                    <span>PHP \${this.meta.php_version}</span>
                </div>
                <div class="sp-debug-tabs">
                    <div class="sp-debug-tab active" data-tab="components">Components</div>
                    <div class="sp-debug-tab" data-tab="views">Views</div>
                    <div class="sp-debug-tab" data-tab="events">Events</div>
                </div>
                <div class="sp-debug-content" id="sp-debug-body"></div>
            \`;
            document.body.appendChild(panel);

            panel.querySelectorAll('.sp-debug-tab').forEach(tab => {
                tab.onclick = () => this.switchTab(tab.getAttribute('data-tab'));
            });

            this.renderContent();
        }

        toggle() {
            this.isOpen = !this.isOpen;
            document.getElementById('sp-debug-panel').classList.toggle('is-open', this.isOpen);
        }

        switchTab(tab) {
            this.activeTab = tab;
            document.querySelectorAll('.sp-debug-tab').forEach(el => {
                el.classList.toggle('active', el.getAttribute('data-tab') === tab);
            });
            this.renderContent();
        }

        logEvent(event) {
            this.history.unshift(event);
            if (this.activeTab === 'events') this.renderContent();
        }

        updateState(state) {
            // Update state logic
        }

        renderContent() {
            const body = document.getElementById('sp-debug-body');
            body.innerHTML = '';

            if (this.activeTab === 'components') {
                this.meta.components.forEach(c => {
                    const item = document.createElement('div');
                    item.className = 'sp-debug-item';
                    item.innerHTML = \`
                        <div><span class="sp-debug-label">\${c.name}</span></div>
                        <div class="sp-debug-value">\${JSON.stringify(c.props, null, 2)}</div>
                    \`;
                    body.appendChild(item);
                });
            } else if (this.activeTab === 'views') {
                this.meta.views.forEach(v => {
                    const item = document.createElement('div');
                    item.className = 'sp-debug-item';
                    item.innerHTML = \`
                        <div style="margin-bottom:8px"><span class="sp-debug-label">\${v.name}</span> <span class="sp-debug-pill">\${v.path}</span></div>
                        <div style="margin-bottom:4px"><strong>Source:</strong></div>
                        <pre class="sp-debug-code">\${this.escapeHtml(v.source)}</pre>
                        \${v.compiled ? \`
                            <div style="margin-top:8px; margin-bottom:4px"><strong>Compiled PHP:</strong></div>
                            <pre class="sp-debug-code" style="color:#60a5fa">\${this.escapeHtml(v.compiled)}</pre>
                        \` : ''}
                        <div style="margin-top:8px; margin-bottom:4px"><strong>Initial State:</strong></div>
                        <div class="sp-debug-value">\${JSON.stringify(v.state, null, 2)}</div>
                    \`;
                    body.appendChild(item);
                });
            } else if (this.activeTab === 'events') {
                if (this.history.length === 0) {
                    body.innerHTML = '<div style="color:#94a3b8; text-align:center; margin-top:20px">No actions triggered yet.</div>';
                }
                this.history.forEach(e => {
                    const item = document.createElement('div');
                    item.className = 'sp-debug-item';
                    item.innerHTML = \`
                        <div><span class="sp-debug-pill">\${e.timestamp}</span> <span class="sp-debug-label">\${e.action}</span></div>
                        <div class="sp-debug-value">View: \${e.view}</div>
                    \`;
                    body.appendChild(item);
                });
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    new SuperpowersDebugOverlay();
    initReactiveElements();
});
