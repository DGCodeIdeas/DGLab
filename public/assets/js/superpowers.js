/**
 * Superpowers JS Runtime
 */
window.Superpowers = { registry: new Map() };

document.addEventListener('DOMContentLoaded', () => {
    const registry = window.Superpowers.registry;
    let debugOverlay = null;

    Superpowers.findLoadingElements = function(boundary, trigger) { return boundary.querySelectorAll('[s-loading]'); };
    Superpowers.applyLoadingStates = function(elements, isLoading) { elements.forEach(el => { if (isLoading) el.classList.add('s-loading-active'); else el.classList.remove('s-loading-active'); }); };
    Superpowers.morph = function(oldNode, html) { const temp = document.createElement('div'); temp.innerHTML = html; const newNode = temp.firstElementChild; Superpowers.recursiveMorph(oldNode, newNode); };

    Superpowers.recursiveMorph = function(oldNode, newNode) {
        if (oldNode.nodeName !== newNode.nodeName) { oldNode.replaceWith(newNode.cloneNode(true)); return; }
        const oldAttrs = oldNode.attributes; const newAttrs = newNode.attributes;
        if (oldAttrs && newAttrs) {
            for (let i = oldAttrs.length - 1; i >= 0; i--) { if (!newNode.hasAttribute(oldAttrs[i].name)) oldNode.removeAttribute(oldAttrs[i].name); }
            for (let i = 0; i < newAttrs.length; i++) { if (oldNode.getAttribute(newAttrs[i].name) !== newAttrs[i].value) oldNode.setAttribute(newAttrs[i].name, newAttrs[i].value); }
        }
        if (oldNode.nodeType === Node.TEXT_NODE || oldNode.nodeType === Node.COMMENT_NODE) { if (oldNode.textContent !== newNode.textContent) oldNode.textContent = newNode.textContent; return; }
        const oldChildren = Array.from(oldNode.childNodes); const newChildren = Array.from(newNode.childNodes);
        const max = Math.max(oldChildren.length, newChildren.length);
        for (let i = 0; i < max; i++) {
            if (!oldChildren[i]) oldNode.appendChild(newChildren[i].cloneNode(true));
            elif (!newChildren[i]) oldNode.removeChild(oldChildren[i]);
            else Superpowers.recursiveMorph(oldChildren[i], newChildren[i]);
        }
        Superpowers.initReactiveElements(oldNode);
    };

    Superpowers.initReactiveElements = function(root = document) {
        root.querySelectorAll('[s-on\\:click]').forEach(el => {
            if (Superpowers.registry.has(el)) return;
            el.addEventListener('click', e => { e.preventDefault(); Superpowers.handleAction(el, el.getAttribute('s-on:click'), e); });
            Superpowers.registry.set(el, true);
        });
    };

    async Superpowers.handleAction = function(el, action, event) {
        const boundary = el.closest('[s-data]'); if (!boundary) return;
        const state = boundary.getAttribute('s-data'); const view = boundary.getAttribute('s-view');
        const loadingEls = Superpowers.findLoadingElements(boundary, el); Superpowers.applyLoadingStates(loadingEls, true);
        if (!navigator.onLine) { console.log('[Superpowers] Offline'); return; }
        try {
            const response = await fetch('/_superpowers/action', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('input[name="_token"]')?.value },
                body: JSON.stringify({ action, state, view })
            });
            if (response.ok) {
                const result = await response.json(); Superpowers.morph(boundary, result.html);
                if (result.changedPersisted && Object.keys(result.changedPersisted).length > 0) window.dispatchEvent(new CustomEvent('superpowers:state-changed', { detail: result.changedPersisted }));
            }
        } catch (err) { console.error('Superpowers Bridge Error:', err); } finally { Superpowers.applyLoadingStates(loadingEls, false); }
    };

    new SuperpowersDebugOverlay();
    Superpowers.initReactiveElements();
});

class SuperpowersDebugOverlay {
    constructor() {
        this.container = document.getElementById('superpowers-debug-overlay'); if (!this.container) return;
        this.meta = JSON.parse(this.container.getAttribute('data-meta')); this.activeTab = 'components'; this.initUI();
    }
    initUI() {
        const style = document.createElement('style'); style.innerHTML = '#sp-debug-trigger { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; background: #6366f1; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 99999; }'; document.head.appendChild(style);
        const trigger = document.createElement('div'); trigger.id = 'sp-debug-trigger'; trigger.innerHTML = 'SP'; document.body.appendChild(trigger);
    }
}
