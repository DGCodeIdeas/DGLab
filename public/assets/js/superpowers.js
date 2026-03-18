/**
 * Superpowers JS Runtime - Phase 8 (Morphing, Loading & Optimistic)
 */
document.addEventListener('DOMContentLoaded', () => {
    const registry = new Map();

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
            }
        } catch (err) {
            console.error('Superpowers Bridge Error:', err);
        } finally {
            applyLoadingStates(loadingEls, false);
        }
    }

    function applyOptimistic(boundary, expression) {
        // Optimistic UI logic:
        // Support: "hide:#id", "show:#id", "toggle:#id", "text:#id:Value"
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

    initReactiveElements();
});
